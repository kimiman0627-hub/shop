<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 회원 배송지록. 회원당 여러 개, 그중 하나가 기본이다.
 */
#[Fillable(['user_id', 'label', 'receiver_name', 'receiver_phone', 'postcode', 'address1', 'address2', 'is_default'])]
class UserAddress extends Model
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        // 기본 배송지가 항상 맨 위. 그 다음은 최근 추가 순.
        return $query->orderByDesc('is_default')->orderByDesc('id');
    }
}
