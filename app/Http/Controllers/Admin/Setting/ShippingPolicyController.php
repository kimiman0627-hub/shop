<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Setting;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingPolicyRequest;
use App\Libraries\Shipping\ShippingPolicyLibrary;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 설정 > 배송비설정 (menu_code: SETTING_SHIPPING).
 *
 * 컨트롤러는 요청 받기 → 라이브러리 호출 → 응답만 한다 (CLAUDE.md §4.2).
 */
class ShippingPolicyController extends Controller
{
    public function __construct(
        private readonly ShippingPolicyLibrary $policies,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Setting/Shipping/Index', [
            'policies' => $this->policies->getList(),
        ]);
    }

    public function store(ShippingPolicyRequest $request): RedirectResponse
    {
        try {
            $this->policies->create($request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '배송비 정책을 추가했습니다.');
    }

    public function update(ShippingPolicyRequest $request, int $policy): RedirectResponse
    {
        try {
            $this->policies->update($policy, $request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '배송비 정책을 수정했습니다.');
    }

    public function destroy(int $policy): RedirectResponse
    {
        try {
            $this->policies->delete($policy);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '배송비 정책을 삭제했습니다.');
    }
}
