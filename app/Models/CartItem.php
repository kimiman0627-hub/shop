<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 장바구니 항목.
 *
 * 가격을 저장하지 않는다. 상품 가격이 바뀌면 장바구니에도 바로 반영되어야 한다.
 */
#[Fillable(['cart_id', 'product_variant_id', 'quantity'])]
class CartItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
