<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Admin\AdminStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 관리자 계정. 고객(User)과 테이블·가드가 완전히 분리되어 있다 (CLAUDE.md §7.1).
 *
 * 가드: admin (config/auth.php)
 * 로그인 식별자: login_id
 */
// last_login_at 은 일부러 뺐다. 시스템이 로그인 시에만 forceFill 로 쓴다 —
// 폼에서 넘어온 값으로 덮이면 안 된다.
#[Fillable(['login_id', 'name', 'email', 'password', 'admin_role_id', 'status'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => AdminStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    public function canLogin(): bool
    {
        return $this->status->canLogin();
    }
}
