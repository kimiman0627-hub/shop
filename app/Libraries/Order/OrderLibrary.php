<?php

declare(strict_types=1);

namespace App\Libraries\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\DomainRuleException;
use App\Libraries\Shipping\ShippingPolicyLibrary;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Support\CartOwner;
use App\Support\PaymentDueCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 주문 (docs/schema-draft.md §4, §7).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class OrderLibrary
{
    public function __construct(
        private readonly CartLibrary $carts,
        private readonly CouponLibrary $coupons,
        private readonly StockLibrary $stock,
        private readonly ShippingPolicyLibrary $shipping,
        // PaymentLibrary 를 주입하면 순환 참조가 된다(그쪽이 OrderLibrary 를 쓴다).
        // 기한 계산만 필요하므로 순수 계산기를 쓴다 (CLAUDE.md §4.2).
        private readonly PaymentDueCalculator $paymentDue,
        private readonly ShipmentLibrary $shipments,
    ) {}

    /**
     * 장바구니로 주문을 만든다.
     *
     * **재고 예약·쿠폰 사용·주문 생성이 한 트랜잭션이다.**
     * 따로 처리하면 예약만 되고 주문이 없거나, 쿠폰이 이중 사용된다 (§8.3).
     *
     * @param  array<string, mixed>  $form  수령인·주문자 정보
     */
    public function createFromCart(CartOwner $owner, array $form, int $userId): Order
    {
        $summary = $this->carts->summary($owner);

        if ($summary['items'] === []) {
            throw new DomainRuleException('장바구니가 비어 있습니다.');
        }

        // 주문이 확정되면 장바구니를 비운다.
        return $this->create($summary, $form, $userId, clearCartOf: $owner);
    }

    /**
     * **바로구매.** 장바구니를 거치지 않고 조합 하나만 주문한다.
     *
     * 장바구니에 담아둔 다른 상품은 **건드리지 않는다** — 바로구매인데
     * 장바구니 내용까지 같이 결제되면 사고다. 그래서 비울 장바구니를 넘기지 않는다.
     *
     * 요약은 `CartLibrary::directSummary()` 가 장바구니와 같은 모양으로 만들어준다.
     *
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $form
     */
    public function createDirect(array $summary, array $form, int $userId): Order
    {
        if ($summary['items'] === []) {
            throw new DomainRuleException('주문할 상품이 없습니다.');
        }

        return $this->create($summary, $form, $userId, clearCartOf: null);
    }

    /**
     * 주문 생성 본체. 장바구니 주문과 바로구매가 **같은 경로**를 쓴다.
     *
     * 재고 잠금·쿠폰 확정·배송비 계산·예약이 한 트랜잭션 안에 있다.
     * 경로를 두 벌로 나누면 이 순서가 반드시 어긋난다.
     *
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $form
     * @param  CartOwner|null  $clearCartOf  주문 후 비울 장바구니. 바로구매는 null.
     */
    private function create(array $summary, array $form, int $userId, ?CartOwner $clearCartOf): Order
    {
        // 비회원은 쿠폰을 쓸 수 없다 (§8.3).
        $userCouponId = $userId === null ? null : ($form['user_coupon_id'] ?? null);

        $method = PaymentMethod::from($form['payment_method']);

        if (! $method->isAvailable()) {
            throw new DomainRuleException('사용할 수 없는 결제 수단입니다.', 'payment_method');
        }

        $dueAt = $this->paymentDue->for($method);

        return DB::transaction(function () use ($summary, $form, $userId, $userCouponId, $clearCartOf, $dueAt) {
            $quantities = [];

            foreach ($summary['items'] as $line) {
                $quantities[$line['variant_id']] = $line['quantity'];
            }

            // 1) 조합을 id 오름차순으로 잠근다 — 데드락 방지 (§7.6).
            $locked = $this->stock->lockVariants(array_keys($quantities));

            // 2) 잠근 상태에서 가격을 다시 읽는다.
            //    장바구니 요약은 잠금 전 값이라, 그 사이 가격이 바뀌었을 수 있다.
            $lines = $this->buildLines($summary['items'], $locked);

            $itemsTotal = array_sum(array_column($lines, 'subtotal'));

            // 3) 쿠폰 확정. 같은 트랜잭션 안이라 이중 사용이 불가능하다.
            $discount = 0;
            $userCoupon = null;

            if ($userCouponId !== null) {
                $userCoupon = $this->coupons->markUsed((int) $userCouponId, $userId);
                $discount = $this->coupons->discountFor($userCoupon, $itemsTotal);

                if ($discount <= 0) {
                    throw new DomainRuleException('이 주문에 사용할 수 없는 쿠폰입니다.', 'user_coupon_id');
                }
            }

            // 4) 배송비는 장바구니·상세와 같은 계산기를 쓴다 (§6.2).
            $shippingFee = $this->shipping->calculateFee($lines);

            $order = Order::query()->create([
                'order_no' => $this->nextOrderNo(),
                'user_id' => $userId,
                'status' => OrderStatus::PENDING,
                'items_total' => $itemsTotal,
                'discount_total' => $discount,
                'shipping_fee' => $shippingFee,
                'total_amount' => max(0, $itemsTotal - $discount) + $shippingFee,
                'user_coupon_id' => $userCoupon?->id,
                'orderer_name' => $form['orderer_name'],
                'orderer_phone' => $form['orderer_phone'],
                'orderer_email' => $form['orderer_email'] ?? null,
                'receiver_name' => $form['receiver_name'],
                'receiver_phone' => $form['receiver_phone'],
                'postcode' => $form['postcode'],
                'address1' => $form['address1'],
                'address2' => $form['address2'] ?? null,
                'delivery_memo' => $form['delivery_memo'] ?? null,
                'ordered_at' => now(),
                // 결제 기한을 주문 시점에 확정한다. 수단마다 다르므로 컬럼으로 둔다.
                'payment_due_at' => $dueAt,
            ]);

            foreach ($lines as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['variant_id'],
                    // 아래가 스냅샷이다. 상품이 바뀌어도 주문서는 불변이다 (§4.3).
                    'product_name' => $line['product_name'],
                    'variant_name' => $line['option_label'] !== '' ? $line['option_label'] : null,
                    'sku' => $line['sku'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                    'shipping_fee_type' => $line['shipping_fee_type'],
                ]);
            }

            // 5) 예약. 여기서 재고가 모자라면 전부 롤백된다.
            $this->stock->reserve($quantities, $locked, $order->id);

            // 6) 쿠폰에 주문을 연결한다.
            $userCoupon?->forceFill(['order_id' => $order->id])->save();

            // 7) 장바구니를 비운다. 주문이 확정된 뒤여야 하고, 바로구매는 비우지 않는다.
            if ($clearCartOf !== null) {
                $this->carts->clear($clearCartOf);
            }

            return $order;
        });
    }

    /**
     * 주문 취소. 예약을 풀고 쿠폰을 되살린다.
     *
     * @param  bool  $byAdmin  관리자 취소는 상품준비중까지 허용된다 (OrderStatus 참고)
     */
    public function cancel(int $orderId, string $memo = '고객 취소', bool $byAdmin = false): void
    {
        DB::transaction(function () use ($orderId, $memo, $byAdmin) {
            $order = Order::query()->with('items')->lockForUpdate()->findOrFail($orderId);

            $allowed = $byAdmin
                ? $order->status->isCancelableByAdmin()
                : $order->status->isCancelableByCustomer();

            if (! $allowed) {
                throw new DomainRuleException(
                    $order->status === OrderStatus::SHIPPING || $order->status === OrderStatus::DELIVERED
                        ? "{$order->status->label()} 상태입니다. 이미 출고된 주문은 취소가 아니라 반품·환불로 처리해야 합니다."
                        : "{$order->status->label()} 상태에서는 취소할 수 없습니다.",
                );
            }

            if ($order->status === OrderStatus::PENDING) {
                // 아직 예약만 잡힌 상태 — 실물은 건드리지 않는다.
                $this->stock->release($order, $memo);
            } else {
                // 이미 판매 확정된 주문 — 실물을 되돌린다.
                $this->stock->restock($order, $memo);
            }

            $order->forceFill([
                'status' => OrderStatus::CANCELED,
                'canceled_at' => now(),
            ])->save();

            // 만료된 쿠폰은 되살리지 않는다 (§8.3).
            if ($order->user_coupon_id !== null) {
                $this->coupons->restore($order->user_coupon_id);
            }

            // 입금대기 중인 결제 요청도 함께 닫는다.
            // 남겨두면 관리자 무통장처리 목록에 유령 건이 계속 뜬다.
            Payment::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatus::READY->value)
                ->update([
                    'status' => PaymentStatus::CANCELED->value,
                    'canceled_at' => now(),
                    'memo' => $memo,
                ]);
        });
    }

    /**
     * 결제 완료 처리. 예약을 실물 차감으로 바꾼다.
     */
    public function markPaid(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::query()->with('items')->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== OrderStatus::PENDING) {
                throw new DomainRuleException('결제대기 상태의 주문만 결제할 수 있습니다.');
            }

            $this->stock->confirmSale($order);

            $order->forceFill([
                'status' => OrderStatus::PAID,
                'paid_at' => now(),
                // 예약이 실물로 바뀌었으므로 해제 대상이 아니다.
                'stock_released_at' => now(),
            ])->save();

            return $order;
        });
    }

    /**
     * 만료된 미결제 주문을 정리한다. 스케줄러가 부른다 (§7.3).
     *
     * 이게 없으면 결제창을 닫은 주문이 재고를 영원히 물고 있는다.
     *
     * @return int 정리한 주문 수
     */
    public function expireStaleOrders(): int
    {
        // 기한은 주문마다 다르다 — 결제 수단별로 payment_due_at 에 확정 저장된다.
        // 고정 TTL 을 쓰면 무통장입금 주문이 전부 취소된다.
        $stale = Order::query()
            ->where('status', OrderStatus::PENDING->value)
            ->whereNull('stock_released_at')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->pluck('id');

        $count = 0;

        foreach ($stale as $orderId) {
            try {
                $this->cancel($orderId, '예약 만료 자동취소');
                $count++;
            } catch (DomainRuleException) {
                continue;
            }
        }

        return $count;
    }

    // ------------------------------------------------------------------ 조회

    /**
     * @return array<string, mixed>
     */
    public function getDetail(int $orderId): array
    {
        $order = Order::query()->with(['items', 'shipment'])->findOrFail($orderId);

        return $this->present($order);
    }

    public function findByOrderNo(string $orderNo): ?Order
    {
        return Order::query()->with(['items', 'shipment'])->where('order_no', $orderNo)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'is_cancelable' => $order->status->isCancelableByCustomer(),

            /*
             * 출고 뒤에는 취소가 아니라 반품·교환이다.
             * 두 버튼이 동시에 보이는 상태는 없다 — 상태값이 겹치지 않는다.
             */
            'is_returnable' => in_array($order->status, [OrderStatus::SHIPPING, OrderStatus::DELIVERED], true),
            'items_total' => $order->items_total,
            'discount_total' => $order->discount_total,
            'shipping_fee' => $order->shipping_fee,
            'total_amount' => $order->total_amount,
            'orderer_name' => $order->orderer_name,
            'orderer_phone' => $order->orderer_phone,
            'orderer_email' => $order->orderer_email,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'postcode' => $order->postcode,
            'address1' => $order->address1,
            'address2' => $order->address2,
            'delivery_memo' => $order->delivery_memo,
            'ordered_at' => $order->ordered_at->toDateTimeString(),
            'paid_at' => $order->paid_at?->toDateTimeString(),
            'is_guest' => $order->isGuest(),

            // 출고 전에는 null 이다. 송장이 없는데 '배송정보' 를 보여주면 혼란만 준다.
            'shipment' => $this->shipments->customerView($order->shipment),

            // 전부 스냅샷 컬럼에서 읽는다. FK 조인이 아니다 (§4.3).
            'items' => $order->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'product_name' => $i->product_name,
                'variant_name' => $i->variant_name,
                'sku' => $i->sku,
                'unit_price' => $i->unit_price,
                'quantity' => $i->quantity,
                'subtotal' => $i->subtotal,
            ])->all(),
        ];
    }

    /**
     * 관리자 주문 목록. **모든 상태**를 다룬다 — 배송관리와 달리 미결제·취소도 보인다.
     *
     * @param  array{status?: string|null, keyword?: string|null, from?: string|null, to?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return Order::query()
            ->with(['items', 'shipment'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('ordered_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('ordered_at', '<=', $d))
            ->when($keyword !== '', fn ($q) => $q->where(
                fn ($sub) => $sub
                    ->whereLike('order_no', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereLike('orderer_name', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereLike('orderer_phone', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereLike('receiver_name', '%'.$keyword.'%', caseSensitive: false),
            ))
            ->ordered()
            ->paginate(config('shop.per_page.order'))
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'is_guest' => $o->isGuest(),
                'orderer_name' => $o->orderer_name,
                'orderer_phone' => $o->orderer_phone,
                'item_summary' => $this->itemSummary($o),
                'total_amount' => $o->total_amount,
                'ordered_at' => $o->ordered_at->toDateTimeString(),
                'paid_at' => $o->paid_at?->toDateTimeString(),
                'payment_due_at' => $o->payment_due_at?->toDateTimeString(),
                // 미결제인데 기한이 지났으면 스케줄러가 곧 취소한다. 눈에 띄게 표시한다.
                'overdue' => $o->status === OrderStatus::PENDING
                    && $o->payment_due_at?->isPast() === true,
                'tracking_no' => $o->shipment?->tracking_no,
            ]);
    }

    /**
     * 관리자 주문 상세. 결제·배송·재고 이력까지 한 화면에서 본다.
     *
     * 문의가 들어왔을 때 화면 여러 개를 오가지 않아도 되게 한 곳에 모은다.
     *
     * @return array<string, mixed>
     */
    public function getAdminDetail(int $orderId): array
    {
        $order = Order::query()
            ->with([
                'items', 'shipment.shippedBy:id,name',
                'user:id,name,email',
                'userCoupon.coupon:id,name',
            ])
            ->findOrFail($orderId);

        return [
            ...$this->present($order),

            'is_cancelable_by_admin' => $order->status->isCancelableByAdmin(),
            'canceled_at' => $order->canceled_at?->toDateTimeString(),
            'payment_due_at' => $order->payment_due_at?->toDateTimeString(),
            'stock_released_at' => $order->stock_released_at?->toDateTimeString(),

            'customer' => $order->user === null ? null : [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ],

            'coupon' => $order->userCoupon === null ? null : [
                'name' => $order->userCoupon->coupon?->name,
                'discount' => $order->discount_total,
            ],

            'payments' => Payment::query()
                ->where('order_id', $order->id)
                ->with('confirmedBy:id,name')
                ->orderBy('id')
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'method_label' => $p->method->label(),
                    'status' => $p->status->value,
                    'status_label' => $p->status->label(),
                    'amount' => $p->amount,
                    'account_label' => $p->bank_name !== null ? $p->accountLabel() : null,
                    'depositor_name' => $p->depositor_name,
                    'requested_at' => $p->requested_at->toDateTimeString(),
                    'paid_at' => $p->paid_at?->toDateTimeString(),
                    'confirmed_by' => $p->confirmedBy?->name,
                    'memo' => $p->memo,
                ])->all(),

            // 재고가 어떻게 움직였는지. 재고 문의 대응에 필요하다.
            'stock_movements' => StockMovement::query()
                ->where('order_id', $order->id)
                ->with('variant:id,sku')
                ->orderBy('id')
                ->get()
                ->map(fn (StockMovement $m) => [
                    'type' => $m->type->value,
                    'type_label' => $m->type->label(),
                    'sku' => $m->variant?->sku,
                    'stock_delta' => $m->stock_delta,
                    'reserved_delta' => $m->reserved_delta,
                    'created_at' => $m->created_at?->toDateTimeString(),
                ])->all(),
        ];
    }

    /** 목록에서 주문서를 다 펴지 않고 한 줄로 요약한다. */
    private function itemSummary(Order $order): string
    {
        $first = $order->items->first();

        if ($first === null) {
            return '-';
        }

        $more = $order->items->count() - 1;

        return $more > 0 ? "{$first->product_name} 외 {$more}건" : $first->product_name;
    }

    public function getUserOrders(int $userId): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with(['items', 'shipment'])
            ->ordered()
            ->paginate(config('shop.per_page.order'))
            ->through(fn (Order $o) => $this->present($o));
    }

    // ------------------------------------------------------------------ 내부

    /**
     * 잠근 조합에서 가격을 다시 읽어 주문 라인을 만든다.
     *
     * @param  list<array<string, mixed>>  $cartLines
     * @param  Collection<int, ProductVariant>  $locked
     * @return list<array<string, mixed>>
     */
    private function buildLines(array $cartLines, $locked): array
    {
        $lines = [];

        foreach ($cartLines as $line) {
            /** @var ProductVariant|null $variant */
            $variant = $locked->get($line['variant_id']);

            if ($variant === null) {
                throw new DomainRuleException("판매하지 않는 상품이 있습니다: {$line['product_name']}");
            }

            $product = $variant->product;

            if (! $product->status->isPurchasable()) {
                throw new DomainRuleException("현재 구매할 수 없는 상품이 있습니다: {$product->name}");
            }

            $unitPrice = $product->displayPrice() + $variant->additional_price;

            $lines[] = [
                'variant_id' => $variant->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'option_label' => $line['option_label'],
                'sku' => $variant->sku,
                'unit_price' => $unitPrice,
                'quantity' => $line['quantity'],
                'subtotal' => $unitPrice * $line['quantity'],
                'shipping_fee_type' => $product->shipping_fee_type->value,
                'shipping_policy_id' => $product->shipping_policy_id,
            ];
        }

        return $lines;
    }

    /**
     * 주문번호. 접두사 + 날짜 + 랜덤.
     *
     * DB 시퀀스에 의존하지 않는다 (CLAUDE.md §5.1). 충돌하면 다시 뽑는다.
     */
    private function nextOrderNo(): string
    {
        $prefix = config('shop.order_no_prefix').now()->format('Ymd');

        do {
            $candidate = $prefix.Str::upper(Str::random(6));
        } while (Order::query()->where('order_no', $candidate)->exists());

        return $candidate;
    }
}
