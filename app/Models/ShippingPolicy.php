<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 배송비 정책 (docs/schema-draft.md §6.1).
 */
#[Fillable(['name', 'base_fee', 'free_threshold', 'is_default', 'is_active'])]
class ShippingPolicy extends Model
{
    protected function casts(): array
    {
        return [
            // SQLite 0/1 vs PostgreSQL true/false (CLAUDE.md §5.2).
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'base_fee' => 'integer',
            'free_threshold' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderBy('id');
    }

    /** 조건부 무료배송을 쓰는 정책인가. */
    public function hasFreeThreshold(): bool
    {
        return $this->free_threshold !== null;
    }
}
