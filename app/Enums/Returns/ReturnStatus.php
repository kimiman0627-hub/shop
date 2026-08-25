<?php

declare(strict_types=1);

namespace App\Enums\Returns;

/**
 * 반품·교환 진행 상태. 값은 대문자 (CLAUDE.md §6.1).
 *
 *   REQUESTED → APPROVED → PICKING → RECEIVED → COMPLETED
 *             ↘ REJECTED
 */
enum ReturnStatus: string
{
    case REQUESTED = 'REQUESTED';
    case APPROVED = 'APPROVED';
    case PICKING = 'PICKING';
    case RECEIVED = 'RECEIVED';
    case COMPLETED = 'COMPLETED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => '접수',
            self::APPROVED => '승인',
            self::PICKING => '수거중',
            self::RECEIVED => '입고완료',
            self::COMPLETED => '처리완료',
            self::REJECTED => '반려',
        };
    }

    /** 더 이상 진행되지 않는가. */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REJECTED], true);
    }

    /**
     * 이 상태의 신청이 항목 수량을 '점유'하는가.
     *
     * 반려된 건은 점유를 풀어 같은 항목을 다시 신청할 수 있어야 한다.
     */
    public function occupiesQuantity(): bool
    {
        return $this !== self::REJECTED;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
