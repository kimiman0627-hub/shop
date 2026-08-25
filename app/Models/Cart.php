<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 장바구니 (docs/schema-draft.md §3.1).
 *
 * 회원은 user_id, 비회원은 session_token 으로 식별한다.
 */
#[Fillable(['user_id', 'session_token'])]
class Cart extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }
}
