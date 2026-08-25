<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Returns\ReturnReason;
use App\Enums\Returns\ReturnResponsibility;
use App\Enums\Returns\ReturnStatus;
use App\Enums\Returns\ReturnType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 반품·교환 신청.
 *
 * 금액 컬럼은 승인 시점 스냅샷이다. 정책이 바뀌어도 소급되지 않는다.
 *
 * ⚠ Fillable 에 컬럼을 빠뜨리면 저장이 **조용히** 무시된다 (docs/worklog.md #8).
 *   컬럼을 추가하면 여기도 같이 고칠 것.
 */
#[Fillable([
    'order_id', 'type', 'reason', 'reason_detail', 'responsibility', 'status',
    'items_refund', 'coupon_deduction', 'shipping_deduction', 'shipping_refund', 'refund_amount',
    'pickup_carrier', 'pickup_tracking_no', 'exchange_carrier', 'exchange_tracking_no',
    'restock', 'reject_reason', 'admin_memo', 'handled_by_admin_id',
    'requested_at', 'approved_at', 'received_at', 'completed_at', 'rejected_at',
])]
class OrderReturn extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ReturnType::class,
            'reason' => ReturnReason::class,
            'responsibility' => ReturnResponsibility::class,
            'status' => ReturnStatus::class,
            'items_refund' => 'integer',
            'coupon_deduction' => 'integer',
            'shipping_deduction' => 'integer',
            'shipping_refund' => 'integer',
            'refund_amount' => 'integer',
            'restock' => 'boolean',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class)->orderBy('id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }

    /** 아직 항목 수량을 점유하고 있는 건. 중복 신청 차단에 쓴다. */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->where('status', '!=', ReturnStatus::REJECTED->value);
    }
}
