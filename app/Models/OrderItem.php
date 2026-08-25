<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Product\ShippingFeeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 주문 항목 (docs/schema-draft.md §4.3).
 *
 * product_name / unit_price 등이 원본이다. FK 는 "지금도 존재하면 링크를
 * 걸어주는" 용도일 뿐이므로, 화면에 찍는 값을 FK 로 조인하지 않는다.
 */
#[Fillable([
    'order_id', 'product_id', 'product_variant_id',
    'product_name', 'variant_name', 'sku',
    'unit_price', 'quantity', 'subtotal', 'shipping_fee_type',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'shipping_fee_type' => ShippingFeeType::class,
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    /** 후기는 주문 항목당 1건이지만, 존재 여부를 whereDoesntHave 로 묻기 위해 HasMany 로 둔다. */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }
}
