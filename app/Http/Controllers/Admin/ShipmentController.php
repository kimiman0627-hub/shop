<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Order\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Order\ShipmentLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 주문관리 > 배송관리 (menu_code: ORDER_SHIPMENT).
 */
class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShipmentLibrary $shipments,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'keyword' => $request->string('keyword')->toString(),
        ];

        return Inertia::render('Admin/Shipment/Index', [
            'orders' => $this->shipments->getAdminList($filters),
            'filters' => $filters,
            'carrierOptions' => $this->shipments->carrierOptions(),
            'statusOptions' => array_map(
                fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                // 배송 대상 단계만 필터로 노출한다.
                [OrderStatus::PAID, OrderStatus::PREPARING, OrderStatus::SHIPPING, OrderStatus::DELIVERED],
            ),
        ]);
    }

    public function prepare(int $order): RedirectResponse
    {
        try {
            $this->shipments->markPreparing($order);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '상품준비중으로 변경했습니다.');
    }

    public function ship(Request $request, int $order): RedirectResponse
    {
        $validated = $request->validate([
            'carrier' => ['required', Rule::in(array_keys(config('shop.shipping.carriers')))],
            // 직접배송은 송장이 없을 수 있다.
            'tracking_no' => ['nullable', 'string', 'max:50', 'regex:/^[0-9A-Za-z\-]+$/'],
            'memo' => ['nullable', 'string', 'max:255'],
        ], [
            'tracking_no.regex' => '송장번호는 영문·숫자·하이픈만 입력할 수 있습니다.',
        ]);

        try {
            $this->shipments->ship($order, $validated, $request->user('admin')->id);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '출고 처리했습니다.');
    }

    public function deliver(int $order): RedirectResponse
    {
        try {
            $this->shipments->markDelivered($order);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '배송완료로 변경했습니다.');
    }

    public function revert(Request $request, int $order): RedirectResponse
    {
        $validated = $request->validate([
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->shipments->revertShipping($order, $validated['memo'] ?? '');
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '출고를 취소하고 준비중으로 되돌렸습니다.');
    }
}
