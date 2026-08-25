<?php

declare(strict_types=1);

namespace App\Enums\Payment;

/**
 * 결제 시도의 상태 (docs/schema-draft.md §5.1). 값은 대문자 (CLAUDE.md §6.1).
 *
 * 주문 상태(OrderStatus)와 다른 축이다.
 * 주문은 하나지만 결제 시도는 여러 번일 수 있다 — 카드가 거절되고 재시도하는 경우.
 */
enum PaymentStatus: string
{
    case READY = 'READY';
    case PAID = 'PAID';
    case FAILED = 'FAILED';
    case CANCELED = 'CANCELED';
    case REFUNDED = 'REFUNDED';

    public function label(): string
    {
        return match ($this) {
            self::READY => '입금대기',
            self::PAID => '결제완료',
            self::FAILED => '실패',
            self::CANCELED => '취소',
            self::REFUNDED => '환불',
        };
    }

    /**
     * 이 시도가 아직 진행 중인가.
     *
     * 진행 중인 결제가 있는 주문은 예약 만료로 취소하지 않는다.
     * 그러지 않으면 "돈은 받았는데 재고는 남에게 넘어간" 상태가 된다.
     */
    public function isInFlight(): bool
    {
        return $this === self::READY;
    }
}
