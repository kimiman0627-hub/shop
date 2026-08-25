<?php

declare(strict_types=1);

namespace App\Enums\Admin;

/**
 * 관리자 계정 상태. 값은 대문자 (CLAUDE.md §6.1).
 */
enum AdminStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => '활성',
            self::SUSPENDED => '정지',
        };
    }

    /**
     * 로그인 가능한 상태인가.
     */
    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }
}
