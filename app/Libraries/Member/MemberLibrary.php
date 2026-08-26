<?php

declare(strict_types=1);

namespace App\Libraries\Member;

use App\Enums\Order\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Libraries\Order\CouponLibrary;
use App\Models\Inquiry;
use App\Models\MemberMemo;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

/**
 * 회원 관리 (관리자용).
 *
 * 상세는 주문·결제·배송·문의·쿠폰을 한 번에 모은다 —
 * 문의 응대 중에 화면을 여러 개 오가지 않게 하려는 것이다.
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class MemberLibrary
{
    public function __construct(
        private readonly CouponLibrary $coupons,
        private readonly AddressLibrary $addresses,
    ) {}

    /**
     * @param  array{keyword?: string|null, verified?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $verified = $filters['verified'] ?? '';

        return User::query()
            ->withCount([
                'orders',
                // 취소·환불을 뺀 '실제 구매' 건수. 매출 판단의 기준이다.
                'orders as paid_orders_count' => fn ($q) => $q->whereNotIn('status', [
                    OrderStatus::PENDING->value,
                    OrderStatus::CANCELED->value,
                    OrderStatus::REFUNDED->value,
                ]),
                'inquiries as pending_inquiries_count' => fn ($q) => $q->pending(),
            ])
            ->withSum([
                'orders as total_spent' => fn ($q) => $q->whereNotIn('status', [
                    OrderStatus::PENDING->value,
                    OrderStatus::CANCELED->value,
                    OrderStatus::REFUNDED->value,
                ]),
            ], 'total_amount')
            ->when($keyword !== '', fn ($q) => $q->where(
                fn ($sub) => $sub
                    ->whereLike('name', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereLike('email', '%'.$keyword.'%', caseSensitive: false),
            ))
            ->when($verified === 'Y', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($verified === 'N', fn ($q) => $q->whereNull('email_verified_at'))
            ->orderByDesc('id')
            ->paginate(config('shop.per_page.admin'))
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'email_verified' => $u->hasVerifiedEmail(),
                'phone' => $u->phone,
                'joined_at' => LocalTime::date($u->created_at),
                'last_login_at' => LocalTime::dateTime($u->last_login_at),
                'orders_count' => $u->orders_count,
                'paid_orders_count' => $u->paid_orders_count,
                'total_spent' => (int) ($u->total_spent ?? 0),
                'pending_inquiries_count' => $u->pending_inquiries_count,
            ]);
    }

    /**
     * 회원 상세. 모달 한 장에 필요한 모든 것.
     *
     * @return array<string, mixed>
     */
    public function getDetail(int $userId): array
    {
        $user = User::query()->findOrFail($userId);

        return [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'email_verified_at' => LocalTime::dateTime($user->email_verified_at),
                'phone' => $user->phone,
                'marketing_email_agreed' => $user->hasAgreedToEmailMarketing(),
                'marketing_sms_agreed' => $user->hasAgreedToSmsMarketing(),
                'joined_at' => LocalTime::dateTime($user->created_at),
                'last_login_at' => LocalTime::dateTime($user->last_login_at),
            ],
            'stats' => $this->stats($user->id),
            'addresses' => $this->addresses->listFor($user->id)->values()->all(),
            'orders' => $this->orders($user->id),
            'payments' => $this->payments($user->id),
            'inquiries' => $this->inquiries($user->id),
            'memos' => $this->memos($user->id),
            'coupons' => $this->coupons->myCoupons($user->id)->values()->all(),
        ];
    }

    /**
     * 회원 정보 수정.
     *
     * @param  array{name: string, email: string, email_verified: bool}  $data
     */
    public function update(int $userId, array $data): User
    {
        $user = User::query()->findOrFail($userId);

        $duplicate = User::query()
            ->where('email', $data['email'])
            ->whereKeyNot($user->id)
            ->exists();

        if ($duplicate) {
            throw new DomainRuleException('이미 사용 중인 이메일입니다.', 'email');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        // 이메일을 바꾸면 인증이 무효가 된다. 새 주소는 확인된 적이 없다.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // 관리자가 수동으로 인증 처리할 수 있다. 고객이 메일을 못 받는 경우가 있다.
        if ($data['email_verified'] && $user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        if (! $data['email_verified']) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }

    public function addMemo(int $userId, int $adminId, string $content): MemberMemo
    {
        User::query()->findOrFail($userId);

        return MemberMemo::query()->create([
            'user_id' => $userId,
            'admin_id' => $adminId,
            'content' => $content,
        ]);
    }

    public function deleteMemo(int $userId, int $memoId): void
    {
        MemberMemo::query()
            ->where('user_id', $userId)
            ->findOrFail($memoId)
            ->delete();
    }

    // ------------------------------------------------------------------ 내부

    /**
     * @return array<string, mixed>
     */
    private function stats(int $userId): array
    {
        $countable = fn () => Order::query()
            ->where('user_id', $userId)
            ->whereNotIn('status', [
                OrderStatus::PENDING->value,
                OrderStatus::CANCELED->value,
                OrderStatus::REFUNDED->value,
            ]);

        return [
            'orders_count' => Order::query()->where('user_id', $userId)->count(),
            'paid_orders_count' => $countable()->count(),
            'total_spent' => (int) $countable()->sum('total_amount'),
            'canceled_count' => Order::query()->where('user_id', $userId)
                ->whereIn('status', [OrderStatus::CANCELED->value, OrderStatus::REFUNDED->value])
                ->count(),
            'last_ordered_at' => Order::query()->where('user_id', $userId)
                ->max('ordered_at'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orders(int $userId): array
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with(['items', 'shipment'])
            ->ordered()
            ->limit(20)
            ->get()
            ->map(function (Order $o) {
                $first = $o->items->first();
                $more = $o->items->count() - 1;

                return [
                    'id' => $o->id,
                    'order_no' => $o->order_no,
                    'status' => $o->status->value,
                    'status_label' => $o->status->label(),
                    'total_amount' => $o->total_amount,
                    'ordered_at' => LocalTime::dateTime($o->ordered_at),
                    'item_summary' => $first === null
                        ? '-'
                        : ($more > 0 ? "{$first->product_name} 외 {$more}건" : $first->product_name),
                    'thumbnail_url' => $this->thumbnailOf($o),
                    // 배송은 출고된 건만 의미가 있다.
                    'carrier_name' => $o->shipment?->carrierName(),
                    'tracking_no' => $o->shipment?->tracking_no,
                    'tracking_url' => $o->shipment?->trackingUrl(),
                    'shipment_status_label' => $o->shipment?->status->label(),
                ];
            })
            ->all();
    }

    private function thumbnailOf(Order $order): ?string
    {
        $path = $order->items->first()?->product?->thumbnail_path;

        return $path === null
            ? null
            : Storage::disk(config('shop.image.disk'))->url($path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payments(int $userId): array
    {
        return Payment::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->with(['order:id,order_no', 'confirmedBy:id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'order_no' => $p->order?->order_no,
                'method_label' => $p->method->label(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'amount' => $p->amount,
                'requested_at' => LocalTime::dateTime($p->requested_at),
                'paid_at' => LocalTime::dateTime($p->paid_at),
                'confirmed_by' => $p->confirmedBy?->name,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inquiries(int $userId): array
    {
        return Inquiry::query()
            ->where('user_id', $userId)
            ->with(['order:id,order_no', 'answeredBy:id,name'])
            ->orderByDesc('id')
            ->limit(20)
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
                'answered_at' => LocalTime::dateTime($i->answered_at),
                'answered_by' => $i->answeredBy?->name,
                'created_at' => LocalTime::dateTime($i->created_at),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memos(int $userId): array
    {
        return MemberMemo::query()
            ->where('user_id', $userId)
            ->with('admin:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MemberMemo $m) => [
                'id' => $m->id,
                'content' => $m->content,
                'admin_name' => $m->admin?->name ?? '(삭제된 관리자)',
                'created_at' => LocalTime::dateTime($m->created_at),
            ])
            ->all();
    }
}
