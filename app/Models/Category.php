<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 상품 카테고리 (docs/schema-draft.md §2.1).
 *
 * 모델에는 관계·캐스팅·스코프까지만 둔다. 조건이 붙는 조회 조립은
 * CategoryLibrary 에서 한다 (CLAUDE.md §4.2).
 */
#[Fillable(['parent_id', 'name', 'slug', 'depth', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** 최대 계층 깊이. depth 는 0부터이므로 3단계 = 0,1,2 */
    public const MAX_DEPTH = 3;

    protected function casts(): array
    {
        return [
            // SQLite 는 0/1, PostgreSQL 은 true/false. 캐스팅을 빼면 갈린다 (CLAUDE.md §5.2).
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** 목록 정렬은 항상 명시한다 — 미지정 시 순서는 PostgreSQL 에서 보장되지 않는다 (CLAUDE.md §5.2). */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
