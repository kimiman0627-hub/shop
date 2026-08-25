<?php

declare(strict_types=1);

namespace App\Libraries\Order;

use App\Exceptions\DomainRuleException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\CartOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 장바구니 (docs/schema-draft.md §3).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 * 누구의 장바구니인지는 컨트롤러가 CartOwner 로 만들어 넘긴다.
 *
 * **장바구니는 재고를 예약하지 않는다.** 예약은 주문 생성 시점부터다 (§7).
 * 여기서 하는 재고 확인은 안내용이고, 진짜 판정은 주문 트랜잭션 안에서 다시 한다.
 */
class CartLibrary
{
    /** 한 조합을 담을 수 있는 최대 수량. 오타로 9999 를 담는 사고를 막는다. */
    private const MAX_QUANTITY = 99;

    public function resolve(CartOwner $owner): Cart
    {
        return Cart::query()->firstOrCreate($owner->toAttributes());
    }

    /**
     * 화면에 뿌릴 장바구니 내용. 가격은 항상 지금 값을 계산한다.
     *
     * @return array<string, mixed>
     */
    public function summary(CartOwner $owner): array
    {
        $cart = $this->resolve($owner);

        $items = CartItem::query()
            ->where('cart_id', $cart->id)
            ->with(['variant.product', 'variant.optionValues.option'])
            ->orderBy('id')
            ->get();

        $lines = $items
            ->map(fn (CartItem $item) => $this->lineFor($item->variant, $item->quantity, $item->id))
            ->all();

        return $this->wrap($lines);
    }

    /**
     * **바로구매용 요약.** 장바구니를 거치지 않고 조합 하나로 같은 모양을 만든다.
     *
     * 라인 모양이 장바구니와 같아야 배송비 계산·주문 생성이 그대로 돌아간다.
     * 그래서 lineFor() 를 공유한다 — 두 벌로 나뉘면 반드시 어긋난다.
     *
     * @return array<string, mixed>
     */
    public function directSummary(int $variantId, int $quantity): array
    {
        $variant = ProductVariant::query()
            ->with(['product', 'optionValues.option'])
            ->find($variantId);

        if ($variant === null || $variant->product === null) {
            throw new DomainRuleException('상품 정보를 찾을 수 없습니다.');
        }

        if ($quantity < 1) {
            throw new DomainRuleException('수량은 1개 이상이어야 합니다.');
        }

        return $this->wrap([$this->lineFor($variant, $quantity, null)]);
    }

    /**
     * 라인 하나. 장바구니 항목과 바로구매가 같은 함수를 쓴다.
     *
     * @return array<string, mixed>
     */
    private function lineFor(ProductVariant $variant, int $quantity, ?int $cartItemId): array
    {
        $product = $variant->product;

        $unitPrice = $product->displayPrice() + $variant->additional_price;
        $available = $variant->availableQuantity();

        return [
            // 바로구매에는 장바구니 항목이 없다. 화면에서 수량 변경·삭제를 못 하게 하는 표시이기도 하다.
            'id' => $cartItemId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'variant_id' => $variant->id,
            'option_label' => $variant->optionValues
                ->sortBy(fn ($v) => $v->option?->sort_order ?? 0)
                ->pluck('value')
                ->implode(' / '),
            'thumbnail_url' => $product->thumbnail_path === null
                ? null
                : Storage::disk(config('shop.image.disk'))->url($product->thumbnail_path),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $unitPrice * $quantity,

            // 담아둔 사이에 품절되거나 재고가 줄 수 있다. 화면에서 알려준다.
            'purchasable' => $product->status->isPurchasable() && $variant->is_active && $available > 0,
            'exceeds_stock' => $quantity > $available,
            'available' => max($available, 0),

            // 배송비 계산에 필요한 값. 라인마다 정책이 다를 수 있다 (§6.2).
            'shipping_fee_type' => $product->shipping_fee_type->value,
            'shipping_policy_id' => $product->shipping_policy_id,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function wrap(array $lines): array
    {
        return [
            'items' => $lines,
            'items_total' => array_sum(array_column($lines, 'subtotal')),
            'count' => count($lines),
            'has_issue' => collect($lines)->contains(
                fn (array $l) => ! $l['purchasable'] || $l['exceeds_stock'],
            ),
        ];
    }

    /** 헤더 뱃지용. 전체 요약을 만들지 않고 개수만 센다. */
    public function itemCount(CartOwner $owner): int
    {
        $cart = Cart::query()->where($owner->toAttributes())->first();

        return $cart === null ? 0 : CartItem::query()->where('cart_id', $cart->id)->count();
    }

    public function add(CartOwner $owner, int $variantId, int $quantity): void
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);

        if (! $variant->product->status->isPurchasable() || ! $variant->is_active) {
            throw new DomainRuleException('현재 구매할 수 없는 상품입니다.', 'variant');
        }

        $cart = $this->resolve($owner);

        DB::transaction(function () use ($cart, $variant, $quantity) {
            $item = CartItem::query()->firstOrNew([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
            ]);

            // 이미 담긴 조합이면 수량을 합친다. 같은 조합이 두 줄이 되지 않는다.
            $next = ($item->quantity ?? 0) + $quantity;

            $this->assertQuantitySane($next, $variant);

            $item->fill(['quantity' => $next])->save();
        });
    }

    public function updateQuantity(CartOwner $owner, int $itemId, int $quantity): void
    {
        $item = $this->ownedItem($owner, $itemId);

        $this->assertQuantitySane($quantity, $item->variant);

        $item->update(['quantity' => $quantity]);
    }

    public function remove(CartOwner $owner, int $itemId): void
    {
        $this->ownedItem($owner, $itemId)->delete();
    }

    public function clear(CartOwner $owner): void
    {
        $cart = Cart::query()->where($owner->toAttributes())->first();

        if ($cart !== null) {
            CartItem::query()->where('cart_id', $cart->id)->delete();
        }
    }

    /**
     * 비회원 장바구니를 회원 장바구니로 옮긴다. 로그인 시 호출된다.
     *
     * 같은 조합은 수량을 합치고, 비회원 장바구니 행은 지운다.
     */
    public function mergeGuestIntoUser(string $sessionToken, int $userId): void
    {
        $guest = Cart::query()->where('session_token', $sessionToken)->with('items')->first();

        if ($guest === null || $guest->items->isEmpty()) {
            $guest?->delete();

            return;
        }

        DB::transaction(function () use ($guest, $userId) {
            $mine = Cart::query()->firstOrCreate(['user_id' => $userId]);

            foreach ($guest->items as $item) {
                $existing = CartItem::query()
                    ->where('cart_id', $mine->id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($existing === null) {
                    CartItem::query()->create([
                        'cart_id' => $mine->id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                    ]);

                    continue;
                }

                // 합쳐도 상한을 넘지 않게 자른다. 병합 때문에 저장이 실패하면 안 된다.
                $existing->update([
                    'quantity' => min($existing->quantity + $item->quantity, self::MAX_QUANTITY),
                ]);
            }

            $guest->delete();
        });
    }

    // ------------------------------------------------------------------ 내부

    private function ownedItem(CartOwner $owner, int $itemId): CartItem
    {
        $cart = Cart::query()->where($owner->toAttributes())->first();

        if ($cart === null) {
            throw new DomainRuleException('장바구니가 없습니다.');
        }

        // 남의 장바구니 항목 id 를 보내는 것을 막는다.
        return CartItem::query()
            ->where('cart_id', $cart->id)
            ->with('variant')
            ->findOr($itemId, fn () => throw new DomainRuleException('장바구니에 없는 항목입니다.'));
    }

    private function assertQuantitySane(int $quantity, ProductVariant $variant): void
    {
        if ($quantity < 1) {
            throw new DomainRuleException('수량은 1개 이상이어야 합니다.', 'quantity');
        }

        if ($quantity > self::MAX_QUANTITY) {
            throw new DomainRuleException(
                '한 번에 '.self::MAX_QUANTITY.'개까지 담을 수 있습니다.',
                'quantity',
            );
        }

        $available = $variant->availableQuantity();

        if ($quantity > $available) {
            throw new DomainRuleException(
                $available > 0 ? "재고가 {$available}개 남았습니다." : '품절된 상품입니다.',
                'quantity',
            );
        }
    }
}
