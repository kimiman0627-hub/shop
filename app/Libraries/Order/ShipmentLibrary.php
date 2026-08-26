<?php

declare(strict_types=1);

namespace App\Libraries\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\Shipment;
use App\Support\LocalTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 배송 (docs/schema-draft.md §6.3).
 *
 * 주문 상태와 배송 상태를 **함께** 움직인다. 한쪽만 바뀌면 화면마다 다른 말을 하게 된다.
 *
 *   결제완료(PAID) → 상품준비중(PREPARING) → 배송중(SHIPPING) → 배송완료(DELIVERED)
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class ShipmentLibrary
{
    /**
     * 배송 대상 주문 목록.
     *
     * @param  array{status?: string|null, keyword?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = $filters['status'] ?? '';

        return Order::query()
            // 결제 이후 단계만 배송 대상이다. 미결제·취소 주문은 보이지 않는다.
            ->whereIn('status', [
                OrderStatus::PAID->value,
                OrderStatus::PREPARING->value,
                OrderStatus::SHIPPING->value,
                OrderStatus::DELIVERED->value,
            ])
            ->with(['items', 'shipment.shippedBy:id,name'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($keyword !== '', fn ($q) => $q->where(
                fn ($sub) => $sub
                    ->whereLike('order_no', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereLike('receiver_name', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereHas('shipment', fn ($s) => $s
                        ->whereLike('tracking_no', '%'.$keyword.'%', caseSensitive: false)),
            ))
            // 오래 기다린 주문이 위로 온다.
            ->orderBy('paid_at')
            ->paginate(config('shop.per_page.order'))
            ->withQueryString()
            ->through(fn (Order $o) => $this->adminRow($o));
    }

    /**
     * 송장 등록 + 출고 처리.
     *
     * 송장을 찍는 순간 물건이 나간 것으로 본다 — 주문도 배송중이 된다.
     */
    public function ship(int $orderId, array $data, int $adminId): Shipment
    {
        return DB::transaction(function () use ($orderId, $data, $adminId) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [OrderStatus::PAID, OrderStatus::PREPARING], true)) {
                throw new DomainRuleException(
                    "{$order->status->label()} 상태에서는 출고할 수 없습니다.",
                );
            }

            $shipment = Shipment::query()->firstOrNew(['order_id' => $order->id]);

            $shipment->fill([
                'order_id' => $order->id,
                'carrier' => $data['carrier'],
                'tracking_no' => $data['tracking_no'] ?: null,
                'status' => ShipmentStatus::SHIPPING,
                'shipped_at' => now(),
                'shipped_by_admin_id' => $adminId,
                'memo' => $data['memo'] ?? null,
            ])->save();

            $order->forceFill(['status' => OrderStatus::SHIPPING])->save();

            return $shipment;
        });
    }

    /**
     * 상품준비중으로 전환. 출고 전 단계 표시용이다.
     */
    public function markPreparing(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== OrderStatus::PAID) {
                throw new DomainRuleException(
                    "결제완료 상태의 주문만 준비중으로 바꿀 수 있습니다. (현재: {$order->status->label()})",
                );
            }

            $order->forceFill(['status' => OrderStatus::PREPARING])->save();

            Shipment::query()->firstOrCreate(
                ['order_id' => $order->id],
                ['status' => ShipmentStatus::READY],
            );
        });
    }

    public function markDelivered(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::query()->with('shipment')->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== OrderStatus::SHIPPING) {
                throw new DomainRuleException(
                    "배송중인 주문만 배송완료로 바꿀 수 있습니다. (현재: {$order->status->label()})",
                );
            }

            $order->forceFill(['status' => OrderStatus::DELIVERED])->save();

            $order->shipment?->forceFill([
                'status' => ShipmentStatus::DELIVERED,
                'delivered_at' => now(),
            ])->save();
        });
    }

    /**
     * 잘못 찍은 송장을 되돌린다.
     *
     * 배송완료된 건은 되돌리지 않는다 — 고객이 이미 받은 상태다.
     */
    public function revertShipping(int $orderId, string $memo = ''): void
    {
        DB::transaction(function () use ($orderId, $memo) {
            $order = Order::query()->with('shipment')->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== OrderStatus::SHIPPING) {
                throw new DomainRuleException(
                    "배송중인 주문만 되돌릴 수 있습니다. (현재: {$order->status->label()})",
                );
            }

            $order->forceFill(['status' => OrderStatus::PREPARING])->save();

            $order->shipment?->forceFill([
                'status' => ShipmentStatus::READY,
                'tracking_no' => null,
                'shipped_at' => null,
                'memo' => $memo !== '' ? $memo : null,
            ])->save();
        });
    }

    /**
     * 고객 화면에 내릴 배송 정보.
     *
     * @return array<string, mixed>|null
     */
    public function customerView(?Shipment $shipment): ?array
    {
        if ($shipment === null || ! $shipment->status->isDispatched()) {
            return null;
        }

        return [
            'status' => $shipment->status->value,
            'status_label' => $shipment->status->label(),
            'carrier_name' => $shipment->carrierName(),
            'tracking_no' => $shipment->tracking_no,
            'tracking_url' => $shipment->trackingUrl(),
            'shipped_at' => LocalTime::dateTime($shipment->shipped_at),
            'delivered_at' => LocalTime::dateTime($shipment->delivered_at),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function carrierOptions(): array
    {
        $options = [];

        foreach (config('shop.shipping.carriers', []) as $code => $carrier) {
            $options[] = ['value' => $code, 'label' => $carrier['name']];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminRow(Order $order): array
    {
        $shipment = $order->shipment;

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'address' => "({$order->postcode}) {$order->address1} ".($order->address2 ?? ''),
            'delivery_memo' => $order->delivery_memo,
            'paid_at' => LocalTime::dateTime($order->paid_at),
            'item_summary' => $this->itemSummary($order),

            'carrier' => $shipment?->carrier,
            'carrier_name' => $shipment?->carrierName(),
            'tracking_no' => $shipment?->tracking_no,
            'tracking_url' => $shipment?->trackingUrl(),
            'shipped_at' => LocalTime::dateTime($shipment?->shipped_at),
            'delivered_at' => LocalTime::dateTime($shipment?->delivered_at),
            'shipped_by' => $shipment?->shippedBy?->name,
            'memo' => $shipment?->memo,
        ];
    }

    /** 주문서 전체를 펴지 않고 한 줄로 요약한다. 목록에서 상품을 알아볼 정도면 된다. */
    private function itemSummary(Order $order): string
    {
        $first = $order->items->first();

        if ($first === null) {
            return '-';
        }

        $name = $first->product_name;
        $more = $order->items->count() - 1;

        return $more > 0 ? "{$name} 외 {$more}건" : $name;
    }
}
