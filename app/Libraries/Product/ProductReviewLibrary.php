<?php

declare(strict_types=1);

namespace App\Libraries\Product;

use App\Enums\Order\OrderStatus;
use App\Enums\Product\ReviewStatus;
use App\Exceptions\DomainRuleException;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 상품 후기 · 평점 (docs/schema-draft.md §12.2).
 *
 * **구매한 사람만 쓴다.** 근거는 `order_items` 다 — 주문 항목 하나당 후기 하나이고,
 * 그 주문이 배송완료여야 한다. 사지 않은 사람의 후기를 받으면 평점이 광고판이 된다.
 *
 * 평점은 `products.rating_sum` / `review_count` 에 **비정규화**해둔다.
 * 목록 20개마다 후기를 집계하면 화면이 느려진다. 대신 후기 상태가 바뀔 때마다
 * 여기서 정확히 가감한다 — 그 책임이 이 클래스 밖으로 나가면 숫자가 어긋난다.
 */
class ProductReviewLibrary
{
    /** 후기를 쓸 수 있는 주문 상태. 물건을 받아봐야 후기를 쓴다. */
    private const WRITABLE_ORDER_STATUSES = [OrderStatus::DELIVERED];

    /* ------------------------------------------------------------------ 작성 */

    /**
     * 이 회원이 아직 후기를 안 쓴, 쓸 수 있는 주문 항목.
     *
     * @return list<array<string, mixed>>
     */
    public function writableItems(int $userId, ?int $productId = null): array
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $userId)
                ->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, self::WRITABLE_ORDER_STATUSES)))
            ->whereNotNull('product_id')
            ->when($productId !== null, fn ($q) => $q->where('product_id', $productId))
            // 이미 쓴 항목은 뺀다. order_item_id 가 unique 라 DB 도 막지만,
            // 화면에 '작성' 버튼이 뜬 뒤 실패하면 사용자 입장에서 이상하다.
            ->whereDoesntHave('reviews')
            ->with(['order:id,order_no,ordered_at'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (OrderItem $item) => [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'order_no' => $item->order->order_no,
                'ordered_at' => $item->order->ordered_at->toDateString(),
            ])
            ->all();
    }

    /**
     * 후기 등록.
     *
     * @param  array{order_item_id: int, rating: int, content: string}  $form
     */
    public function create(int $userId, array $form): ProductReview
    {
        return DB::transaction(function () use ($userId, $form) {
            $item = OrderItem::query()
                ->with('order')
                ->lockForUpdate()
                ->find($form['order_item_id']);

            if ($item === null || $item->order === null) {
                throw new DomainRuleException('주문 정보를 찾을 수 없습니다.');
            }

            // 남의 주문으로 후기를 쓰지 못하게 한다.
            if ($item->order->user_id !== $userId) {
                throw new DomainRuleException('본인이 구매한 상품만 후기를 쓸 수 있습니다.');
            }

            if (! in_array($item->order->status, self::WRITABLE_ORDER_STATUSES, true)) {
                throw new DomainRuleException(
                    "배송완료된 주문만 후기를 쓸 수 있습니다. (현재: {$item->order->status->label()})",
                );
            }

            if ($item->product_id === null) {
                throw new DomainRuleException('삭제된 상품에는 후기를 쓸 수 없습니다.');
            }

            if (ProductReview::query()->where('order_item_id', $item->id)->exists()) {
                throw new DomainRuleException('이미 후기를 작성한 주문입니다.');
            }

            $rating = (int) $form['rating'];

            if ($rating < 1 || $rating > 5) {
                throw new DomainRuleException('별점은 1~5점 사이여야 합니다.', 'rating');
            }

            $review = ProductReview::query()->create([
                'product_id' => $item->product_id,
                'user_id' => $userId,
                'order_item_id' => $item->id,
                'rating' => $rating,
                'content' => trim($form['content']),
                'status' => ReviewStatus::PUBLISHED,
            ]);

            $this->applyRating($item->product_id, $rating, +1);

            return $review;
        });
    }

    /** 작성자가 지운다. 실제로는 숨김이다 — 평점 이력을 남긴다. */
    public function deleteByOwner(int $reviewId, int $userId): void
    {
        DB::transaction(function () use ($reviewId, $userId) {
            $review = ProductReview::query()->lockForUpdate()->findOrFail($reviewId);

            if ($review->user_id !== $userId) {
                throw new DomainRuleException('본인이 쓴 후기만 삭제할 수 있습니다.');
            }

            $this->setStatus($review, ReviewStatus::HIDDEN);
        });
    }

    /* ------------------------------------------------------------------ 관리 */

    /** 관리자가 노출/숨김을 바꾼다. 평점 집계도 같이 움직인다. */
    public function changeStatus(int $reviewId, string $status): ProductReview
    {
        return DB::transaction(function () use ($reviewId, $status) {
            $review = ProductReview::query()->lockForUpdate()->findOrFail($reviewId);

            $this->setStatus($review, ReviewStatus::from($status));

            return $review->fresh();
        });
    }

    public function reply(int $reviewId, int $adminId, string $reply): ProductReview
    {
        $review = ProductReview::query()->findOrFail($reviewId);

        $review->forceFill([
            'admin_reply' => trim($reply) !== '' ? trim($reply) : null,
            'replied_by_admin_id' => $adminId,
            'replied_at' => now(),
        ])->save();

        return $review;
    }

    /**
     * 상태를 바꾸면서 평점 합계를 정확히 가감한다.
     *
     * **같은 상태로 다시 바꾸면 아무것도 하지 않는다.** 안 그러면 숨김을 두 번 눌렀을 때
     * 평점이 두 번 빠진다 — 재고 예약 해제와 같은 멱등성 문제다 (schema-draft.md §7.4).
     */
    private function setStatus(ProductReview $review, ReviewStatus $status): void
    {
        if ($review->status === $status) {
            return;
        }

        $review->forceFill(['status' => $status])->save();

        $this->applyRating(
            $review->product_id,
            $review->rating,
            $status->countsForRating() ? +1 : -1,
        );
    }

    /**
     * 상품의 평점 합계·건수를 갱신한다.
     *
     * @param  int  $direction  +1 이면 더하고, -1 이면 뺀다
     */
    private function applyRating(int $productId, int $rating, int $direction): void
    {
        $product = Product::query()->lockForUpdate()->find($productId);

        if ($product === null) {
            return;
        }

        // max(0, ...) 로 막는다. 어딘가 어긋나도 음수 평점이 화면에 나가면 안 된다.
        $product->forceFill([
            'review_count' => max(0, $product->review_count + $direction),
            'rating_sum' => max(0, $product->rating_sum + ($rating * $direction)),
        ])->save();
    }

    /* ------------------------------------------------------------------ 조회 */

    /**
     * 상품 상세에 붙는 후기 목록.
     *
     * @return array<string, mixed>
     */
    public function forProduct(int $productId, int $perPage = 5): array
    {
        $reviews = ProductReview::query()
            ->where('product_id', $productId)
            ->published()
            ->with(['user:id,name', 'orderItem:id,variant_name'])
            ->orderByDesc('id')
            ->paginate($perPage, pageName: 'review_page')
            ->withQueryString()
            ->through(fn (ProductReview $r) => $this->present($r));

        return [
            'list' => $reviews,
            'summary' => $this->summary($productId),
        ];
    }

    /**
     * 평점 요약 — 평균, 건수, 별점별 분포.
     *
     * 분포는 막대로 그려야 하니 5~1점을 **빠짐없이** 채운다.
     * 0건인 별점을 건너뛰면 막대가 밀려서 다른 점수처럼 보인다.
     *
     * @return array<string, mixed>
     */
    public function summary(int $productId): array
    {
        $product = Product::query()->findOrFail($productId);

        $counts = ProductReview::query()
            ->where('product_id', $productId)
            ->published()
            ->get(['rating'])
            ->countBy('rating');

        $distribution = [];

        foreach ([5, 4, 3, 2, 1] as $score) {
            $count = (int) ($counts[$score] ?? 0);

            $distribution[] = [
                'rating' => $score,
                'count' => $count,
                'percent' => $product->review_count > 0
                    ? (int) round($count / $product->review_count * 100)
                    : 0,
            ];
        }

        return [
            'average' => $product->ratingAverage(),
            'count' => $product->review_count,
            'distribution' => $distribution,
        ];
    }

    /**
     * 관리자 후기 목록.
     *
     * @param  array{status?: string|null, rating?: int|string|null, keyword?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return ProductReview::query()
            ->with(['user:id,name', 'product:id,name', 'orderItem:id,variant_name', 'repliedBy:id,name'])
            ->when(
                ($filters['status'] ?? null) !== null && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when(
                ($filters['rating'] ?? null) !== null && $filters['rating'] !== '',
                fn ($q) => $q->where('rating', (int) $filters['rating']),
            )
            ->when($keyword !== '', fn ($q) => $q
                ->where(fn ($w) => $w
                    ->whereLike('content', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereHas('product', fn ($p) => $p
                        ->whereLike('name', '%'.$keyword.'%', caseSensitive: false))))
            ->orderByDesc('id')
            ->paginate(config('shop.per_page.admin'))
            ->withQueryString()
            ->through(fn (ProductReview $r) => [
                ...$this->present($r, maskName: false),
                'product_name' => $r->product?->name,
                'product_id' => $r->product_id,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'replied_by' => $r->repliedBy?->name,
            ]);
    }

    /** 상태별 건수. 관리자 목록 상단 필터에 쓴다. @return array<string, int> */
    public function statusCounts(): array
    {
        $rows = ProductReview::query()->get(['status'])->countBy(fn (ProductReview $r) => $r->status->value);

        $counts = [];

        foreach (ReviewStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ProductReview $review, bool $maskName = true): array
    {
        return [
            'id' => $review->id,
            'user_id' => $review->user_id,
            // 고객 화면에는 이름을 가린다. 김서연 → 김*연
            'author' => $maskName ? $this->maskName($review->user?->name) : $review->user?->name,
            'rating' => $review->rating,
            'content' => $review->content,
            'variant_name' => $review->orderItem?->variant_name,
            'admin_reply' => $review->admin_reply,
            'replied_at' => $review->replied_at?->toDateString(),
            'created_at' => $review->created_at->toDateString(),
        ];
    }

    /**
     * 이름 가리기. 후기는 공개 글이라 실명을 그대로 노출하지 않는다.
     *
     * 한 글자면 가릴 게 없고, 두 글자면 가운데가 없으니 뒤를 가린다.
     */
    private function maskName(?string $name): string
    {
        $name = trim((string) $name);
        $length = mb_strlen($name);

        return match (true) {
            $length === 0 => '알 수 없음',
            $length === 1 => $name,
            $length === 2 => mb_substr($name, 0, 1).'*',
            default => mb_substr($name, 0, 1)
                .str_repeat('*', $length - 2)
                .mb_substr($name, -1),
        };
    }
}
