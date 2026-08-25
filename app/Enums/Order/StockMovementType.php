<?php

declare(strict_types=1);

namespace App\Enums\Order;

/**
 * 재고 변동 사유 (docs/schema-draft.md §7.7). 값은 대문자 (CLAUDE.md §6.1).
 *
 * 실물(stock)과 예약(reserved) 두 축을 따로 기록한다.
 * 한 축만 남기면 "재고가 왜 이 숫자인가"의 절반만 설명된다.
 */
enum StockMovementType: string
{
    case RESERVE = 'RESERVE';
    case RELEASE = 'RELEASE';
    case SELL = 'SELL';
    case RESTOCK = 'RESTOCK';
    case MANUAL_IN = 'MANUAL_IN';
    case MANUAL_OUT = 'MANUAL_OUT';
    case ADJUST = 'ADJUST';

    public function label(): string
    {
        return match ($this) {
            self::RESERVE => '주문 예약',
            self::RELEASE => '예약 해제',
            self::SELL => '판매 확정',
            self::RESTOCK => '입고·반품',
            self::MANUAL_IN => '수동 입고',
            self::MANUAL_OUT => '수동 출고',
            self::ADJUST => '실사 반영',
        };
    }
}
