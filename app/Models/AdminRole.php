<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 관리자 역할 (CLAUDE.md §7.2).
 */
#[Fillable(['code', 'name', 'description'])]
class AdminRole extends Model
{
    /**
     * 최고관리자 역할 코드. 권한 검사를 전부 통과한다.
     * DB 레코드에 의존하지 않는 하드코딩 예외다 (CLAUDE.md §7.2).
     */
    public const SUPER_ADMIN = 'SUPER_ADMIN';

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AdminRolePermission::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->code === self::SUPER_ADMIN;
    }
}
