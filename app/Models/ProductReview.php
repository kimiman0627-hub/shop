<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Product\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 상품 후기 (docs/schema-draft.md §12.2).
 *
 * 삭제하지 않고 숨긴다. 평점 집계와 구매 이력이 엮여 있다.
 */
#[Fillable([
    'product_id', 'user_id', 'order_item_id', 'rating', 'content', 'status',
    'admin_reply', 'replied_by_admin_id', 'replied_at',
])]
class ProductReview extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'replied_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'replied_by_admin_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::PUBLISHED->value);
    }
}
