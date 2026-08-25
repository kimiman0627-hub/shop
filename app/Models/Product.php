<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Product\ProductStatus;
use App\Enums\Product\ShippingFeeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 상품 (docs/schema-draft.md §2.2).
 *
 * 재고를 갖지 않는다. 재고는 ProductVariant 에만 있다.
 */
#[Fillable([
    'category_id', 'name', 'slug', 'summary', 'description',
    'base_price', 'sale_price', 'status',
    'shipping_fee_type', 'shipping_policy_id',
    'thumbnail_path', 'sort_order',
])]
// review_count / rating_sum 은 일부러 Fillable 에서 뺐다.
// 후기 등록·숨김이 관리하는 값이라 폼에서 덮어쓰면 안 된다 (docs/worklog.md #11 과 같은 이유).
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'shipping_fee_type' => ShippingFeeType::class,
            'base_price' => 'integer',
            'sale_price' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function shippingPolicy(): BelongsTo
    {
        return $this->belongsTo(ShippingPolicy::class, 'shipping_policy_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    /**
     * 평균 평점. 합계·건수에서 매번 계산한다 — 평균을 저장하면 오차가 쌓인다.
     *
     * 소수 첫째 자리까지만 쓴다. 4.28 과 4.3 을 구분할 이유가 없다.
     */
    public function ratingAverage(): float
    {
        return $this->review_count > 0
            ? round($this->rating_sum / $this->review_count, 1)
            : 0.0;
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** 노출가. 할인가가 있으면 그것, 없으면 정가. 계산은 이 한 곳에서만 한다. */
    public function displayPrice(): int
    {
        return $this->sale_price ?? $this->base_price;
    }

    public function isDiscounted(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->base_price;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /** 고객에게 노출되는 상품만. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProductStatus::ON_SALE->value,
            ProductStatus::SOLD_OUT->value,
        ]);
    }
}
