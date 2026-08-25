<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 고객 계정. 관리자는 별도 테이블/가드를 쓴다 (App\Models\Admin, CLAUDE.md §7.1).
 *
 * `marketing_*_agreed_at` / `last_login_at` 은 일부러 Fillable 에서 뺐다.
 * 동의 시각은 `agreeToMarketing()` / `revokeMarketing()` 으로만 찍고,
 * 로그인 시각은 로그인 리스너만 forceFill 한다 — 폼에서 덮어쓰면
 * "언제 동의했는가" 라는 법적 증빙이 조용히 사라진다 (Admin::last_login_at 과 같은 이유).
 */
#[Fillable(['name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'marketing_email_agreed_at' => 'datetime',
            'marketing_sms_agreed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function memos(): HasMany
    {
        return $this->hasMany(MemberMemo::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    public function hasAgreedToEmailMarketing(): bool
    {
        return $this->marketing_email_agreed_at !== null;
    }

    public function hasAgreedToSmsMarketing(): bool
    {
        return $this->marketing_sms_agreed_at !== null;
    }
}
