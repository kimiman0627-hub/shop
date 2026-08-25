<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Order\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Order\OrderLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 주문관리 > 주문목록 (menu_code: ORDER_LIST).
 *
 * 배송관리(ORDER_SHIPMENT)는 결제 이후 배송 단계만 다룬다.
 * 여기는 **모든 상태**를 보고 상세 확인·강제취소를 한다.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderLibrary $orders,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'keyword' => $request->string('keyword')->toString(),
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        return Inertia::render('Admin/Order/Index', [
            'orders' => $this->orders->getAdminList($filters),
            'filters' => $filters,
            'statusOptions' => array_map(
                fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                OrderStatus::cases(),
            ),
        ]);
    }

    public function show(int $order): Response
    {
        return Inertia::render('Admin/Order/Show', [
            'order' => $this->orders->getAdminDetail($order),
        ]);
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        $validated = $request->validate([
            'memo' => ['required', 'string', 'max:255'],
        ], [
            // 강제 취소는 사유가 남아야 한다. 나중에 왜 취소됐는지 설명할 수 있어야 한다.
            'memo.required' => '취소 사유를 입력하세요.',
        ]);

        try {
            $this->orders->cancel(
                $order,
                '관리자 취소: '.$validated['memo'],
                byAdmin: true,
            );
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '주문을 취소했습니다.');
    }
}
