<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Support\CartOwnerResolver;
use App\Libraries\Order\CartLibrary;
use App\Libraries\Shipping\ShippingPolicyLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 장바구니.
 *
 * 세션은 CartOwnerResolver 만 만진다. CartLibrary 는 CartOwner 만 받는다 (CLAUDE.md §4.2).
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartLibrary $carts,
        private readonly CartOwnerResolver $owners,
        private readonly ShippingPolicyLibrary $shipping,
    ) {}

    public function index(Request $request): Response
    {
        /*
         * 장바구니로 돌아왔다는 건 바로구매 흐름을 벗어났다는 뜻이다.
         * 안 지우면 여기서 '주문하기' 를 눌러도 세션에 남은 바로구매 건이 주문된다.
         */
        $request->session()->forget('direct_order');

        $summary = $this->carts->summary($this->owners->resolve($request));

        return Inertia::render('Store/Cart/Index', [
            'cart' => $summary,
            // 실제 청구 배송비는 여기서도 같은 계산기를 쓴다 — 화면마다 다르면 안 된다.
            'shipping_fee' => $this->shipping->calculateFee($summary['items']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->carts->add(
                $this->owners->resolve($request),
                $validated['variant_id'],
                $validated['quantity'],
            );
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '장바구니에 담았습니다.');
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->carts->updateQuantity(
                $this->owners->resolve($request),
                $item,
                $validated['quantity'],
            );
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '수량을 변경했습니다.');
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        try {
            $this->carts->remove($this->owners->resolve($request), $item);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '항목을 삭제했습니다.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->carts->clear($this->owners->resolve($request));

        return back()->with('status', '장바구니를 비웠습니다.');
    }
}
