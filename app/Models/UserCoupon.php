<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 발급된 쿠폰 (docs/schema-draft.md §8.2).
 *
 * expires_at 은 발급 시점에 확정 저장된다. 마스터 설정이 바뀌어도
 * 이미 발급된 쿠폰의 만료일은 변하지 않는다.
 */
#[Fillable(['coupon_id', 'user_id', 'issued_at', 'expires_at', 'used_at', 'order_id'])]
class UserCoupon extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** 지금 주문에 쓸 수 있는가. 최소 주문금액은 별도로 확인한다. */
    public function isUsable(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired() && ($this->coupon?->is_active ?? false);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
