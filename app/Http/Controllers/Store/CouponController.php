<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Order\CouponLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 쿠폰함.
 *
 * 비회원은 쿠폰을 쓸 수 없다 — user_coupons.user_id 가 필수다 (schema-draft.md §8.3).
 * 그래서 이 라우트만 auth 미들웨어를 건다.
 */
class CouponController extends Controller
{
    public function __construct(
        private readonly CouponLibrary $coupons,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Store/Coupon/Index', [
            'coupons' => $this->coupons->myCoupons($request->user()->id),
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
        ]);

        try {
            $this->coupons->redeemByCode($request->user()->id, $validated['code']);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '쿠폰을 받았습니다.');
    }
}
