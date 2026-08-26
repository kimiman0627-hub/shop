<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Enums\Returns\ReturnReason;
use App\Enums\Returns\ReturnType;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Order\ReturnLibrary;
use App\Models\Order;
use App\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 반품·교환 신청.
 *
 * 회원 전용이다 — 처리 결과를 전달할 창구가 있어야 한다.
 * 비회원은 1:1 문의 대신 고객센터로 유도하고, 관리자가 대행 접수한다.
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnLibrary $returns) {}

    /** 내 신청 내역. */
    public function index(Request $request): Response
    {
        return Inertia::render('Store/Return/Index', [
            'returns' => $this->returns->myReturns($request->user()->id),
        ]);
    }

    /** 신청서. 주문에서 어떤 상품을 몇 개 돌려보낼지 고른다. */
    public function create(Request $request, int $order): Response|RedirectResponse
    {
        $target = $this->ownedOrder($request, $order);

        if ($target === null) {
            return redirect()->route('orders.index')->withErrors(['return' => '주문을 찾을 수 없습니다.']);
        }

        return Inertia::render('Store/Return/Create', [
            'order' => [
                'id' => $target->id,
                'order_no' => $target->order_no,
                'status_label' => $target->status->label(),
                'delivered_at' => LocalTime::dateTime($target->shipment?->delivered_at),
            ],
            'items' => $this->returns->requestableItems($target->id),
            'typeOptions' => ReturnType::options(),
            'reasonOptions' => ReturnReason::options(),
            'returnDays' => (int) config('shop.return.days'),
            'returnShippingFee' => (int) config('shop.return.shipping_fee'),
            'existing' => $this->returns->forOrder($target->id),
        ]);
    }

    public function store(Request $request, int $order): RedirectResponse
    {
        $target = $this->ownedOrder($request, $order);

        if ($target === null) {
            return redirect()->route('orders.index')->withErrors(['return' => '주문을 찾을 수 없습니다.']);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:RETURN,EXCHANGE'],
            'reason' => ['required', 'string', 'max:30'],
            'reason_detail' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.exchange_variant_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->returns->create($target->id, $validated);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return redirect()->route('returns.index')
            ->with('status', '반품·교환 신청이 접수되었습니다. 관리자 확인 후 안내드립니다.');
    }

    public function cancel(Request $request, int $return): RedirectResponse
    {
        try {
            $this->returns->cancelRequest($return, $request->user()->id);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('status', '신청을 취소했습니다.');
    }

    /**
     * 남의 주문에 반품을 걸 수 없게 소유권을 확인한다.
     *
     * 컨트롤러가 하는 건 '누구의 요청인가' 까지다. '반품 가능한 상태인가' 는
     * 라이브러리가 판단한다 — 관리자 대행 접수와 규칙이 같아야 하기 때문이다.
     */
    private function ownedOrder(Request $request, int $orderId): ?Order
    {
        return Order::query()
            ->with('shipment')
            ->where('user_id', $request->user()->id)
            ->find($orderId);
    }
}
