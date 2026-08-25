<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 판매 단위(SKU) = 옵션 조합 하나 (docs/schema-draft.md §2.3).
 *
 * 재고는 이 모델에만 있다. 실물과 예약을 분리하며,
 * 판매가능 = stock_quantity - reserved_quantity 다 (§7).
 */
#[Fillable(['product_id', 'sku', 'additional_price', 'stock_quantity', 'reserved_quantity', 'is_active'])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'additional_price' => 'integer',
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_values',
            'product_variant_id',
            'product_option_value_id',
        );
    }

    /**
     * 판매 가능한 수량. 예약분은 이미 다른 사람이 결제 중이므로 뺀다.
     *
     * 이 값을 컬럼으로 저장하지 않는다 — 두 컬럼에서 항상 계산한다.
     */
    public function availableQuantity(): int
    {
        return $this->stock_quantity - $this->reserved_quantity;
    }

    public function isPurchasable(int $quantity = 1): bool
    {
        return $this->is_active && $this->availableQuantity() >= $quantity;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
