<?php

declare(strict_types=1);

namespace App\Enums\Support;

/**
 * 1:1 문의 유형. 값은 대문자 (CLAUDE.md §6.1).
 */
enum InquiryCategory: string
{
    case ORDER = 'ORDER';
    case DELIVERY = 'DELIVERY';
    case PAYMENT = 'PAYMENT';
    case PRODUCT = 'PRODUCT';
    case RETURN_EXCHANGE = 'RETURN_EXCHANGE';
    case ETC = 'ETC';

    public function label(): string
    {
        return match ($this) {
            self::ORDER => '주문',
            self::DELIVERY => '배송',
            self::PAYMENT => '결제',
            self::PRODUCT => '상품',
            self::RETURN_EXCHANGE => '반품·교환',
            self::ETC => '기타',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
