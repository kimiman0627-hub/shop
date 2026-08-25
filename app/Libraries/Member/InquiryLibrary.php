<?php

declare(strict_types=1);

namespace App\Libraries\Member;

use App\Enums\Support\InquiryStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Inquiry;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * 1:1 문의.
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class InquiryLibrary
{
    /**
     * 고객이 문의를 남긴다.
     *
     * @param  array{category: string, title: string, content: string, order_id: int|null}  $data
     */
    public function create(int $userId, array $data): Inquiry
    {
        $orderId = $data['order_id'] ?? null;

        // 남의 주문번호를 붙여 문의를 남기면 그 주문 정보가 노출된다.
        if ($orderId !== null) {
            $owned = Order::query()
                ->where('id', $orderId)
                ->where('user_id', $userId)
                ->exists();

            if (! $owned) {
                throw new DomainRuleException('본인의 주문만 선택할 수 있습니다.', 'order_id');
            }
        }

        return Inquiry::query()->create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'category' => $data['category'],
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => InquiryStatus::PENDING,
        ]);
    }

    /**
     * 관리자가 답변한다.
     */
    public function answer(int $inquiryId, int $adminId, string $answer): Inquiry
    {
        $inquiry = Inquiry::query()->findOrFail($inquiryId);

        $inquiry->forceFill([
            'answer' => $answer,
            'status' => InquiryStatus::ANSWERED,
            'answered_at' => now(),
            'answered_by_admin_id' => $adminId,
        ])->save();

        return $inquiry;
    }

    /**
     * 고객 본인 문의 목록.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function myInquiries(int $userId): Collection
    {
        return Inquiry::query()
            ->where('user_id', $userId)
            ->with('order:id,order_no')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Inquiry $i) => [
                'id' => $i->id,
                'category_label' => $i->category->label(),
                'title' => $i->title,
                'content' => $i->content,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'order_no' => $i->order?->order_no,
                'answer' => $i->answer,
                'answered_at' => $i->answered_at?->toDateTimeString(),
                'created_at' => $i->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * 고객이 문의에 붙일 수 있는 주문 목록.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    public function selectableOrders(int $userId): Collection
    {
        return Order::query()
            ->where('user_id', $userId)
            ->ordered()
            ->limit(30)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'label' => "{$o->order_no} ({$o->ordered_at->toDateString()}, {$o->status->label()})",
            ]);
    }

    /**
     * 관리자 문의 목록. 미답변이 먼저 온다.
     *
     * @param  array{status?: string|null, keyword?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return Inquiry::query()
            ->with(['user:id,name,email', 'order:id,order_no', 'answeredBy:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($keyword !== '', fn ($q) => $q->where(
                fn ($sub) => $sub
                    ->whereLike('title', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereHas('user', fn ($u) => $u
                        ->whereLike('name', '%'.$keyword.'%', caseSensitive: false)
                        ->orWhereLike('email', '%'.$keyword.'%', caseSensitive: false)),
            ))
            // 답변대기(PENDING)가 ANSWERED 보다 알파벳상 앞이라 오름차순이면 먼저 온다.
            ->orderBy('status')
            ->orderBy('created_at')
            ->paginate(config('shop.per_page.admin'))
            ->withQueryString()
            ->through(fn (Inquiry $i) => [
                'id' => $i->id,
                'user_id' => $i->user_id,
                'user_name' => $i->user?->name,
                'user_email' => $i->user?->email,
                'category_label' => $i->category->label(),
                'title' => $i->title,
                'content' => $i->content,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'order_no' => $i->order?->order_no,
                'answer' => $i->answer,
                'answered_at' => $i->answered_at?->toDateTimeString(),
                'answered_by' => $i->answeredBy?->name,
                'created_at' => $i->created_at?->toDateTimeString(),
            ]);
    }
}
