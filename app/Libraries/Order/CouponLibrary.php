<?php

declare(strict_types=1);

namespace App\Libraries\Order;

use App\Enums\Coupon\CouponDiscountType;
use App\Enums\Coupon\CouponIssueType;
use App\Exceptions\DomainRuleException;
use App\Models\Coupon;
use App\Models\UserCoupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 쿠폰 (docs/schema-draft.md §8).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class CouponLibrary
{
    // ------------------------------------------------------------- 관리자

    public function getAdminList(): LengthAwarePaginator
    {
        return Coupon::query()
            ->withCount([
                'userCoupons as issued_count',
                'userCoupons as used_count' => fn ($q) => $q->whereNotNull('used_at'),
            ])
            ->orderByDesc('id')
            ->paginate(config('shop.per_page.admin'))
            ->through(fn (Coupon $c) => $this->adminRow($c));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(int $couponId): array
    {
        $coupon = Coupon::query()->findOrFail($couponId);

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'issue_type' => $coupon->issue_type->value,
            'discount_type' => $coupon->discount_type->value,
            'discount_value' => $coupon->discount_value,
            'max_discount_amount' => $coupon->max_discount_amount,
            'min_order_amount' => $coupon->min_order_amount,
            'valid_days' => $coupon->valid_days,
            'valid_from' => $coupon->valid_from?->toDateString(),
            'valid_until' => $coupon->valid_until?->toDateString(),
            'total_issue_limit' => $coupon->total_issue_limit,
            'per_user_limit' => $coupon->per_user_limit,
            'is_active' => $coupon->is_active,
        ];
    }

    /**
     * 발급 내역.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function issuedList(int $couponId): Collection
    {
        return UserCoupon::query()
            ->where('coupon_id', $couponId)
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (UserCoupon $uc) => [
                'id' => $uc->id,
                'user_name' => $uc->user?->name,
                'user_email' => $uc->user?->email,
                'issued_at' => $uc->issued_at->toDateTimeString(),
                'expires_at' => $uc->expires_at->toDateTimeString(),
                'used_at' => $uc->used_at?->toDateTimeString(),
                'expired' => $uc->isExpired(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        $this->assertSane($data);

        return Coupon::query()->create($this->attributes($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $couponId, array $data): Coupon
    {
        $coupon = Coupon::query()->findOrFail($couponId);

        $this->assertSane($data);

        // 발급이 시작된 쿠폰의 발급 방식은 바꾸지 않는다.
        // SIGNUP 을 CODE 로 바꾸면 이미 받은 사람과 규칙이 어긋난다.
        if ($coupon->userCoupons()->exists()
            && $coupon->issue_type->value !== $data['issue_type']) {
            throw new DomainRuleException(
                '이미 발급된 쿠폰의 발급 방식은 변경할 수 없습니다.',
                'issue_type',
            );
        }

        $coupon->update($this->attributes($data));

        return $coupon;
    }

    /**
     * 쿠폰은 삭제하지 않는다. 내리기만 한다 (§8.2).
     */
    public function deactivate(int $couponId): void
    {
        Coupon::query()->findOrFail($couponId)->update(['is_active' => false]);
    }

    // -------------------------------------------------------------- 발급

    /**
     * 회원가입 시 자동 발급. 조건에 맞는 SIGNUP 쿠폰을 전부 준다.
     *
     * @return int 발급한 장수
     */
    public function issueSignupCoupons(int $userId): int
    {
        $coupons = Coupon::query()
            ->active()
            ->where('issue_type', CouponIssueType::SIGNUP->value)
            ->get();

        $issued = 0;

        foreach ($coupons as $coupon) {
            try {
                $this->issue($coupon, $userId);
                $issued++;
            } catch (DomainRuleException) {
                // 한도 초과 등으로 못 받는 쿠폰이 있어도 가입은 계속되어야 한다.
                continue;
            }
        }

        return $issued;
    }

    /**
     * 코드 입력으로 받기.
     */
    public function redeemByCode(int $userId, string $code): UserCoupon
    {
        $coupon = Coupon::query()
            ->where('code', Str::upper(trim($code)))
            ->first();

        if ($coupon === null || $coupon->issue_type !== CouponIssueType::CODE) {
            throw new DomainRuleException('사용할 수 없는 쿠폰 코드입니다.', 'code');
        }

        return $this->issue($coupon, $userId);
    }

    /**
     * 실제 발급. 한도와 기간을 여기서 판정한다.
     */
    public function issue(Coupon $coupon, int $userId): UserCoupon
    {
        if (! $coupon->isIssuable()) {
            throw new DomainRuleException('발급 기간이 아니거나 중지된 쿠폰입니다.', 'code');
        }

        return DB::transaction(function () use ($coupon, $userId) {
            $mine = UserCoupon::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($mine >= $coupon->per_user_limit) {
                throw new DomainRuleException('이미 발급받은 쿠폰입니다.', 'code');
            }

            if ($coupon->total_issue_limit !== null) {
                $total = UserCoupon::query()->where('coupon_id', $coupon->id)->count();

                if ($total >= $coupon->total_issue_limit) {
                    throw new DomainRuleException('발급이 마감된 쿠폰입니다.', 'code');
                }
            }

            $issuedAt = now();

            return UserCoupon::query()->create([
                'coupon_id' => $coupon->id,
                'user_id' => $userId,
                'issued_at' => $issuedAt,
                // 만료일을 여기서 확정한다. 마스터가 바뀌어도 이 값은 안 변한다 (§8.2).
                'expires_at' => $coupon->expiryFrom($issuedAt),
            ]);
        });
    }

    // -------------------------------------------------------------- 사용

    /**
     * 이 회원이 지금 주문에 쓸 수 있는 쿠폰들.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function usableFor(int $userId, int $itemsTotal): Collection
    {
        return UserCoupon::query()
            ->where('user_id', $userId)
            ->usable()
            ->with('coupon')
            ->orderBy('expires_at')
            ->get()
            ->filter(fn (UserCoupon $uc) => $uc->coupon?->is_active ?? false)
            ->map(fn (UserCoupon $uc) => [
                'id' => $uc->id,
                'name' => $uc->coupon->name,
                'expires_at' => $uc->expires_at->toDateString(),
                'min_order_amount' => $uc->coupon->min_order_amount,
                'discount' => $this->discountFor($uc, $itemsTotal),
                // 최소 주문금액 미달이면 목록에는 보이되 선택은 막는다.
                'applicable' => $itemsTotal >= $uc->coupon->min_order_amount,
            ])
            ->values();
    }

    /**
     * 할인 금액. 만료됐거나 내려간 쿠폰이면 0 이다.
     *
     * **used_at 은 보지 않는다.** 주문 생성은 markUsed() 로 사용 처리를 먼저 하고
     * 할인액을 계산하는데, 여기서 isUsable() 을 보면 방금 자기가 찍은 used_at
     * 때문에 항상 0 이 나온다 — 쿠폰이 붙은 주문이 전부 막힌다 (docs/worklog.md #13).
     *
     * 사용 가능 여부는 markUsed() 와 usableFor() 가 판단한다. 여기는 계산만 한다.
     */
    public function discountFor(UserCoupon $userCoupon, int $itemsTotal): int
    {
        $coupon = $userCoupon->coupon;

        if ($coupon === null || $userCoupon->isExpired() || ! $coupon->is_active) {
            return 0;
        }

        if ($itemsTotal < $coupon->min_order_amount) {
            return 0;
        }

        return $coupon->discount_type->discountFor(
            $itemsTotal,
            $coupon->discount_value,
            $coupon->max_discount_amount,
        );
    }

    /**
     * 주문에 쿠폰을 확정한다.
     *
     * **주문 생성 트랜잭션 안에서 호출한다.** 따로 처리하면 이중 사용이 난다 (§8.3).
     */
    public function markUsed(int $userCouponId, int $userId): UserCoupon
    {
        $userCoupon = UserCoupon::query()
            ->where('user_id', $userId)
            ->with('coupon')
            ->findOr($userCouponId, fn () => throw new DomainRuleException('보유하지 않은 쿠폰입니다.', 'coupon'));

        if (! $userCoupon->isUsable()) {
            throw new DomainRuleException('이미 사용했거나 만료된 쿠폰입니다.', 'coupon');
        }

        $userCoupon->update(['used_at' => now()]);

        return $userCoupon;
    }

    /**
     * 주문 취소 시 쿠폰을 되살린다.
     *
     * 이미 만료됐으면 되살리지 않는다 — 만료된 쿠폰이 살아나면 안 된다 (§8.3).
     */
    public function restore(int $userCouponId): bool
    {
        $userCoupon = UserCoupon::query()->find($userCouponId);

        if ($userCoupon === null || $userCoupon->isExpired()) {
            return false;
        }

        $userCoupon->update(['used_at' => null]);

        return true;
    }

    /**
     * 고객의 쿠폰함.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function myCoupons(int $userId): Collection
    {
        return UserCoupon::query()
            ->where('user_id', $userId)
            ->with('coupon')
            ->orderBy('expires_at')
            ->get()
            // 미사용을 위로 올린다. CASE WHEN 은 DB 별 문법 차이가 있어 쓰지 않는다 (CLAUDE.md §5.1).
            ->sortBy(fn (UserCoupon $uc) => $uc->isUsed() ? 1 : 0)
            ->values()
            ->map(fn (UserCoupon $uc) => [
                'id' => $uc->id,
                'name' => $uc->coupon?->name,
                'discount_label' => $this->discountLabel($uc->coupon),
                'min_order_amount' => $uc->coupon?->min_order_amount ?? 0,
                'expires_at' => $uc->expires_at->toDateString(),
                'used' => $uc->isUsed(),
                'expired' => $uc->isExpired(),
                'usable' => $uc->isUsable(),
            ]);
    }

    // ------------------------------------------------------------------ 내부

    private function discountLabel(?Coupon $coupon): string
    {
        if ($coupon === null) {
            return '-';
        }

        $value = number_format($coupon->discount_value).$coupon->discount_type->unit();

        if ($coupon->discount_type === CouponDiscountType::PERCENT
            && $coupon->max_discount_amount !== null) {
            return $value.' (최대 '.number_format($coupon->max_discount_amount).'원)';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertSane(array $data): void
    {
        $issueType = CouponIssueType::from($data['issue_type']);
        $discountType = CouponDiscountType::from($data['discount_type']);

        // 코드 입력형인데 코드가 없으면 아무도 못 받는다.
        if ($issueType->needsCode() && trim((string) ($data['code'] ?? '')) === '') {
            throw new DomainRuleException('코드 입력형 쿠폰은 코드가 필요합니다.', 'code');
        }

        if ($discountType === CouponDiscountType::PERCENT) {
            if ($data['discount_value'] < 1 || $data['discount_value'] > 100) {
                throw new DomainRuleException('정률 할인은 1~100% 사이여야 합니다.', 'discount_value');
            }
        }

        // 기간을 아무것도 안 정하면 사실상 무기한 쿠폰이 된다. 실수를 막는다.
        $hasPeriod = ($data['valid_days'] ?? null) !== null
            || ($data['valid_until'] ?? null) !== null;

        if (! $hasPeriod) {
            throw new DomainRuleException(
                '유효기간을 정하세요. 발급일 기준 일수 또는 종료일 중 하나는 필요합니다.',
                'valid_days',
            );
        }

        if (($data['valid_from'] ?? null) !== null && ($data['valid_until'] ?? null) !== null
            && $data['valid_from'] > $data['valid_until']) {
            throw new DomainRuleException('발급 시작일이 종료일보다 늦습니다.', 'valid_from');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $issueType = CouponIssueType::from($data['issue_type']);
        $discountType = CouponDiscountType::from($data['discount_type']);

        return [
            // 코드는 대문자로 통일한다 (CLAUDE.md §6.1).
            'code' => $issueType->needsCode() ? Str::upper(trim((string) $data['code'])) : null,
            'name' => $data['name'],
            'issue_type' => $issueType,
            'discount_type' => $discountType,
            'discount_value' => (int) $data['discount_value'],
            // 정액 쿠폰에 상한은 의미가 없다.
            'max_discount_amount' => $discountType === CouponDiscountType::PERCENT
                ? ($data['max_discount_amount'] ?? null)
                : null,
            'min_order_amount' => (int) ($data['min_order_amount'] ?? 0),
            'valid_days' => $data['valid_days'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'total_issue_limit' => $data['total_issue_limit'] ?? null,
            'per_user_limit' => (int) ($data['per_user_limit'] ?? 1),
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminRow(Coupon $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'issue_type' => $c->issue_type->value,
            'issue_type_label' => $c->issue_type->label(),
            'discount_label' => $this->discountLabel($c),
            'min_order_amount' => $c->min_order_amount,
            'valid_days' => $c->valid_days,
            'valid_until' => $c->valid_until?->toDateString(),
            'issued_count' => $c->issued_count,
            'used_count' => $c->used_count,
            'total_issue_limit' => $c->total_issue_limit,
            'is_active' => $c->is_active,
        ];
    }
}
