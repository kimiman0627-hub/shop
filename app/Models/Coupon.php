<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Coupon\CouponDiscountType;
use App\Enums\Coupon\CouponIssueType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 쿠폰 마스터 (docs/schema-draft.md §8.1).
 *
 * 삭제하지 않는다. is_active = false 로 내린다 — 사용 이력이 매출과 엮인다.
 */
#[Fillable([
    'code', 'name', 'issue_type', 'discount_type', 'discount_value',
    'max_discount_amount', 'min_order_amount',
    'valid_days', 'valid_from', 'valid_until',
    'total_issue_limit', 'per_user_limit', 'is_active',
])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'issue_type' => CouponIssueType::class,
            'discount_type' => CouponDiscountType::class,
            'discount_value' => 'integer',
            'max_discount_amount' => 'integer',
            'min_order_amount' => 'integer',
            'valid_days' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'total_issue_limit' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }

    /**
     * 이 쿠폰을 지금 발급할 수 있는가 (기간·활성 여부만 본다).
     * 1인당·총 발급 한도는 CouponLibrary 가 확인한다.
     */
    public function isIssuable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from !== null && $now->lt($this->valid_from)) {
            return false;
        }

        return ! ($this->valid_until !== null && $now->gt($this->valid_until));
    }

    /**
     * 발급 시점 기준 만료일.
     *
     * valid_days(발급일 기준)와 valid_until(절대 기간)은 다른 축이다.
     * 둘 다 있으면 **더 이른 쪽**을 만료일로 삼는다 (§8.1).
     */
    public function expiryFrom(\DateTimeInterface $issuedAt): Carbon
    {
        $byDays = $this->valid_days !== null
            ? Carbon::instance($issuedAt)->addDays($this->valid_days)
            : null;

        $candidates = array_filter([$byDays, $this->valid_until]);

        if ($candidates === []) {
            // 둘 다 없으면 사실상 무기한이다. 그래도 만료일 컬럼은 비울 수 없으므로 멀리 잡는다.
            return Carbon::instance($issuedAt)->addYears(10);
        }

        return collect($candidates)->min();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
