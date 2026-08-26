<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 상품별 일자별 집계 한 행. `ProductStatLibrary` 만 쓴다.
 *
 * `view_count`/`cart_count` 는 되돌릴 수 없는 누적값이고
 * `order_count`/`quantity`/`revenue` 는 재계산 대상이다 — 마이그레이션 주석 참고.
 */
#[Fillable([
    'stat_date', 'product_id',
    'view_count', 'cart_count',
    'order_count', 'quantity', 'revenue',
])]
class DailyProductStat extends Model
{
    protected function casts(): array
    {
        return ['stat_date' => 'date'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
