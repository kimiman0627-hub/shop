<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Product\QuestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 상품 문의 (docs/schema-draft.md §12.3).
 *
 * 1:1 문의(Inquiry)와 다르다 — 그쪽은 주문에 붙고 비공개,
 * 이쪽은 상품에 붙고 기본 공개다.
 */
#[Fillable([
    'product_id', 'user_id', 'content', 'is_secret', 'status',
    'answer', 'answered_by_admin_id', 'answered_at',
])]
class ProductQuestion extends Model
{
    protected function casts(): array
    {
        return [
            'status' => QuestionStatus::class,
            'is_secret' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'answered_by_admin_id');
    }

    /**
     * 이 사람이 내용을 볼 수 있는가.
     *
     * 비밀글은 작성자와 관리자만 본다. **판단을 화면에 맡기지 않는다** —
     * 서버가 아예 내용을 안 내려야 한다.
     */
    public function isVisibleTo(?int $userId, bool $isAdmin = false): bool
    {
        return ! $this->is_secret || $isAdmin || ($userId !== null && $userId === $this->user_id);
    }
}
