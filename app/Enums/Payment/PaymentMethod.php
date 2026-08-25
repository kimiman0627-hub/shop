<?php

declare(strict_types=1);

namespace App\Enums\Payment;

/**
 * 결제 수단 (docs/schema-draft.md §5.1). 값은 대문자 (CLAUDE.md §6.1).
 */
enum PaymentMethod: string
{
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CARD = 'CARD';
    case VIRTUAL_ACCOUNT = 'VIRTUAL_ACCOUNT';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => '무통장입금',
            self::CARD => '신용카드',
            self::VIRTUAL_ACCOUNT => '가상계좌',
        };
    }

    /** 지금 고객이 고를 수 있는 수단인가. PG 연동 전에는 무통장만 열어둔다. */
    public function isAvailable(): bool
    {
        return $this === self::BANK_TRANSFER;
    }

    /**
     * 사람이 직접 입금을 확인해야 하는 수단인가.
     *
     * 이런 수단은 결제 기한이 분 단위가 아니라 일 단위다.
     * 재고 예약 만료 시간이 달라진다 (config/shop.php).
     */
    public function needsManualConfirm(): bool
    {
        return $this === self::BANK_TRANSFER;
    }

    /**
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => $m->isAvailable()));
    }
}
