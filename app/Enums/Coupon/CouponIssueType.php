<?php

declare(strict_types=1);

namespace App\Enums\Coupon;

/**
 * 쿠폰 발급 방식 (docs/schema-draft.md §8.1). 값은 대문자 (CLAUDE.md §6.1).
 */
enum CouponIssueType: string
{
    case SIGNUP = 'SIGNUP';
    case MANUAL = 'MANUAL';
    case CODE = 'CODE';

    public function label(): string
    {
        return match ($this) {
            self::SIGNUP => '가입 시 자동발급',
            self::MANUAL => '관리자 지정발급',
            self::CODE => '코드 입력',
        };
    }

    /** 고객이 코드를 입력해 직접 받는 방식인가. */
    public function needsCode(): bool
    {
        return $this === self::CODE;
    }
}
