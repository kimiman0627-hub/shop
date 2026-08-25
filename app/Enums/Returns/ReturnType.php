<?php

declare(strict_types=1);

namespace App\Enums\Returns;

/**
 * 반품 유형. 값은 대문자 (CLAUDE.md §6.1).
 */
enum ReturnType: string
{
    case RETURN = 'RETURN';
    case EXCHANGE = 'EXCHANGE';

    public function label(): string
    {
        return match ($this) {
            self::RETURN => '반품',
            self::EXCHANGE => '교환',
        };
    }

    /** 돈을 돌려주는가. 교환은 물건을 바꿀 뿐 정산이 없다. */
    public function needsRefund(): bool
    {
        return $this === self::RETURN;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
