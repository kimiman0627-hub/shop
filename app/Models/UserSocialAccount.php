<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 간편로그인 연동 계정 (카카오·네이버). User 1 - N.
 */
#[Fillable(['user_id', 'provider', 'provider_user_id'])]
class UserSocialAccount extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
