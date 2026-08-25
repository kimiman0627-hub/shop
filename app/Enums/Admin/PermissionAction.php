<?php

declare(strict_types=1);

namespace App\Enums\Admin;

/**
 * 관리자 페이지 권한 종류. 값은 대문자 (CLAUDE.md §6.1).
 *
 * READ  = 페이지 조회
 * WRITE = 생성/수정/삭제
 *
 * 읽기 없이 쓰기만 가능한 조합은 허용하지 않는다 (CLAUDE.md §7.2).
 */
enum PermissionAction: string
{
    case READ = 'READ';
    case WRITE = 'WRITE';

    public function label(): string
    {
        return match ($this) {
            self::READ => '조회',
            self::WRITE => '쓰기',
        };
    }
}
