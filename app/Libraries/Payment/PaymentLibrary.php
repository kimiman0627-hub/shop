<?php

declare(strict_types=1);

namespace App\Libraries\Payment;

use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\DomainRuleException;
use App\Libraries\Order\OrderLibrary;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 결제 (docs/schema-draft.md §5).
 *
 * 현재는 무통장입금만 지원한다. PG 연동은 이 클래스에 메서드를 더하는 방식으로 붙인다.
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class PaymentLibrary
{
    public function __construct(
        private readonly BankAccountLibrary $accounts,
        private readonly DepositNotifier $notifier,
        private readonly OrderLibrary $orders,
    ) {}

    /**
     * 무통장입금 요청을 만든다.
     *
     * 계좌 정보를 payments 에 **스냅샷**으로 복사한다.
     * 관리자가 나중에 계좌를 바꿔도 이미 안내한 내용은 변하면 안 된다.
     */
    public function requestBankTransfer(Order $order, ?string $depositorName = null): Payment
    {
        if ($order->status !== OrderStatus::PENDING) {
            throw new DomainRuleException('결제대기 상태의 주문만 결제를 요청할 수 있습니다.');
        }

        $account = $this->accounts->defaultAccount();

        $payment = DB::transaction(function () use ($order, $account, $depositorName) {
            // 같은 주문에 살아 있는 요청이 있으면 새로 만들지 않는다.
            // 새로고침 때마다 결제 행이 쌓이는 것을 막는다.
            $existing = Payment::query()
                ->where('order_id', $order->id)
                ->inFlight()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return Payment::query()->create([
                'order_id' => $order->id,
                'method' => PaymentMethod::BANK_TRANSFER,
                'status' => PaymentStatus::READY,
                'amount' => $order->total_amount,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'holder_name' => $account->holder_name,
                'depositor_name' => $depositorName ?: $order->orderer_name,
                'requested_at' => now(),
            ]);
        });

        // 안내 발송은 트랜잭션 밖이다. 발송이 느리거나 실패해도 주문은 이미 안전하다.
        $this->notifier->send($payment->load('order'));

        return $payment;
    }

    /**
     * 관리자가 입금을 확인했다. 여기서 주문이 결제완료가 된다.
     *
     * 은행 앱에서 눈으로 확인한 뒤 누르는 버튼이므로,
     * **누가 눌렀는지 반드시 남긴다.** 수동 처리는 책임 소재가 중요하다.
     */
    public function confirmDeposit(int $paymentId, int $adminId, string $memo = ''): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminId, $memo) {
            $payment = Payment::query()->with('order')->lockForUpdate()->findOrFail($paymentId);

            if ($payment->status !== PaymentStatus::READY) {
                throw new DomainRuleException(
                    "이미 처리된 결제입니다. (현재: {$payment->status->label()})",
                );
            }

            if ($payment->method !== PaymentMethod::BANK_TRANSFER) {
                throw new DomainRuleException('무통장입금 결제만 수동 확인할 수 있습니다.');
            }

            // 주문이 그 사이 취소됐을 수 있다. 취소된 주문을 결제완료로 만들면 안 된다.
            if ($payment->order->status !== OrderStatus::PENDING) {
                throw new DomainRuleException(
                    "주문이 {$payment->order->status->label()} 상태입니다. 입금 확인 전에 주문 상태를 확인하세요.",
                );
            }

            // 재고 예약을 실물 차감으로 바꾼다.
            $this->orders->markPaid($payment->order_id);

            $payment->forceFill([
                'status' => PaymentStatus::PAID,
                'paid_at' => now(),
                'confirmed_by_admin_id' => $adminId,
                'memo' => $memo !== '' ? $memo : null,
            ])->save();

            return $payment;
        });
    }

    /**
     * 입금이 안 들어와 결제를 취소한다. 주문도 함께 취소된다.
     */
    public function cancel(int $paymentId, string $memo = ''): void
    {
        DB::transaction(function () use ($paymentId, $memo) {
            $payment = Payment::query()->with('order')->lockForUpdate()->findOrFail($paymentId);

            if ($payment->status !== PaymentStatus::READY) {
                throw new DomainRuleException('입금대기 상태의 결제만 취소할 수 있습니다.');
            }

            $payment->forceFill([
                'status' => PaymentStatus::CANCELED,
                'canceled_at' => now(),
                'memo' => $memo !== '' ? $memo : null,
            ])->save();

            if ($payment->order->status === OrderStatus::PENDING) {
                $this->orders->cancel($payment->order_id, $memo !== '' ? $memo : '입금 미확인 취소');
            }
        });
    }

    // ------------------------------------------------------------- 조회

    /**
     * 관리자 무통장처리 목록.
     *
     * @param  array{status?: string|null, keyword?: string|null}  $filters
     */
    public function getDepositList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = $filters['status'] ?? PaymentStatus::READY->value;

        return Payment::query()
            ->where('method', PaymentMethod::BANK_TRANSFER->value)
            ->with(['order', 'confirmedBy:id,name'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($keyword !== '', fn ($q) => $q->where(
                fn ($sub) => $sub
                    ->whereLike('depositor_name', '%'.$keyword.'%', caseSensitive: false)
                    ->orWhereHas('order', fn ($o) => $o
                        ->whereLike('order_no', '%'.$keyword.'%', caseSensitive: false)),
            ))
            // 오래 기다린 건이 위로 온다.
            ->orderBy('requested_at')
            ->paginate(config('shop.per_page.order'))
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'order_id' => $p->order_id,
                'order_no' => $p->order?->order_no,
                'orderer_name' => $p->order?->orderer_name,
                'orderer_phone' => $p->order?->orderer_phone,
                'depositor_name' => $p->depositor_name,
                'amount' => $p->amount,
                'account_label' => $p->accountLabel(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'order_status_label' => $p->order?->status->label(),
                'requested_at' => $p->requested_at->toDateTimeString(),
                'due_at' => $p->order?->payment_due_at?->toDateTimeString(),
                'overdue' => $p->status === PaymentStatus::READY
                    && $p->order?->payment_due_at?->isPast() === true,
                'paid_at' => $p->paid_at?->toDateTimeString(),
                'confirmed_by' => $p->confirmedBy?->name,
                'memo' => $p->memo,
            ]);
    }

    /**
     * 고객 주문완료 화면에 보여줄 입금 안내.
     *
     * @return array<string, mixed>|null
     */
    public function depositGuideFor(int $orderId): ?array
    {
        $payment = Payment::query()
            ->where('order_id', $orderId)
            ->where('method', PaymentMethod::BANK_TRANSFER->value)
            ->orderByDesc('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        return [
            'bank_name' => $payment->bank_name,
            'account_number' => $payment->account_number,
            'holder_name' => $payment->holder_name,
            'depositor_name' => $payment->depositor_name,
            'amount' => $payment->amount,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
        ];
    }
}
