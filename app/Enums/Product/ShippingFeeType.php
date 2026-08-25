<?php

declare(strict_types=1);

namespace App\Enums\Product;

/**
 * 상품별 배송비 유형 (docs/schema-draft.md §6). 값은 대문자 (CLAUDE.md §6.1).
 */
enum ShippingFeeType: string
{
    case FREE = 'FREE';
    case PAID = 'PAID';

    public function label(): string
    {
        return match ($this) {
            self::FREE => '무료배송',
            self::PAID => '배송비 부과',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }
}
