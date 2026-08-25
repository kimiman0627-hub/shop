<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Product\ProductImageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ⚠ 컬럼을 추가하면 여기에도 넣는다. 빠지면 조용히 저장이 무시된다 (docs/worklog.md #8).
#[Fillable(['product_id', 'path', 'alt', 'sort_order', 'is_primary', 'type'])]
class ProductImage extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'type' => ProductImageType::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
