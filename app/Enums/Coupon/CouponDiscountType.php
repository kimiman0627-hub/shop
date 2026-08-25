<?php

declare(strict_types=1);

namespace App\Enums\Coupon;

/**
 * 쿠폰 할인 방식 (docs/schema-draft.md §8.1). 값은 대문자 (CLAUDE.md §6.1).
 */
enum CouponDiscountType: string
{
    case FIXED = 'FIXED';
    case PERCENT = 'PERCENT';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => '정액 할인',
            self::PERCENT => '정률 할인',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::FIXED => '원',
            self::PERCENT => '%',
        };
    }

    /**
     * 할인 금액을 계산한다.
     *
     * 원 단위 정수만 다룬다. 정률은 **버림**한다 — 올림하면 가맹점이 손해를 보고,
     * 반올림은 합계가 어긋날 때 설명하기 어렵다.
     */
    public function discountFor(int $itemsTotal, int $value, ?int $maxDiscount): int
    {
        $discount = match ($this) {
            self::FIXED => $value,
            self::PERCENT => intdiv($itemsTotal * $value, 100),
        };

        // 정률 쿠폰의 상한. 없으면 고가 상품에서 손실이 무한정 커진다 (§8.1).
        if ($this === self::PERCENT && $maxDiscount !== null) {
            $discount = min($discount, $maxDiscount);
        }

        // 할인이 상품 합계를 넘지 않는다. 배송비는 할인 대상이 아니다 (§8.3).
        return max(0, min($discount, $itemsTotal));
    }
}
