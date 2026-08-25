<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Support\InquiryCategory;
use App\Enums\Support\InquiryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 문의.
 */
#[Fillable([
    'user_id', 'order_id', 'category', 'title', 'content',
    'status', 'answer', 'answered_at', 'answered_by_admin_id',
])]
class Inquiry extends Model
{
    protected function casts(): array
    {
        return [
            'category' => InquiryCategory::class,
            'status' => InquiryStatus::class,
            'answered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'answered_by_admin_id');
    }

    public function isAnswered(): bool
    {
        return $this->status === InquiryStatus::ANSWERED;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InquiryStatus::PENDING->value);
    }
}
