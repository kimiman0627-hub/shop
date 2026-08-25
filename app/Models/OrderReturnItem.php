<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 반품·교환 대상 항목. 부분 반품이므로 주문 항목의 일부 수량만 담을 수 있다.
 */
#[Fillable(['order_return_id', 'order_item_id', 'quantity', 'exchange_variant_id'])]
class OrderReturnItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function exchangeVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'exchange_variant_id');
    }
}
