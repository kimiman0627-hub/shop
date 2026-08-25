<?php

declare(strict_types=1);

namespace App\Enums\Order;

/**
 * 주문 상태 (docs/schema-draft.md §4.4). 값은 대문자 (CLAUDE.md §6.1).
 */
enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case PREPARING = 'PREPARING';
    case SHIPPING = 'SHIPPING';
    case DELIVERED = 'DELIVERED';
    case CANCELED = 'CANCELED';
    case REFUNDED = 'REFUNDED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '결제대기',
            self::PAID => '결제완료',
            self::PREPARING => '상품준비중',
            self::SHIPPING => '배송중',
            self::DELIVERED => '배송완료',
            self::CANCELED => '취소',
            self::REFUNDED => '환불완료',
        };
    }

    /**
     * 이 상태가 재고를 **예약**하고 있는가.
     *
     * PENDING 만 예약을 잡는다. 결제되면 예약이 실물 차감으로 바뀌고,
     * 취소되면 예약이 풀린다 (§7.2).
     */
    public function holdsReservation(): bool
    {
        return $this === self::PENDING;
    }

    /** 고객이 스스로 취소할 수 있는 상태인가. */
    public function isCancelableByCustomer(): bool
    {
        return in_array($this, [self::PENDING, self::PAID], true);
    }

    /**
     * 관리자가 취소할 수 있는 상태인가.
     *
     * 고객보다 한 단계 넓다 — 상품준비중까지는 아직 물건이 안 나갔으므로 되돌릴 수 있다.
     * **출고(SHIPPING) 이후는 취소가 아니라 반품·환불 절차다.** 여기서 막는다.
     */
    public function isCancelableByAdmin(): bool
    {
        return in_array($this, [self::PENDING, self::PAID, self::PREPARING], true);
    }

    /** 더 이상 진행되지 않는 종료 상태인가. */
    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::CANCELED, self::REFUNDED], true);
    }

    /**
     * **매출로 잡히는가.** 통계·인기상품 집계의 기준이다.
     *
     * 결제 전(PENDING)은 아직 돈이 안 들어왔고, 취소·환불은 돈이 나갔다.
     * 이걸 안 걸르면 장바구니만 담고 안 산 주문이 '인기 상품' 이 된다.
     */
    public function countsAsSale(): bool
    {
        return in_array(
            $this,
            [self::PAID, self::PREPARING, self::SHIPPING, self::DELIVERED],
            true,
        );
    }

    /**
     * 매출로 잡히는 상태값 목록. `whereIn` 에 바로 넣는다.
     *
     * @return list<string>
     */
    public static function saleValues(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->countsAsSale()),
        ));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
