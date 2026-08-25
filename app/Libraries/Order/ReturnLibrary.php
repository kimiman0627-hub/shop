<?php

declare(strict_types=1);

namespace App\Libraries\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Returns\ReturnReason;
use App\Enums\Returns\ReturnResponsibility;
use App\Enums\Returns\ReturnStatus;
use App\Enums\Returns\ReturnType;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 반품·교환 (docs/schema-draft.md §11).
 *
 * 취소와의 경계: **출고 전은 취소, 출고 후는 반품**이다.
 * 취소는 주문을 통째로 되돌리지만 반품은 항목·수량 단위라 부분 처리가 된다.
 *
 * 진행:
 *   REQUESTED → APPROVED → PICKING → RECEIVED → COMPLETED
 *             ↘ REJECTED
 *
 * 재고와 돈이 실제로 움직이는 시점은 COMPLETED 한 곳뿐이다.
 * 물건을 받기도 전에 환불하면 돌려받지 못하는 사고가 난다.
 */
class ReturnLibrary
{
    public function __construct(
        private readonly StockLibrary $stock,
        private readonly CouponLibrary $coupons,
    ) {}

    /* ------------------------------------------------------------------ 신청 */

    /**
     * 이 주문에서 아직 반품 신청이 가능한 항목과 남은 수량.
     *
     * @return list<array<string, mixed>>
     */
    public function requestableItems(int $orderId): array
    {
        $order = Order::query()->with('items')->findOrFail($orderId);
        $remaining = $this->remainingQuantities($order);

        return $order->items->map(fn (OrderItem $item) => [
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'sku' => $item->sku,
            'unit_price' => $item->unit_price,
            'ordered_quantity' => $item->quantity,
            'remaining_quantity' => $remaining[$item->id] ?? 0,

            // 교환으로 바꿀 수 있는 조합. 같은 상품 안에서만 고른다.
            'exchange_options' => $this->exchangeOptions($item),
        ])->all();
    }

    /**
     * 반품·교환 접수.
     *
     * @param  array{type: string, reason: string, reason_detail?: string|null, items: array<int, array{order_item_id: int|string, quantity: int|string, exchange_variant_id?: int|string|null}>}  $form
     * @param  bool  $byAdmin  관리자 대행 접수는 신청 기한을 적용하지 않는다 (전화 접수 등)
     */
    public function create(int $orderId, array $form, bool $byAdmin = false): OrderReturn
    {
        return DB::transaction(function () use ($orderId, $form, $byAdmin) {
            $order = Order::query()->with(['items', 'shipment'])->lockForUpdate()->findOrFail($orderId);

            $this->assertReturnable($order, $byAdmin);

            $type = ReturnType::from($form['type']);
            $reason = ReturnReason::from($form['reason']);

            $remaining = $this->remainingQuantities($order);
            $itemsById = $order->items->keyBy('id');

            $rows = [];

            foreach ($form['items'] ?? [] as $line) {
                $quantity = (int) ($line['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $orderItemId = (int) $line['order_item_id'];
                $item = $itemsById->get($orderItemId);

                if ($item === null) {
                    throw new DomainRuleException('이 주문에 없는 상품입니다.');
                }

                $left = $remaining[$orderItemId] ?? 0;

                if ($quantity > $left) {
                    throw new DomainRuleException(
                        $left > 0
                            ? "{$item->product_name}: 신청 가능 수량은 {$left}개입니다."
                            : "{$item->product_name}: 이미 반품·교환이 접수된 상품입니다.",
                    );
                }

                $rows[] = [
                    'order_item_id' => $orderItemId,
                    'quantity' => $quantity,
                    'exchange_variant_id' => $type === ReturnType::EXCHANGE
                        ? $this->resolveExchangeVariant($item, $line['exchange_variant_id'] ?? null)
                        : null,
                ];
            }

            if ($rows === []) {
                throw new DomainRuleException('반품·교환할 상품을 선택해 주세요.');
            }

            $return = OrderReturn::query()->create([
                'order_id' => $order->id,
                'type' => $type,
                'reason' => $reason,
                'reason_detail' => $form['reason_detail'] ?? null,
                // 접수 시점에는 사유가 말하는 기본값을 넣는다. 승인 때 관리자가 확정한다.
                'responsibility' => $reason->defaultResponsibility(),
                'status' => ReturnStatus::REQUESTED,
                'restock' => $reason->defaultRestockable(),
                'requested_at' => now(),
            ]);

            $return->items()->createMany($rows);

            return $return->load('items');
        });
    }

    /** 고객이 접수를 물린다. 관리자가 손대기 전에만 가능하다. */
    public function cancelRequest(int $returnId, ?int $userId = null): void
    {
        DB::transaction(function () use ($returnId, $userId) {
            $return = $this->lockReturn($returnId);

            if ($userId !== null && $return->order->user_id !== $userId) {
                throw new DomainRuleException('본인의 신청만 취소할 수 있습니다.');
            }

            if ($return->status !== ReturnStatus::REQUESTED) {
                throw new DomainRuleException(
                    "이미 {$return->status->label()} 처리된 신청은 고객이 취소할 수 없습니다. 고객센터로 문의해 주세요.",
                );
            }

            // 반려와 같은 자리로 보낸다. 항목 점유가 풀려 다시 신청할 수 있다.
            $return->forceFill([
                'status' => ReturnStatus::REJECTED,
                'reject_reason' => '고객이 신청을 취소했습니다.',
                'rejected_at' => now(),
            ])->save();
        });
    }

    /* ------------------------------------------------------------------ 처리 */

    /**
     * 승인. **여기서 금액을 계산해 스냅샷으로 굳힌다.**
     *
     * @param  array{responsibility?: string|null, restock?: bool|null, admin_memo?: string|null}  $form
     */
    public function approve(int $returnId, int $adminId, array $form = []): OrderReturn
    {
        return DB::transaction(function () use ($returnId, $adminId, $form) {
            $return = $this->lockReturn($returnId);

            if ($return->status !== ReturnStatus::REQUESTED) {
                throw new DomainRuleException("접수 상태의 신청만 승인할 수 있습니다. (현재: {$return->status->label()})");
            }

            // 사유는 고객이 고르지만 귀책은 관리자가 정한다 —
            // 변심으로 접수했는데 실제로는 불량인 경우가 흔하다.
            $responsibility = isset($form['responsibility'])
                ? ReturnResponsibility::from($form['responsibility'])
                : $return->responsibility;

            $return->forceFill([
                'responsibility' => $responsibility,
                'restock' => (bool) ($form['restock'] ?? $return->restock),
                'admin_memo' => $form['admin_memo'] ?? $return->admin_memo,
                'status' => ReturnStatus::APPROVED,
                'approved_at' => now(),
                'handled_by_admin_id' => $adminId,
            ])->save();

            // 금액은 귀책이 확정된 뒤에 계산해야 배송비 부담이 제대로 반영된다.
            $return->forceFill($this->calculateAmounts($return))->save();

            return $return->fresh(['items']);
        });
    }

    public function reject(int $returnId, int $adminId, string $reason): OrderReturn
    {
        return DB::transaction(function () use ($returnId, $adminId, $reason) {
            $return = $this->lockReturn($returnId);

            if ($return->status->isFinal()) {
                throw new DomainRuleException("이미 {$return->status->label()}된 신청입니다.");
            }

            if ($return->status === ReturnStatus::RECEIVED) {
                // 물건이 창고에 들어와 있다. 반려하면 그 물건의 행방이 장부에서 사라진다.
                throw new DomainRuleException('입고완료된 건은 반려할 수 없습니다. 처리완료하거나 별도로 재출고하세요.');
            }

            $return->forceFill([
                'status' => ReturnStatus::REJECTED,
                'reject_reason' => $reason,
                'rejected_at' => now(),
                'handled_by_admin_id' => $adminId,
                // 반려하면 금액 스냅샷은 의미가 없다. 남겨두면 환불 통계에 섞인다.
                'items_refund' => 0,
                'coupon_deduction' => 0,
                'shipping_deduction' => 0,
                'shipping_refund' => 0,
                'refund_amount' => 0,
            ])->save();

            return $return;
        });
    }

    /**
     * 회수 송장 등록.
     *
     * shipments 는 '최초 배송' 만 담으므로 회수 송장은 반품 레코드에 직접 붙인다.
     *
     * @param  array{pickup_carrier: string, pickup_tracking_no?: string|null}  $form
     */
    public function registerPickup(int $returnId, int $adminId, array $form): OrderReturn
    {
        return DB::transaction(function () use ($returnId, $adminId, $form) {
            $return = $this->lockReturn($returnId);

            if ($return->status !== ReturnStatus::APPROVED) {
                throw new DomainRuleException("승인 상태에서만 회수를 등록할 수 있습니다. (현재: {$return->status->label()})");
            }

            $return->forceFill([
                'pickup_carrier' => $this->assertCarrier($form['pickup_carrier'] ?? null),
                'pickup_tracking_no' => $form['pickup_tracking_no'] ?? null,
                'status' => ReturnStatus::PICKING,
                'handled_by_admin_id' => $adminId,
            ])->save();

            return $return;
        });
    }

    /**
     * 입고 확인. 물건이 실제로 도착했다는 뜻이다.
     *
     * 재판매 가능 여부를 여기서 확정한다 — 물건을 봐야 알 수 있기 때문이다.
     * 수거 송장 없이 고객이 직접 보낸 경우가 있어 APPROVED 에서도 바로 받는다.
     *
     * @param  array{restock?: bool|null, admin_memo?: string|null}  $form
     */
    public function markReceived(int $returnId, int $adminId, array $form = []): OrderReturn
    {
        return DB::transaction(function () use ($returnId, $adminId, $form) {
            $return = $this->lockReturn($returnId);

            if (! in_array($return->status, [ReturnStatus::APPROVED, ReturnStatus::PICKING], true)) {
                throw new DomainRuleException("승인·수거중 상태에서만 입고 처리할 수 있습니다. (현재: {$return->status->label()})");
            }

            $return->forceFill([
                'restock' => (bool) ($form['restock'] ?? $return->restock),
                'admin_memo' => $form['admin_memo'] ?? $return->admin_memo,
                'status' => ReturnStatus::RECEIVED,
                'received_at' => now(),
                'handled_by_admin_id' => $adminId,
            ])->save();

            return $return;
        });
    }

    /**
     * 처리완료. **재고와 돈이 여기서 움직인다.**
     *
     * - 반품: 재고 복구(재판매 가능할 때) → 전량이면 주문을 REFUNDED 로
     * - 교환: 회수품 복구 + 교환품 출고 → 주문 상태는 DELIVERED 그대로
     *
     * @param  array{exchange_carrier?: string|null, exchange_tracking_no?: string|null, admin_memo?: string|null}  $form
     */
    public function complete(int $returnId, int $adminId, array $form = []): OrderReturn
    {
        return DB::transaction(function () use ($returnId, $adminId, $form) {
            $return = $this->lockReturn($returnId);

            if ($return->status !== ReturnStatus::RECEIVED) {
                throw new DomainRuleException(
                    "입고완료 상태에서만 처리를 끝낼 수 있습니다. (현재: {$return->status->label()}) 물건을 받기 전에 환불하면 회수하지 못합니다.",
                );
            }

            $order = Order::query()->with('items')->lockForUpdate()->findOrFail($return->order_id);

            // 1) 회수품을 재고로 되돌린다. 불량·파손이면 되돌리지 않는다.
            if ($return->restock) {
                $this->stock->restockVariants(
                    $this->returnedVariantQuantities($return),
                    $order->id,
                    "반품 입고 #{$return->id}",
                );
            }

            // 2) 교환이면 새 조합을 실물에서 뺀다. 재고가 없으면 여기서 막힌다.
            if ($return->type === ReturnType::EXCHANGE) {
                $carrier = $this->assertCarrier($form['exchange_carrier'] ?? null, '교환품을 보낼 택배사를 선택해 주세요.');

                $this->stock->shipOutVariants(
                    $this->exchangeVariantQuantities($return),
                    $order->id,
                    "교환 재발송 #{$return->id}",
                );

                $return->forceFill([
                    'exchange_carrier' => $carrier,
                    'exchange_tracking_no' => $form['exchange_tracking_no'] ?? null,
                ])->save();
            }

            $return->forceFill([
                'status' => ReturnStatus::COMPLETED,
                'completed_at' => now(),
                'admin_memo' => $form['admin_memo'] ?? $return->admin_memo,
                'handled_by_admin_id' => $adminId,
            ])->save();

            // 3) 반품으로 주문 전량이 돌아왔다면 주문 자체를 환불 상태로 내린다.
            if ($return->type === ReturnType::RETURN && $this->isFullyReturned($order)) {
                $this->finalizeRefundedOrder($order);
            }

            return $return->fresh(['items']);
        });
    }

    /* ------------------------------------------------------------------ 금액 */

    /**
     * 환불 금액 스냅샷.
     *
     * 부분 반품이면 쿠폰 할인을 상품 금액 비율로 안분해 차감한다.
     * 안 그러면 1만원 쿠폰을 쓴 주문에서 한 개만 반품해도 할인 전액이 살아남아
     * 고객이 할인분을 두 번 챙긴다.
     *
     * **누적 기준으로 계산한다.** 매 건 따로 내림하면 잔돈이 새서
     * 전량 반품 합계가 실제 결제액과 어긋난다.
     *
     * @return array<string, int>
     */
    private function calculateAmounts(OrderReturn $return): array
    {
        $order = Order::query()->with('items')->findOrFail($return->order_id);
        $return->loadMissing('items.orderItem');

        $itemsRefund = $this->itemsRefundOf($return);

        // 이번 건을 뺀, 이미 확정된(승인 이후) 반품들의 누계.
        $priorReturns = OrderReturn::query()
            ->where('order_id', $order->id)
            ->where('id', '!=', $return->id)
            ->where('type', ReturnType::RETURN->value)
            ->whereIn('status', [
                ReturnStatus::APPROVED->value,
                ReturnStatus::PICKING->value,
                ReturnStatus::RECEIVED->value,
                ReturnStatus::COMPLETED->value,
            ])
            ->with('items')
            ->get();

        $priorItemsRefund = (int) $priorReturns->sum('items_refund');
        $priorCoupon = (int) $priorReturns->sum('coupon_deduction');

        $couponDeduction = 0;

        if ($order->discount_total > 0 && $order->items_total > 0) {
            // 누적 안분액에서 이미 차감한 만큼을 뺀다 → 마지막 건이 잔돈을 흡수한다.
            $cumulative = intdiv(
                $order->discount_total * ($priorItemsRefund + $itemsRefund),
                $order->items_total,
            );

            $couponDeduction = max(0, min($cumulative - $priorCoupon, $itemsRefund));
        }

        $isFull = $this->coversWholeOrder($order, $return, $priorReturns);

        // 고객 귀책이면 반품 배송비를 고객이 낸다 → 환불액에서 뺀다.
        $shippingDeduction = $return->responsibility->customerPaysReturnShipping()
            ? (int) config('shop.return.shipping_fee')
            : 0;

        // 판매자 잘못으로 주문 전체가 돌아오면 처음 받은 배송비도 돌려준다.
        $shippingRefund = ($isFull && ! $return->responsibility->customerPaysReturnShipping())
            ? (int) $order->shipping_fee
            : 0;

        if (! $return->type->needsRefund()) {
            /*
             * 교환은 정산이 없다. shipping_deduction 만 남겨 "고객이 부담할
             * 왕복 배송비" 를 관리자가 알 수 있게 한다 — 실제 수납은 시스템 밖이다.
             */
            return [
                'items_refund' => 0,
                'coupon_deduction' => 0,
                'shipping_deduction' => $shippingDeduction,
                'shipping_refund' => 0,
                'refund_amount' => 0,
            ];
        }

        $refund = $itemsRefund - $couponDeduction - $shippingDeduction + $shippingRefund;

        return [
            'items_refund' => $itemsRefund,
            'coupon_deduction' => $couponDeduction,
            'shipping_deduction' => $shippingDeduction,
            'shipping_refund' => $shippingRefund,
            // 반품 배송비가 상품값보다 크면 환불액이 음수가 된다. 추가 청구 수단이
            // 없으므로 0 으로 막고, 부족분은 관리자가 메모로 관리한다.
            'refund_amount' => max(0, $refund),
        ];
    }

    /**
     * 승인 전에 화면에 보여줄 예상 금액. 저장하지 않는다.
     *
     * 관리자가 귀책을 바꿔가며 환불액이 얼마가 되는지 확인하는 용도다.
     *
     * @return array<string, int>
     */
    public function previewAmounts(int $returnId, ?string $responsibility = null): array
    {
        $return = OrderReturn::query()->with('items')->findOrFail($returnId);

        if ($responsibility !== null) {
            $return->responsibility = ReturnResponsibility::from($responsibility);
        }

        return $this->calculateAmounts($return);
    }

    /** 반품 항목의 상품 금액 합. 단가는 주문 시점 스냅샷을 쓴다 (§4.3). */
    private function itemsRefundOf(OrderReturn $return): int
    {
        $return->loadMissing('items.orderItem');

        return (int) $return->items->sum(
            fn (OrderReturnItem $line) => (int) ($line->orderItem?->unit_price ?? 0) * $line->quantity,
        );
    }

    /* ------------------------------------------------------------------ 판정 */

    /** 주문을 반품할 수 있는 상태인가. */
    private function assertReturnable(Order $order, bool $byAdmin): void
    {
        if (! in_array($order->status, [OrderStatus::SHIPPING, OrderStatus::DELIVERED], true)) {
            throw new DomainRuleException(
                $order->status->isCancelableByCustomer()
                    ? '아직 출고 전인 주문입니다. 반품이 아니라 주문취소로 처리해 주세요.'
                    : "{$order->status->label()} 상태의 주문은 반품·교환을 신청할 수 없습니다.",
            );
        }

        if ($byAdmin) {
            // 관리자 대행 접수는 기한을 보지 않는다. 하자 건은 법정 기한이 더 길고,
            // 예외 처리는 사람이 판단하는 게 맞다.
            return;
        }

        $deliveredAt = $order->shipment?->delivered_at;

        if ($deliveredAt === null) {
            // 아직 배송중이면 기한이 시작되지 않았다. 신청은 받아둔다.
            return;
        }

        $days = (int) config('shop.return.days');

        if ($deliveredAt->copy()->addDays($days)->isPast()) {
            throw new DomainRuleException(
                "반품·교환 신청 기한({$days}일)이 지났습니다. 1:1 문의로 접수해 주세요.",
            );
        }
    }

    /**
     * 주문 항목별 남은 신청 가능 수량.
     *
     * 반려된 건은 점유를 풀어준다 — 반려당한 고객이 다시 신청할 수 있어야 한다.
     *
     * @return array<int, int> order_item_id => 남은 수량
     */
    private function remainingQuantities(Order $order): array
    {
        $order->loadMissing('items');

        $used = OrderReturn::query()
            ->where('order_id', $order->id)
            ->occupying()
            ->with('items')
            ->get()
            ->flatMap(fn (OrderReturn $r) => $r->items)
            ->groupBy('order_item_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        $remaining = [];

        foreach ($order->items as $item) {
            $remaining[$item->id] = max(0, $item->quantity - (int) ($used[$item->id] ?? 0));
        }

        return $remaining;
    }

    /**
     * 이 반품까지 포함하면 주문의 모든 수량이 반품되는가.
     *
     * @param  Collection<int, OrderReturn>  $priorReturns
     */
    private function coversWholeOrder(Order $order, OrderReturn $return, Collection $priorReturns): bool
    {
        $returned = $priorReturns
            ->flatMap(fn (OrderReturn $r) => $r->items)
            ->concat($return->items)
            ->groupBy('order_item_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        foreach ($order->items as $item) {
            if ((int) ($returned[$item->id] ?? 0) < $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /** 처리완료된 반품만 세서 주문 전량이 돌아왔는지 본다. */
    private function isFullyReturned(Order $order): bool
    {
        $returned = OrderReturn::query()
            ->where('order_id', $order->id)
            ->where('type', ReturnType::RETURN->value)
            ->where('status', ReturnStatus::COMPLETED->value)
            ->with('items')
            ->get()
            ->flatMap(fn (OrderReturn $r) => $r->items)
            ->groupBy('order_item_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        foreach ($order->items as $item) {
            if ((int) ($returned[$item->id] ?? 0) < $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * 전량 반품된 주문 마무리. 주문을 REFUNDED 로 내리고 결제와 쿠폰을 정리한다.
     */
    private function finalizeRefundedOrder(Order $order): void
    {
        $order->forceFill([
            'status' => OrderStatus::REFUNDED,
            'canceled_at' => $order->canceled_at ?? now(),
        ])->save();

        Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::PAID->value)
            ->update([
                'status' => PaymentStatus::REFUNDED->value,
                'memo' => '반품 전량 환불',
            ]);

        /*
         * 쿠폰은 **전량 반품일 때만** 되살린다.
         * 부분 반품에서 되살리면 남은 주문에 할인이 적용된 채로 쿠폰을 다시 쓴다.
         * 이미 만료된 쿠폰은 restore() 가 알아서 거른다 (§8.3).
         */
        if ($order->user_coupon_id !== null) {
            $this->coupons->restore($order->user_coupon_id);
        }
    }

    /* ------------------------------------------------------------------ 조합 */

    /**
     * 교환으로 고를 수 있는 조합. 같은 상품 안에서만 바꾼다 —
     * 다른 상품으로 바꾸는 건 금액이 달라져 반품 후 재주문이 맞다.
     *
     * @return list<array<string, mixed>>
     */
    private function exchangeOptions(OrderItem $item): array
    {
        if ($item->product_id === null) {
            return [];
        }

        return ProductVariant::query()
            ->where('product_id', $item->product_id)
            ->where('is_active', true)
            ->with('optionValues.option')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $this->variantLabel($v),
                'available' => max(0, $v->stock_quantity - $v->reserved_quantity),
            ])
            ->all();
    }

    private function resolveExchangeVariant(OrderItem $item, mixed $variantId): int
    {
        if ($variantId === null || $variantId === '') {
            throw new DomainRuleException("{$item->product_name}: 교환받을 옵션을 선택해 주세요.");
        }

        $variant = ProductVariant::query()->find((int) $variantId);

        if ($variant === null || $variant->product_id !== $item->product_id) {
            throw new DomainRuleException('같은 상품의 옵션으로만 교환할 수 있습니다.');
        }

        if (! $variant->is_active) {
            throw new DomainRuleException('판매 중지된 옵션으로는 교환할 수 없습니다.');
        }

        // 재고 확인은 여기서 하지 않는다. 접수와 발송 사이에 재고가 바뀌므로
        // 실제 판단은 complete() 의 shipOutVariants() 에서 잠근 채로 한다.
        return $variant->id;
    }

    /**
     * 회수해서 되돌릴 조합별 수량.
     *
     * @return array<int, int>
     */
    private function returnedVariantQuantities(OrderReturn $return): array
    {
        $return->loadMissing('items.orderItem');
        $quantities = [];

        foreach ($return->items as $line) {
            $variantId = $line->orderItem?->product_variant_id;

            if ($variantId === null) {
                continue;
            }

            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + $line->quantity;
        }

        return $quantities;
    }

    /**
     * 교환으로 내보낼 조합별 수량.
     *
     * @return array<int, int>
     */
    private function exchangeVariantQuantities(OrderReturn $return): array
    {
        $return->loadMissing('items');
        $quantities = [];

        foreach ($return->items as $line) {
            if ($line->exchange_variant_id === null) {
                continue;
            }

            $quantities[$line->exchange_variant_id] =
                ($quantities[$line->exchange_variant_id] ?? 0) + $line->quantity;
        }

        return $quantities;
    }

    private function assertCarrier(?string $carrier, string $message = '등록되지 않은 택배사입니다.'): string
    {
        if ($carrier === null || ! array_key_exists($carrier, config('shop.shipping.carriers'))) {
            throw new DomainRuleException($message);
        }

        return $carrier;
    }

    private function lockReturn(int $returnId): OrderReturn
    {
        return OrderReturn::query()->with(['items', 'order'])->lockForUpdate()->findOrFail($returnId);
    }

    /**
     * 조합 표시명. CartLibrary 와 같은 규칙이다 — 옵션 정렬 순서대로 ' / ' 로 잇는다.
     *
     * 조합에는 이름 컬럼이 없다. 옵션값에서 매번 만든다 (docs/worklog.md #9).
     */
    private function variantLabel(ProductVariant $variant): string
    {
        $label = $variant->optionValues
            ->sortBy(fn ($v) => $v->option?->sort_order ?? 0)
            ->pluck('value')
            ->implode(' / ');

        return $label !== '' ? $label : $variant->sku;
    }

    /* ------------------------------------------------------------------ 조회 */

    /**
     * 관리자 반품·교환 목록.
     *
     * @param  array{status?: string|null, type?: string|null, keyword?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return OrderReturn::query()
            ->with(['order', 'items.orderItem'])
            ->when(
                ($filters['status'] ?? null) !== null && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when(
                ($filters['type'] ?? null) !== null && $filters['type'] !== '',
                fn ($q) => $q->where('type', $filters['type']),
            )
            ->when($keyword !== '', fn ($q) => $q->whereHas(
                'order',
                fn ($o) => $o
                    ->where('order_no', 'like', "%{$keyword}%")
                    ->orWhere('orderer_name', 'like', "%{$keyword}%")
                    ->orWhere('orderer_phone', 'like', "%{$keyword}%"),
            ))
            ->orderByDesc('id')
            ->paginate(config('shop.per_page.admin'))
            ->withQueryString()
            ->through(fn (OrderReturn $r) => $this->presentRow($r));
    }

    /**
     * 상태별 건수. 관리자가 "지금 처리할 게 몇 건인가" 를 먼저 본다.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $rows = OrderReturn::query()->get(['status'])->countBy(fn (OrderReturn $r) => $r->status->value);

        $counts = [];

        foreach (ReturnStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminDetail(int $returnId): array
    {
        $return = OrderReturn::query()
            ->with([
                'order.items', 'order.shipment', 'items.orderItem',
                'items.exchangeVariant.optionValues.option', 'handledBy',
            ])
            ->findOrFail($returnId);

        return [
            'return' => $this->present($return),
            'order' => [
                'id' => $return->order->id,
                'order_no' => $return->order->order_no,
                'status' => $return->order->status->value,
                'status_label' => $return->order->status->label(),
                'orderer_name' => $return->order->orderer_name,
                'orderer_phone' => $return->order->orderer_phone,
                'receiver_name' => $return->order->receiver_name,
                'postcode' => $return->order->postcode,
                'address1' => $return->order->address1,
                'address2' => $return->order->address2,
                'items_total' => $return->order->items_total,
                'discount_total' => $return->order->discount_total,
                'shipping_fee' => $return->order->shipping_fee,
                'total_amount' => $return->order->total_amount,
                'is_guest' => $return->order->isGuest(),
            ],
            'handled_by' => $return->handledBy?->name,
            'carriers' => $this->carrierOptions(),

            /*
             * 승인 전에는 스냅샷이 비어 있으므로 예상액을 보여준다.
             * 귀책 두 경우를 **서버가 같은 계산식으로** 미리 내놓는다 —
             * 화면에서 배송비 규칙을 다시 구현하면 두 벌이 어긋난다.
             */
            'estimates' => $return->status === ReturnStatus::REQUESTED
                ? [
                    ReturnResponsibility::CUSTOMER->value => $this->previewAmounts(
                        $return->id, ReturnResponsibility::CUSTOMER->value,
                    ),
                    ReturnResponsibility::SELLER->value => $this->previewAmounts(
                        $return->id, ReturnResponsibility::SELLER->value,
                    ),
                ]
                : null,
        ];
    }

    /**
     * 고객의 반품·교환 신청 내역.
     *
     * @return list<array<string, mixed>>
     */
    public function myReturns(int $userId): array
    {
        return OrderReturn::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->with(['order', 'items.orderItem'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (OrderReturn $r) => $this->present($r))
            ->all();
    }

    /** 특정 주문에 걸린 신청들. 주문 상세에 같이 띄운다. */
    public function forOrder(int $orderId): array
    {
        return OrderReturn::query()
            ->where('order_id', $orderId)
            ->with(['items.orderItem'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (OrderReturn $r) => $this->present($r))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(OrderReturn $return): array
    {
        $return->loadMissing(['items.orderItem', 'items.exchangeVariant.optionValues.option', 'order']);

        return [
            'id' => $return->id,
            'order_id' => $return->order_id,
            'order_no' => $return->order?->order_no,
            'type' => $return->type->value,
            'type_label' => $return->type->label(),
            'reason' => $return->reason->value,
            'reason_label' => $return->reason->label(),
            'reason_detail' => $return->reason_detail,
            'responsibility' => $return->responsibility->value,
            'responsibility_label' => $return->responsibility->label(),
            'status' => $return->status->value,
            'status_label' => $return->status->label(),
            'is_final' => $return->status->isFinal(),
            // 접수 상태에서만 고객이 물릴 수 있다.
            'is_cancelable_by_customer' => $return->status === ReturnStatus::REQUESTED,

            'items_refund' => $return->items_refund,
            'coupon_deduction' => $return->coupon_deduction,
            'shipping_deduction' => $return->shipping_deduction,
            'shipping_refund' => $return->shipping_refund,
            'refund_amount' => $return->refund_amount,

            'restock' => $return->restock,
            'reject_reason' => $return->reject_reason,
            'admin_memo' => $return->admin_memo,

            'pickup' => $this->trackingView($return->pickup_carrier, $return->pickup_tracking_no),
            'exchange' => $this->trackingView($return->exchange_carrier, $return->exchange_tracking_no),

            'requested_at' => $return->requested_at?->toDateTimeString(),
            'approved_at' => $return->approved_at?->toDateTimeString(),
            'received_at' => $return->received_at?->toDateTimeString(),
            'completed_at' => $return->completed_at?->toDateTimeString(),
            'rejected_at' => $return->rejected_at?->toDateTimeString(),

            'items' => $return->items->map(fn (OrderReturnItem $line) => [
                'id' => $line->id,
                // 상품명은 주문 항목 스냅샷에서 읽는다. 상품이 바뀌어도 당시 이름이 남는다.
                'product_name' => $line->orderItem?->product_name,
                'variant_name' => $line->orderItem?->variant_name,
                'sku' => $line->orderItem?->sku,
                'unit_price' => $line->orderItem?->unit_price,
                'quantity' => $line->quantity,
                'exchange_variant_name' => $line->exchangeVariant !== null
                    ? $this->variantLabel($line->exchangeVariant)
                    : null,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(OrderReturn $return): array
    {
        $first = $return->items->first();
        $extra = $return->items->count() - 1;

        return [
            'id' => $return->id,
            'order_id' => $return->order_id,
            'order_no' => $return->order?->order_no,
            'orderer_name' => $return->order?->orderer_name,
            'type_label' => $return->type->label(),
            'type' => $return->type->value,
            'reason_label' => $return->reason->label(),
            'responsibility_label' => $return->responsibility->label(),
            'status' => $return->status->value,
            'status_label' => $return->status->label(),
            'refund_amount' => $return->refund_amount,
            'summary' => $first === null
                ? '-'
                : ($first->orderItem?->product_name ?? '삭제된 상품').($extra > 0 ? " 외 {$extra}건" : ''),
            'requested_at' => $return->requested_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function trackingView(?string $carrier, ?string $trackingNo): ?array
    {
        if ($carrier === null) {
            return null;
        }

        $config = config("shop.shipping.carriers.{$carrier}");
        $url = $config['tracking_url'] ?? null;

        return [
            'carrier' => $carrier,
            'carrier_name' => $config['name'] ?? $carrier,
            'tracking_no' => $trackingNo,
            'tracking_url' => ($url !== null && $trackingNo !== null)
                ? str_replace('{no}', $trackingNo, $url)
                : null,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function carrierOptions(): array
    {
        return collect(config('shop.shipping.carriers'))
            ->map(fn (array $c, string $code) => ['value' => $code, 'label' => $c['name']])
            ->values()
            ->all();
    }
}
