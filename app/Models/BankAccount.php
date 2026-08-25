<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 무통장입금 계좌 (관리자 설정).
 */
#[Fillable(['bank_name', 'account_number', 'holder_name', 'is_default', 'is_active', 'sort_order'])]
class BankAccount extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id');
    }

    public function label(): string
    {
        return "{$this->bank_name} {$this->account_number} ({$this->holder_name})";
    }
}
