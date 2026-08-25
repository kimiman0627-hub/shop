<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 주문 (docs/schema-draft.md §4.1).
 *
 * 수령인·연락처는 스냅샷이다. 회원 정보에서 조인하지 않는다.
 */
#[Fillable([
    'order_no', 'user_id', 'guest_password', 'status',
    'items_total', 'discount_total', 'shipping_fee', 'total_amount', 'user_coupon_id',
    'orderer_name', 'orderer_phone', 'orderer_email',
    'receiver_name', 'receiver_phone', 'postcode', 'address1', 'address2', 'delivery_memo',
    'ordered_at', 'paid_at', 'canceled_at', 'stock_released_at', 'payment_due_at',
])]
#[Hidden(['guest_password'])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            // 비회원 주문조회 비밀번호. 평문 저장 금지 (schema-draft.md §4.2).
            'guest_password' => 'hashed',
            'items_total' => 'integer',
            'discount_total' => 'integer',
            'shipping_fee' => 'integer',
            'total_amount' => 'integer',
            'ordered_at' => 'datetime',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
            'stock_released_at' => 'datetime',
            'payment_due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function userCoupon(): BelongsTo
    {
        return $this->belongsTo(UserCoupon::class, 'user_coupon_id');
    }

    /** 부분배송을 쓰지 않으므로 주문당 1건이다 (schema-draft.md §6.3). */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /** 반품·교환 신청. 부분 반품이 가능하므로 여러 건이 붙을 수 있다. */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class)->orderByDesc('id');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /** 예약이 아직 살아 있는가. 해제 멱등성을 여기서 판단한다 (§7.4). */
    public function holdsReservation(): bool
    {
        return $this->status->holdsReservation() && $this->stock_released_at === null;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('ordered_at')->orderByDesc('id');
    }
}
