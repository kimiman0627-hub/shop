<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Setting;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Payment\BankAccountLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 설정 > 입금계좌 (menu_code: SETTING_BANK).
 *
 * 여기 설정한 기본 계좌가 고객 주문 시 안내된다.
 */
class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountLibrary $accounts,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Setting/Bank/Index', [
            'accounts' => $this->accounts->getList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $this->accounts->create($this->validated($request));
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '계좌를 추가했습니다.');
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        try {
            $this->accounts->update($account, $this->validated($request));
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '계좌를 수정했습니다.');
    }

    public function destroy(int $account): RedirectResponse
    {
        try {
            $this->accounts->delete($account);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '계좌를 삭제했습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'bank_name' => ['required', 'string', 'max:30'],
            // 계좌번호는 하이픈을 허용한다. 숫자만 강제하면 은행별 표기를 못 담는다.
            'account_number' => ['required', 'string', 'max:40', 'regex:/^[0-9\-]+$/'],
            'holder_name' => ['required', 'string', 'max:50'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ], [
            'account_number.regex' => '계좌번호는 숫자와 하이픈만 입력할 수 있습니다.',
        ]);
    }
}
