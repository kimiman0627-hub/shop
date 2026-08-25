<?php

declare(strict_types=1);

namespace App\Libraries\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\StockMovementType;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * 재고 예약·해제·판매확정 (docs/schema-draft.md §7).
 *
 * 실물(stock_quantity)과 예약(reserved_quantity)을 분리한다.
 * 판매가능 = 실물 − 예약.
 *
 * **이 클래스의 메서드는 반드시 바깥 트랜잭션 안에서 호출한다.**
 * 주문 생성·결제 확정과 원자적으로 묶여야 하기 때문이다.
 */
class StockLibrary
{
    /**
     * 조합들을 잠그고 가져온다.
     *
     * **id 오름차순으로 잠근다.** 순서가 엇갈리면 두 주문이 서로를 기다리는
     * 데드락이 난다 (§7.6). SQLite 에서는 lockForUpdate 가 무효라 이 보호가 없다.
     *
     * @param  list<int>  $variantIds
     * @return Collection<int, ProductVariant> id 로 색인됨
     */
    public function lockVariants(array $variantIds): Collection
    {
        $ids = array_values(array_unique($variantIds));
        sort($ids);

        return ProductVariant::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * 주문 생성 시 예약을 잡는다. 실물은 건드리지 않는다.
     *
     * @param  array<int, int>  $quantities  variant_id => 수량
     * @param  Collection<int, ProductVariant>  $locked  lockVariants() 결과
     */
    public function reserve(array $quantities, Collection $locked, ?int $orderId = null): void
    {
        foreach ($quantities as $variantId => $quantity) {
            $variant = $locked->get($variantId);

            if ($variant === null) {
                throw new DomainRuleException('상품 정보를 찾을 수 없습니다.');
            }

            if (! $variant->is_active) {
                throw new DomainRuleException("판매 중지된 상품이 있습니다: {$variant->sku}");
            }

            $available = $variant->stock_quantity - $variant->reserved_quantity;

            if ($available < $quantity) {
                throw new DomainRuleException(
                    $available > 0
                        ? "재고가 부족합니다. (SKU {$variant->sku}: {$available}개 남음)"
                        : "품절된 상품이 있습니다. (SKU {$variant->sku})",
                );
            }

            $variant->reserved_quantity += $quantity;
            $variant->save();

            $this->record($variant, StockMovementType::RESERVE, 0, $quantity, $orderId);
        }
    }

    /**
     * 결제 완료. 예약을 실물 차감으로 바꾼다.
     */
    public function confirmSale(Order $order): void
    {
        $order->loadMissing('items');

        $locked = $this->lockVariants(
            $order->items->pluck('product_variant_id')->filter()->all(),
        );

        foreach ($order->items as $item) {
            $variant = $locked->get($item->product_variant_id);

            if ($variant === null) {
                // 조합이 사라졌다면 예약도 cascade 로 없어졌다. 기록만 남기지 않고 넘어간다.
                continue;
            }

            $variant->reserved_quantity = max(0, $variant->reserved_quantity - $item->quantity);
            $variant->stock_quantity -= $item->quantity;
            $variant->save();

            $this->record($variant, StockMovementType::SELL, -$item->quantity, -$item->quantity, $order->id);
        }
    }

    /**
     * 예약 해제. 결제 실패·취소·만료에서 쓴다. 실물은 건드리지 않는다.
     *
     * **멱등하다.** stock_released_at 이 이미 있으면 아무것도 하지 않는다.
     * 이게 없으면 스케줄러 재시도가 reserved_quantity 를 음수로 만든다 (§7.4).
     */
    public function release(Order $order, string $memo = ''): bool
    {
        if (! $order->holdsReservation()) {
            return false;
        }

        $order->loadMissing('items');

        $locked = $this->lockVariants(
            $order->items->pluck('product_variant_id')->filter()->all(),
        );

        foreach ($order->items as $item) {
            $variant = $locked->get($item->product_variant_id);

            if ($variant === null) {
                continue;
            }

            $variant->reserved_quantity = max(0, $variant->reserved_quantity - $item->quantity);
            $variant->save();

            $this->record(
                $variant,
                StockMovementType::RELEASE,
                0,
                -$item->quantity,
                $order->id,
                $memo,
            );
        }

        $order->forceFill(['stock_released_at' => now()])->save();

        return true;
    }

    /**
     * 결제 완료된 주문을 취소·환불할 때 실물을 되돌린다.
     */
    public function restock(Order $order, string $memo = ''): void
    {
        $order->loadMissing('items');

        $locked = $this->lockVariants(
            $order->items->pluck('product_variant_id')->filter()->all(),
        );

        foreach ($order->items as $item) {
            $variant = $locked->get($item->product_variant_id);

            if ($variant === null) {
                continue;
            }

            $variant->stock_quantity += $item->quantity;
            $variant->save();

            $this->record($variant, StockMovementType::RESTOCK, $item->quantity, 0, $order->id, $memo);
        }
    }

    /**
     * 조합 단위 입고. **부분 반품**에서 쓴다.
     *
     * restock(Order) 은 주문 전체를 되돌리므로 일부 수량만 돌아오는
     * 반품에는 쓸 수 없다. 여기는 반품 항목에서 계산한 수량만 더한다.
     *
     * @param  array<int, int>  $quantities  variant_id => 수량
     */
    public function restockVariants(array $quantities, ?int $orderId = null, string $memo = ''): void
    {
        $locked = $this->lockVariants(array_keys($quantities));

        foreach ($quantities as $variantId => $quantity) {
            $variant = $locked->get($variantId);

            if ($variant === null || $quantity <= 0) {
                // 조합이 사라졌으면 되돌릴 자리가 없다. 반품 처리 자체는 계속한다.
                continue;
            }

            $variant->stock_quantity += $quantity;
            $variant->save();

            $this->record($variant, StockMovementType::RESTOCK, $quantity, 0, $orderId, $memo);
        }
    }

    /**
     * 조합 단위 출고. **교환 재발송**에서 쓴다.
     *
     * 이미 결제가 끝난 건이므로 예약 단계를 거치지 않고 실물에서 바로 뺀다.
     * 다만 없는 재고를 보낼 수는 없으므로 가용량은 확인한다.
     *
     * @param  array<int, int>  $quantities  variant_id => 수량
     */
    public function shipOutVariants(array $quantities, ?int $orderId = null, string $memo = ''): void
    {
        $locked = $this->lockVariants(array_keys($quantities));

        foreach ($quantities as $variantId => $quantity) {
            $variant = $locked->get($variantId);

            if ($variant === null) {
                throw new DomainRuleException('교환할 상품 정보를 찾을 수 없습니다.');
            }

            $available = $variant->stock_quantity - $variant->reserved_quantity;

            if ($available < $quantity) {
                throw new DomainRuleException(
                    "교환할 상품의 재고가 부족합니다. (SKU {$variant->sku}: {$available}개 남음)",
                );
            }

            $variant->stock_quantity -= $quantity;
            $variant->save();

            $this->record($variant, StockMovementType::MANUAL_OUT, -$quantity, 0, $orderId, $memo);
        }
    }

    /**
     * 정합성 점검: 진행중 주문에서 계산한 예약량과 컬럼 값이 맞는가.
     *
     * **자동 수정하지 않는다.** 원인을 봐야 한다 (§7.5).
     *
     * @return list<array{variant_id: int, sku: string, column: int, expected: int}>
     */
    public function findReservationDrift(): array
    {
        // PENDING 이고 아직 해제되지 않은 주문의 항목만 예약을 잡고 있다.
        // selectRaw / GROUP BY 대신 컬렉션에서 집계한다 — 이식성 (CLAUDE.md §5.1).
        $expected = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('status', OrderStatus::PENDING->value)
                ->whereNull('stock_released_at'))
            ->get(['product_variant_id', 'quantity'])
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        $drift = [];

        foreach (ProductVariant::query()->orderBy('id')->cursor() as $variant) {
            $want = (int) ($expected[$variant->id] ?? 0);

            if ($variant->reserved_quantity !== $want) {
                $drift[] = [
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'column' => $variant->reserved_quantity,
                    'expected' => $want,
                ];
            }
        }

        return $drift;
    }

    private function record(
        ProductVariant $variant,
        StockMovementType $type,
        int $stockDelta,
        int $reservedDelta,
        ?int $orderId = null,
        string $memo = '',
    ): void {
        StockMovement::query()->create([
            'product_variant_id' => $variant->id,
            'type' => $type,
            'stock_delta' => $stockDelta,
            'reserved_delta' => $reservedDelta,
            'stock_after' => $variant->stock_quantity,
            'reserved_after' => $variant->reserved_quantity,
            'order_id' => $orderId,
            'memo' => $memo !== '' ? $memo : null,
            'created_at' => now(),
        ]);
    }
}
