<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 재고 변동 이력 (docs/schema-draft.md §7.7). 갱신되지 않는다.
 */
#[Fillable([
    'product_variant_id', 'type',
    'stock_delta', 'reserved_delta', 'stock_after', 'reserved_after',
    'order_id', 'admin_id', 'memo', 'created_at',
])]
class StockMovement extends Model
{
    /** 이력은 수정되지 않으므로 updated_at 을 두지 않는다. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'stock_delta' => 'integer',
            'reserved_delta' => 'integer',
            'stock_after' => 'integer',
            'reserved_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
