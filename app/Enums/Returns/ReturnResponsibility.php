<?php

declare(strict_types=1);

namespace App\Enums\Returns;

/**
 * 귀책. 반품 배송비를 누가 부담하는지를 정한다. 값은 대문자 (CLAUDE.md §6.1).
 */
enum ReturnResponsibility: string
{
    case CUSTOMER = 'CUSTOMER';
    case SELLER = 'SELLER';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => '고객 귀책',
            self::SELLER => '판매자 귀책',
        };
    }

    /** 고객이 반품 배송비를 부담하는가 → 환불액에서 차감된다. */
    public function customerPaysReturnShipping(): bool
    {
        return $this === self::CUSTOMER;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
