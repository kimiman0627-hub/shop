<?php

declare(strict_types=1);

namespace App\Enums\Order;

/**
 * 배송 상태 (docs/schema-draft.md §6.3). 값은 대문자 (CLAUDE.md §6.1).
 *
 * 주문 상태(OrderStatus)와 다른 축이다.
 * 주문은 결제·취소까지 포함한 전체 흐름이고, 이건 **물건 자체의 이동**만 본다.
 */
enum ShipmentStatus: string
{
    case READY = 'READY';
    case SHIPPING = 'SHIPPING';
    case DELIVERED = 'DELIVERED';

    public function label(): string
    {
        return match ($this) {
            self::READY => '배송준비',
            self::SHIPPING => '배송중',
            self::DELIVERED => '배송완료',
        };
    }

    /** 송장이 찍혀 실제로 출고된 뒤인가. */
    public function isDispatched(): bool
    {
        return $this !== self::READY;
    }
}
