<?php

declare(strict_types=1);

namespace App\Enums\Support;

/**
 * 1:1 문의 상태. 값은 대문자 (CLAUDE.md §6.1).
 */
enum InquiryStatus: string
{
    case PENDING = 'PENDING';
    case ANSWERED = 'ANSWERED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '답변대기',
            self::ANSWERED => '답변완료',
        };
    }
}
