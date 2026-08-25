<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Enums\Payment\PaymentMethod;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CheckoutRequest;
use App\Http\Support\CartOwnerResolver;
use App\Libraries\Member\AddressLibrary;
use App\Libraries\Order\CartLibrary;
use App\Libraries\Order\CouponLibrary;
use App\Libraries\Order\OrderLibrary;
use App\Libraries\Payment\BankAccountLibrary;
use App\Libraries\Payment\PaymentLibrary;
use App\Libraries\Shipping\ShippingPolicyLibrary;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 주문.
 *
 * **구매는 회원만 할 수 있다.** 결제 경로(checkout/store/complete)는 라우트에서
 * auth 로 막는다. 장바구니는 비회원도 쓰고, 로그인 시 병합된다.
 *
 * `lookup` 만 비로그인으로 열려 있다 — 정책 변경 전에 접수된 비회원 주문을
 * 조회할 유일한 통로이기 때문이다.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderLibrary $orders,
        private readonly CartLibrary $carts,
        private readonly CouponLibrary $coupons,
        private readonly CartOwnerResolver $owners,
        private readonly ShippingPolicyLibrary $shipping,
        private readonly PaymentLibrary $payments,
        private readonly BankAccountLibrary $bankAccounts,
        private readonly AddressLibrary $addresses,
    ) {}

    /**
     * 바로구매 접수.
     *
     * **비로그인이면 로그인 화면으로 보내고, 로그인 후 보던 상품으로 되돌린다.**
     * `url.intended` 는 Fortify 의 `redirect()->intended()` 가 읽는 자리다.
     *
     * 이 검사를 컨트롤러에 두는 이유는 라우트 주석에 적어뒀다 — POST 라
     * auth 미들웨어에 맡기면 돌아올 자리가 referer 로 잡혀 부정확해진다.
     */
    public function direct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            // 로그인 후 돌아올 자리. 상품 상세가 넘겨준다.
            'return_to' => ['required', 'string', 'max:255'],
        ]);

        // 열린 리다이렉트를 막는다 — 외부 주소로는 절대 돌려보내지 않는다.
        $returnTo = str_starts_with($validated['return_to'], '/')
            ? $validated['return_to']
            : '/';

        if ($request->user() === null) {
            $request->session()->put('url.intended', $returnTo);

            return redirect()->route('login')
                ->with('status', '구매하려면 로그인이 필요합니다. 로그인하시면 보시던 상품으로 돌아갑니다.');
        }

        try {
            // 담기 전에 살 수 있는 상태인지 확인한다. 실제 재고 확정은 주문 생성 시 잠그고 한다.
            $summary = $this->carts->directSummary($validated['variant_id'], $validated['quantity']);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        if ($summary['has_issue']) {
            return back()->withErrors(['general' => '재고가 부족하거나 판매 중지된 상품입니다.']);
        }

        // 주문서까지만 세션으로 넘긴다. 장바구니는 건드리지 않는다.
        $request->session()->put('direct_order', [
            'variant_id' => $validated['variant_id'],
            'quantity' => $validated['quantity'],
        ]);

        return redirect()->route('orders.checkout');
    }

    /** 주문서 작성 화면. */
    public function checkout(Request $request): Response|RedirectResponse
    {
        $direct = $request->session()->get('direct_order');

        if ($direct !== null) {
            try {
                $summary = $this->carts->directSummary($direct['variant_id'], $direct['quantity']);
            } catch (DomainRuleException $e) {
                $request->session()->forget('direct_order');

                return redirect()->route('cart.index')->withErrors(['general' => $e->getMessage()]);
            }
        } else {
            $summary = $this->carts->summary($this->owners->resolve($request));
        }

        if ($summary['items'] === []) {
            return redirect()->route('cart.index')->withErrors(['general' => '장바구니가 비어 있습니다.']);
        }

        // auth 미들웨어를 거쳤으므로 여기서 회원은 항상 있다.
        $user = $request->user();

        return Inertia::render('Store/Order/Checkout', [
            'isDirect' => $direct !== null,
            'cart' => $summary,
            'shipping_fee' => $this->shipping->calculateFee($summary['items']),
            'coupons' => $this->coupons->usableFor($user->id, $summary['items_total']),

            'paymentMethods' => array_map(
                fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()],
                PaymentMethod::available(),
            ),
            // 계좌가 없으면 무통장 주문을 받을 수 없다. 화면에서 미리 알린다.
            'hasBankAccount' => $this->bankAccounts->hasUsableAccount(),

            // 저장된 배송지. 없으면 빈 배열 — 그냥 직접 입력한다.
            'savedAddresses' => $this->addresses->listFor($user->id),
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $direct = $request->session()->get('direct_order');

        try {
            /*
             * 바로구매는 세션에 담긴 조합 하나로만 주문한다.
             * **장바구니에서 다시 읽지 않는다** — 그러면 담아둔 다른 상품까지 결제된다.
             */
            $order = $direct !== null
                ? $this->orders->createDirect(
                    $this->carts->directSummary($direct['variant_id'], $direct['quantity']),
                    $validated,
                    $request->user()->id,
                )
                : $this->orders->createFromCart(
                    $this->owners->resolve($request),
                    $validated,
                    $request->user()->id,
                );

            // 주문이 만들어진 뒤 결제 요청을 낸다. 계좌 스냅샷 저장 + 입금 안내 발송.
            $this->payments->requestBankTransfer($order, $validated['depositor_name'] ?? null);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        $this->saveAddressIfRequested($request, $validated);

        // 바로구매 세션은 여기서 끝난다. 안 지우면 다음 장바구니 주문까지 가로챈다.
        $request->session()->forget('direct_order');

        // 주문 완료 화면은 방금 만든 주문만 볼 수 있게 세션으로 넘긴다.
        $request->session()->put('recent_order_no', $order->order_no);

        return redirect()->route('orders.complete');
    }

    /**
     * 주문서에서 '이 배송지 저장' 을 체크했으면 배송지록에 추가한다.
     *
     * **주문 자체를 막지 않는다.** 배송지 개수 초과 같은 사유로 실패해도
     * 결제는 이미 끝난 뒤라 조용히 건너뛴다 — 부가 기능이 본 절차를 막으면 안 된다.
     *
     * @param  array<string, mixed>  $validated
     */
    private function saveAddressIfRequested(Request $request, array $validated): void
    {
        $userId = $request->user()->id;

        if (! ($validated['save_address'] ?? false)) {
            return;
        }

        try {
            $this->addresses->create($userId, [
                'label' => null,
                'receiver_name' => $validated['receiver_name'],
                'receiver_phone' => $validated['receiver_phone'],
                'postcode' => $validated['postcode'],
                'address1' => $validated['address1'],
                'address2' => $validated['address2'] ?? null,
                'is_default' => false,
            ]);
        } catch (DomainRuleException) {
            // 개수 초과 등. 주문은 이미 성공했으므로 조용히 넘어간다.
        }
    }

    public function complete(Request $request): Response|RedirectResponse
    {
        $orderNo = $request->session()->get('recent_order_no');

        if (! is_string($orderNo)) {
            return redirect()->route('home');
        }

        $order = $this->orders->findByOrderNo($orderNo);

        if ($order === null) {
            return redirect()->route('home');
        }

        return Inertia::render('Store/Order/Complete', [
            'order' => $this->orders->present($order),
            // 무통장입금이면 계좌 안내를 함께 보여준다.
            // 문자·알림톡을 못 받았거나 지웠을 때 여기서 다시 확인할 수 있어야 한다.
            'deposit' => $this->payments->depositGuideFor($order->id),
        ]);
    }

    /** 회원 주문 목록. */
    public function index(Request $request): Response
    {
        return Inertia::render('Store/Order/Index', [
            'orders' => $this->orders->getUserOrders($request->user()->id),
        ]);
    }

    /** 비회원 주문조회 폼. */
    public function lookupForm(): Response
    {
        return Inertia::render('Store/Order/Lookup');
    }

    /**
     * 비회원 주문조회.
     *
     * 주문번호가 순차적이지 않더라도 대입 시도는 막아야 한다. 스로틀링을 건다 (§4.2).
     */
    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_no' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        $key = 'guest-order-lookup|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'order_no' => "시도 횟수를 초과했습니다. {$seconds}초 후 다시 시도해 주세요.",
            ]);
        }

        $order = $this->orders->findByOrderNo($validated['order_no']);

        // 주문번호 오류와 비밀번호 오류를 구분하지 않는다 — 주문 존재 여부를 흘리지 않는다.
        if ($order === null
            || ! $order->isGuest()
            || $order->guest_password === null
            || ! Hash::check($validated['password'], $order->guest_password)) {
            RateLimiter::hit($key);

            return back()->withErrors(['order_no' => '주문번호 또는 비밀번호가 올바르지 않습니다.']);
        }

        RateLimiter::clear($key);

        $request->session()->put('recent_order_no', $order->order_no);

        return redirect()->route('orders.complete');
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        // 회원은 자기 주문만 취소할 수 있다.
        $target = Order::query()->findOrFail($order);

        if ($target->user_id !== $request->user()?->id) {
            abort(403);
        }

        try {
            $this->orders->cancel($order);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '주문을 취소했습니다.');
    }
}
