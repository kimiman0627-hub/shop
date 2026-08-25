<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Payment\PaymentLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 결제 > 무통장처리 (menu_code: PAYMENT_DEPOSIT).
 *
 * 관리자가 은행 앱에서 입금을 눈으로 확인한 뒤 여기서 결제완료 처리한다.
 * 수동 처리이므로 **누가 눌렀는지 반드시 기록된다.**
 */
class DepositController extends Controller
{
    public function __construct(
        private readonly PaymentLibrary $payments,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString() ?: PaymentStatus::READY->value,
            'keyword' => $request->string('keyword')->toString(),
        ];

        return Inertia::render('Admin/Payment/Deposit/Index', [
            'payments' => $this->payments->getDepositList($filters),
            'filters' => $filters,
            'statusOptions' => array_map(
                fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                PaymentStatus::cases(),
            ),
        ]);
    }

    public function confirm(Request $request, int $payment): RedirectResponse
    {
        $validated = $request->validate([
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->payments->confirmDeposit(
                $payment,
                $request->user('admin')->id,
                $validated['memo'] ?? '',
            );
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '입금을 확인하고 결제완료 처리했습니다.');
    }

    public function cancel(Request $request, int $payment): RedirectResponse
    {
        $validated = $request->validate([
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->payments->cancel($payment, $validated['memo'] ?? '입금 미확인 취소');
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '결제와 주문을 취소했습니다.');
    }
}
