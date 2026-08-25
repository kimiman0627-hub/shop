<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 결제 시도 (docs/schema-draft.md §5.1).
 *
 * 주문당 여러 행이다. 계좌 정보는 안내 시점의 스냅샷이다.
 */
#[Fillable([
    'order_id', 'method', 'status', 'amount',
    'bank_name', 'account_number', 'holder_name', 'depositor_name',
    'pg_provider', 'pg_transaction_id', 'raw_response',
    'confirmed_by_admin_id', 'memo',
    'requested_at', 'paid_at', 'canceled_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'raw_response' => 'array',
            'requested_at' => 'datetime',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'confirmed_by_admin_id');
    }

    /** 안내용 계좌 문자열. 스냅샷 컬럼에서 읽는다. */
    public function accountLabel(): string
    {
        return "{$this->bank_name} {$this->account_number} ({$this->holder_name})";
    }

    public function scopeInFlight(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::READY->value);
    }
}
