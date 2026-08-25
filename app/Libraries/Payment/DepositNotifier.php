<?php

declare(strict_types=1);

namespace App\Libraries\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * 입금 안내 발송.
 *
 * **지금은 실제로 아무 데도 보내지 않는다.** 카카오 알림톡·SMS 사업자가 없어
 * `storage/logs/laravel.log` 에만 남긴다. 로컬 메일이 log 드라이버인 것과 같은 방식이다.
 *
 * 실제 발송을 붙일 때:
 *   1. sendViaKakao() / sendViaSms() 를 구현한다
 *   2. config('shop.payment.notify_channels') 에 채널명을 추가한다
 *   3. 호출부(PaymentLibrary)는 건드리지 않는다
 *
 * 발송 실패가 주문을 막지 않는다 — 안내는 부수 작업이고,
 * 계좌 정보는 주문완료 화면에도 표시되기 때문이다.
 */
class DepositNotifier
{
    public function send(Payment $payment): void
    {
        $order = $payment->order;

        $message = $this->buildMessage($payment);

        foreach (config('shop.payment.notify_channels', ['log']) as $channel) {
            try {
                match ($channel) {
                    'log' => $this->sendViaLog($payment, $message),
                    // 'kakao' => $this->sendViaKakao($payment, $message),
                    // 'sms' => $this->sendViaSms($payment, $message),
                    default => Log::warning('알 수 없는 입금 안내 채널', ['channel' => $channel]),
                };
            } catch (\Throwable $e) {
                Log::error('입금 안내 발송 실패', [
                    'channel' => $channel,
                    'order_no' => $order?->order_no,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 고객에게 보낼 문구. 채널이 달라도 내용은 같다.
     */
    public function buildMessage(Payment $payment): string
    {
        $order = $payment->order;
        $amount = number_format($payment->amount);
        $due = $order?->payment_due_at?->format('Y-m-d H:i');

        return implode("\n", array_filter([
            '[주문 접수] 입금 안내',
            "주문번호: {$order?->order_no}",
            "입금액: {$amount}원",
            "입금계좌: {$payment->accountLabel()}",
            $payment->depositor_name !== null ? "입금자명: {$payment->depositor_name}" : null,
            $due !== null ? "입금기한: {$due}" : null,
            '기한 내 미입금 시 주문이 자동 취소됩니다.',
        ]));
    }

    private function sendViaLog(Payment $payment, string $message): void
    {
        Log::channel(config('logging.default'))->info(
            "[입금안내 발송(모의)] 수신자={$payment->order?->orderer_phone}\n".$message,
        );
    }
}
