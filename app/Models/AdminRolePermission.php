<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 역할이 특정 관리자 메뉴에 대해 갖는 권한 (CLAUDE.md §7.2).
 */
#[Fillable(['admin_role_id', 'menu_code', 'can_read', 'can_write'])]
class AdminRolePermission extends Model
{
    protected function casts(): array
    {
        return [
            // SQLite 는 0/1, PostgreSQL 은 true/false 로 저장한다.
            // 캐스팅을 빼면 두 DB 에서 동작이 갈린다 (CLAUDE.md §5.2).
            'can_read' => 'boolean',
            'can_write' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }
}
