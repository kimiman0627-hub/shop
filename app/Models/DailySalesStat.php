<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 일별 매출 집계 한 행. 원본이 아니라 파생 데이터다 —
 * `SalesAggregateLibrary` 만 쓰고, 사람이 손으로 고치지 않는다.
 */
#[Fillable([
    'stat_date', 'order_count', 'items_total', 'discount_total',
    'shipping_fee', 'revenue', 'refunded',
])]
class DailySalesStat extends Model
{
    protected function casts(): array
    {
        return ['stat_date' => 'date'];
    }
}
