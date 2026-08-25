<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Coupon\CouponDiscountType;
use App\Enums\Coupon\CouponIssueType;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Libraries\Order\CouponLibrary;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 프로모션 > 쿠폰관리 (menu_code: COUPON_LIST).
 */
class CouponController extends Controller
{
    public function __construct(
        private readonly CouponLibrary $coupons,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Coupon/Index', [
            'coupons' => $this->coupons->getAdminList(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Coupon/Edit', [
            'coupon' => null,
            'issued' => [],
            ...$this->formOptions(),
        ]);
    }

    public function edit(int $coupon): Response
    {
        return Inertia::render('Admin/Coupon/Edit', [
            'coupon' => $this->coupons->getDetail($coupon),
            'issued' => $this->coupons->issuedList($coupon),
            ...$this->formOptions(),
        ]);
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        try {
            $coupon = $this->coupons->create($request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.coupons.edit', $coupon->id)
            ->with('status', '쿠폰을 만들었습니다.');
    }

    public function update(CouponRequest $request, int $coupon): RedirectResponse
    {
        try {
            $this->coupons->update($coupon, $request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '쿠폰을 수정했습니다.');
    }

    /**
     * 쿠폰은 삭제하지 않고 내린다 (docs/schema-draft.md §8.2).
     */
    public function destroy(int $coupon): RedirectResponse
    {
        $this->coupons->deactivate($coupon);

        return back()->with('status', '쿠폰을 중지했습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'issueTypeOptions' => array_map(
                fn (CouponIssueType $t) => ['value' => $t->value, 'label' => $t->label()],
                CouponIssueType::cases(),
            ),
            'discountTypeOptions' => array_map(
                fn (CouponDiscountType $t) => ['value' => $t->value, 'label' => $t->label(), 'unit' => $t->unit()],
                CouponDiscountType::cases(),
            ),
        ];
    }
}
