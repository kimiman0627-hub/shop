<?php

declare(strict_types=1);

namespace App\Libraries\Product;

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * 상품 추천.
 *
 * 알고리즘은 **함께 구매(item-to-item)** 한 가지다. 내가 산 상품을 산 다른 사람들이
 * 같이 산 상품을 빈도순으로 세는 것뿐이고, 부족하면 같은 카테고리 인기 →
 * 전체 인기 순으로 채운다. 추천이 비어 있는 화면이 나오지 않게 하려는 것이다.
 *
 * **집계는 PHP 컬렉션에서 한다.** `GROUP BY` / `selectRaw` 를 쓰지 않는다 (CLAUDE.md §5.1).
 * 주문 항목이 수만 건을 넘어가면 이 방식이 버겁다 —
 * 그때는 집계 결과를 별도 테이블이나 캐시에 적재하고 여기서는 읽기만 하도록 바꾼다.
 * (지금 캐시를 넣지 않은 이유: 데이터가 바뀌어도 화면이 안 변해 개발 중 혼란만 준다.)
 */
class RecommendationLibrary
{
    public function __construct(private readonly ProductLibrary $products) {}

    /**
     * 회원 맞춤 추천.
     *
     * 1) 내가 산 상품과 **함께 팔린** 상품
     * 2) 모자라면 내가 산 상품의 **카테고리 인기** 상품
     * 3) 그래도 모자라면 **전체 인기** 상품
     *
     * 이미 산 상품은 뺀다 — 방금 산 걸 다시 추천하면 추천처럼 보이지 않는다.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, int $limit = 4): array
    {
        $purchased = $this->purchasedProductIds($userId);

        if ($purchased === []) {
            return $this->popular($limit);
        }

        $ids = $this->coPurchasedWith($purchased, $purchased)->take($limit)->all();

        if (count($ids) < $limit) {
            $ids = $this->fillWithCategoryPopular($ids, $purchased, $limit);
        }

        if (count($ids) < $limit) {
            $ids = $this->fill($ids, $this->popularIds($limit * 3), array_merge($purchased, $ids), $limit);
        }

        return $this->products->cardsFor($ids);
    }

    /**
     * 이 상품을 산 사람들이 함께 산 상품. 상품 상세에 붙인다.
     *
     * @return list<array<string, mixed>>
     */
    public function relatedTo(int $productId, int $limit = 4): array
    {
        $ids = $this->coPurchasedWith([$productId], [$productId])->take($limit)->all();

        if (count($ids) < $limit) {
            // 함께 팔린 이력이 없으면 같은 카테고리에서 채운다.
            $categoryId = Product::query()->whereKey($productId)->value('category_id');

            if ($categoryId !== null) {
                $sameCategory = $this->purchasableFrom(
                    Product::query()
                        ->visible()
                        ->where('category_id', $categoryId)
                        ->whereKeyNot($productId)
                        ->ordered()
                        ->limit($limit * 4)
                        ->pluck('id')
                        ->all(),
                );

                $ids = $this->fill($ids, $sameCategory, [$productId], $limit);
            }
        }

        if (count($ids) < $limit) {
            // 카테고리에 상품이 몇 개 없으면 여기서도 못 채운다. 인기 상품으로 마저 채운다 —
            // 한 칸짜리 추천 줄은 화면이 덜 만들어진 것처럼 보인다.
            $ids = $this->fill($ids, $this->popularIds($limit * 3), [$productId], $limit);
        }

        return $this->products->cardsFor($ids);
    }

    /**
     * 많이 팔린 순.
     *
     * @param  list<int>  $excludeIds
     * @return list<array<string, mixed>>
     */
    public function popular(int $limit = 4, array $excludeIds = []): array
    {
        $ids = collect($this->popularIds($limit + count($excludeIds)))
            ->reject(fn (int $id) => in_array($id, $excludeIds, true))
            ->take($limit)
            ->values()
            ->all();

        if (count($ids) < $limit) {
            // 판매 이력이 아예 없는 초기 상태 — 진열 순서대로라도 채운다.
            $ids = $this->fill($ids, $this->newestIds($limit * 2), $excludeIds, $limit);
        }

        return $this->products->cardsFor($ids);
    }

    /**
     * 최근 본 상품. 순서는 넘겨받은 그대로 유지한다.
     *
     * 쿠키에서 읽은 id 를 그대로 받는다 — **라이브러리는 Request 를 모른다** (CLAUDE.md §4.2).
     *
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>
     */
    public function recentlyViewed(array $productIds, int $limit = 6): array
    {
        return $this->products->cardsFor(array_slice($productIds, 0, $limit));
    }

    /* ------------------------------------------------------------------ 내부 */

    /**
     * 이 회원이 실제로 구매한 상품 id.
     *
     * @return list<int>
     */
    private function purchasedProductIds(int $userId): array
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $userId)
                ->whereIn('status', OrderStatus::saleValues()))
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 씨앗 상품들과 **같은 주문에 담겼던** 다른 상품을 빈도순으로.
     *
     * @param  list<int>  $seedIds  기준 상품
     * @param  list<int>  $excludeIds  결과에서 뺄 상품
     * @return Collection<int, int>
     */
    private function coPurchasedWith(array $seedIds, array $excludeIds): Collection
    {
        if ($seedIds === []) {
            return collect();
        }

        // 씨앗 상품이 팔린 주문들
        $orderIds = OrderItem::query()
            ->whereIn('product_id', $seedIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', OrderStatus::saleValues()))
            ->pluck('order_id')
            ->unique();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $counts = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->whereNotNull('product_id')
            ->get(['product_id'])
            ->countBy('product_id');

        return $this->rankByCount($counts, $excludeIds);
    }

    /**
     * 내가 산 상품의 카테고리에서 인기 상품을 뽑아 채운다.
     *
     * @param  list<int>  $ids  지금까지 모인 결과
     * @param  list<int>  $purchased
     * @return list<int>
     */
    private function fillWithCategoryPopular(array $ids, array $purchased, int $limit): array
    {
        $categoryIds = Product::query()
            ->whereIn('id', $purchased)
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->all();

        if ($categoryIds === []) {
            return $ids;
        }

        $candidates = $this->purchasableFrom(
            Product::query()
                ->visible()
                ->whereIn('category_id', $categoryIds)
                ->ordered()
                ->limit($limit * 5)
                ->pluck('id')
                ->all(),
        );

        return $this->fill($ids, $candidates, array_merge($purchased, $ids), $limit);
    }

    /**
     * 판매 수량 기준 인기 상품 id.
     *
     * @return list<int>
     */
    private function popularIds(int $limit): array
    {
        $counts = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereIn('status', OrderStatus::saleValues()))
            ->whereNotNull('product_id')
            ->get(['product_id', 'quantity'])
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => (int) $rows->sum('quantity'));

        return $this->rankByCount($counts, [])->take($limit)->all();
    }

    /**
     * @return list<int>
     */
    private function newestIds(int $limit): array
    {
        return $this->purchasableFrom(
            Product::query()->visible()->ordered()->limit($limit * 3)->pluck('id')->all(),
        );
    }

    /**
     * **지금 살 수 있는 상품만** 남긴다. 순서는 넘긴 그대로.
     *
     * 추천은 목록과 기준이 다르다. 상품 목록은 품절 상품도 '품절' 배지를 달고
     * 보여주는 게 맞지만, 추천은 클릭해서 살 수 있어야 추천이다.
     *
     * 판매가능 = 실물 − 예약이라 컬럼 하나로 못 거른다 → 합계를 얹어 PHP 에서 판단한다
     * (CLAUDE.md §5.1, whereRaw 금지).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function purchasableFrom(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $buyable = Product::query()
            ->visible()
            ->whereIn('id', $ids)
            ->withSum('variants as stock_total', 'stock_quantity')
            ->withSum('variants as reserved_total', 'reserved_quantity')
            ->get(['id', 'status'])
            ->filter(fn (Product $p) => $p->status->isPurchasable()
                && ((int) ($p->stock_total ?? 0) - (int) ($p->reserved_total ?? 0)) > 0)
            ->pluck('id')
            ->all();

        return array_values(array_filter($ids, fn (int $id) => in_array($id, $buyable, true)));
    }

    /**
     * 빈도 맵을 '판매 가능한 상품만, 많은 순' 으로 정리한다.
     *
     * @param  Collection<int|string, int>  $counts  product_id => 횟수
     * @param  list<int>  $excludeIds
     * @return Collection<int, int>
     */
    private function rankByCount(Collection $counts, array $excludeIds): Collection
    {
        $ranked = $counts
            ->reject(fn (int $count, $productId) => in_array((int) $productId, $excludeIds, true))
            ->sortDesc()
            ->keys()
            ->map(fn ($id) => (int) $id);

        if ($ranked->isEmpty()) {
            return collect();
        }

        $buyable = $this->purchasableFrom($ranked->all());

        return $ranked->filter(fn (int $id) => in_array($id, $buyable, true))->values();
    }

    /**
     * 후보에서 중복·제외를 걸러 목표 개수까지 채운다.
     *
     * @param  list<int>  $ids
     * @param  list<int>  $candidates
     * @param  list<int>  $excludeIds
     * @return list<int>
     */
    private function fill(array $ids, array $candidates, array $excludeIds, int $limit): array
    {
        foreach ($candidates as $candidate) {
            if (count($ids) >= $limit) {
                break;
            }

            if (in_array($candidate, $ids, true) || in_array($candidate, $excludeIds, true)) {
                continue;
            }

            $ids[] = $candidate;
        }

        return $ids;
    }

    /**
     * 최근 주문한 상품 — '다시 구매하기' 용.
     *
     * @return list<array<string, mixed>>
     */
    public function reorderCandidates(int $userId, int $limit = 4): array
    {
        $ids = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $userId)
                ->whereIn('status', OrderStatus::saleValues()))
            ->whereNotNull('product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->orderByDesc('orders.ordered_at')
            ->pluck('order_items.product_id')
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        return $this->products->cardsFor($ids);
    }

    /** 이 회원이 구매 이력을 가지고 있는가. 화면 문구를 고르는 데 쓴다. */
    public function hasPurchaseHistory(int $userId): bool
    {
        return Order::query()
            ->where('user_id', $userId)
            ->whereIn('status', OrderStatus::saleValues())
            ->exists();
    }
}
