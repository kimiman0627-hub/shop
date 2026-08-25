<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use App\Enums\Admin\PermissionAction;
use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Product\QuestionStatus;
use App\Enums\Product\ReviewStatus;
use App\Enums\Returns\ReturnStatus;
use App\Enums\Support\InquiryStatus;
use App\Models\Admin;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use Closure;

/**
 * "지금 사람이 손대야 하는 일" 목록.
 *
 * 원래 `DashboardLibrary::todo()` 안에만 있었는데, 관리자 상단 알림에서도 같은
 * 숫자가 필요해져 여기로 뺐다 — **두 곳에서 각자 세면 반드시 어긋난다.**
 * 대시보드와 헤더가 같은 정의를 공유한다.
 *
 * **관리자가 볼 권한이 있는 항목만 내린다** (CLAUDE.md §7.5). 사이드바에서 메뉴를
 * 숨겨놓고 알림에 그 숫자를 띄우면 권한 분리가 무의미해진다.
 */
class AdminTodoLibrary
{
    public function __construct(private readonly AdminPermissionLibrary $permissions) {}

    /**
     * @return list<array{label: string, href: string, hint: string, count: int}>
     */
    public function forAdmin(Admin $admin): array
    {
        $visible = [];

        foreach ($this->cards() as $card) {
            if (! $this->permissions->allows($admin, $card['menu'], PermissionAction::READ)) {
                continue;
            }

            $visible[] = [
                'label' => $card['label'],
                'href' => $card['href'],
                'hint' => $card['hint'],
                'count' => ($card['count'])(),
            ];
        }

        return $visible;
    }

    /**
     * 헤더 배지에 찍을 총합. 0 이면 화면이 배지를 안 그린다.
     */
    public function countForAdmin(Admin $admin): int
    {
        return array_sum(array_column($this->forAdmin($admin), 'count'));
    }

    /**
     * @return list<array{menu: string, label: string, href: string, hint: string, count: Closure(): int}>
     */
    private function cards(): array
    {
        return [
            [
                'menu' => 'PAYMENT_DEPOSIT',
                'label' => '입금 확인 대기',
                'href' => '/admin/payments/deposits',
                'count' => fn () => Payment::query()
                    ->where('status', PaymentStatus::READY->value)
                    ->count(),
                'hint' => '입금자명을 확인하고 결제완료 처리하세요.',
            ],
            [
                'menu' => 'ORDER_SHIPMENT',
                'label' => '출고 대기',
                'href' => '/admin/shipments',
                'count' => fn () => Order::query()
                    ->whereIn('status', [OrderStatus::PAID->value, OrderStatus::PREPARING->value])
                    ->count(),
                'hint' => '결제됐지만 아직 송장이 등록되지 않은 주문입니다.',
            ],
            [
                'menu' => 'ORDER_RETURN',
                'label' => '반품·교환 접수',
                'href' => '/admin/returns',
                'count' => fn () => OrderReturn::query()
                    ->where('status', ReturnStatus::REQUESTED->value)
                    ->count(),
                'hint' => '승인 또는 반려가 필요합니다.',
            ],
            [
                'menu' => 'ORDER_RETURN',
                'label' => '반품 입고 완료',
                'href' => '/admin/returns?status=RECEIVED',
                'count' => fn () => OrderReturn::query()
                    ->where('status', ReturnStatus::RECEIVED->value)
                    ->count(),
                'hint' => '물건이 들어왔습니다. 처리완료하면 환불·재고가 반영됩니다.',
            ],
            [
                'menu' => 'MEMBER_LIST',
                'label' => '미답변 1:1문의',
                'href' => '/admin/members',
                'count' => fn () => Inquiry::query()
                    ->where('status', InquiryStatus::PENDING->value)
                    ->count(),
                'hint' => '회원 상세의 1:1문의 탭에서 답변합니다.',
            ],
            [
                'menu' => 'PRODUCT_QNA',
                'label' => '미답변 상품문의',
                'href' => '/admin/questions?status=PENDING',
                'count' => fn () => ProductQuestion::query()
                    ->where('status', QuestionStatus::PENDING->value)
                    ->count(),
                'hint' => '상품 상세에 공개로 노출되는 문의입니다. 먼저 답하세요.',
            ],
            [
                'menu' => 'PRODUCT_REVIEW',
                'label' => '답글 없는 후기',
                'href' => '/admin/reviews',
                /*
                 * 후기는 "미답변" 상태값이 따로 없다(상태는 노출/숨김뿐) —
                 * 답글 컬럼이 비었는지로 판단한다. 숨긴 후기는 세지 않는다:
                 * 안 보이는 글에 답글을 달 이유가 없다.
                 */
                'count' => fn () => ProductReview::query()
                    ->where('status', ReviewStatus::PUBLISHED->value)
                    ->whereNull('admin_reply')
                    ->count(),
                'hint' => '답글은 선택이지만, 낮은 평점부터 확인하는 편이 좋습니다.',
            ],
            [
                'menu' => 'ORDER_LIST',
                'label' => '입금기한 초과',
                'href' => '/admin/orders?status=PENDING',
                'count' => fn () => Order::query()
                    ->where('status', OrderStatus::PENDING->value)
                    ->whereNotNull('payment_due_at')
                    ->where('payment_due_at', '<', now())
                    ->count(),
                'hint' => '스케줄러가 곧 자동 취소합니다. 안 줄어들면 schedule:work 를 확인하세요.',
            ],
        ];
    }
}
