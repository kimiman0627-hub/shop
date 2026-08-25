<?php

declare(strict_types=1);

namespace App\Enums\Returns;

/**
 * 반품 사유. 값은 대문자 (CLAUDE.md §6.1).
 *
 * 사유가 귀책(누가 배송비를 부담하는가)을 결정한다.
 */
enum ReturnReason: string
{
    case CHANGE_OF_MIND = 'CHANGE_OF_MIND';
    case SIZE_OR_COLOR = 'SIZE_OR_COLOR';
    case DEFECTIVE = 'DEFECTIVE';
    case WRONG_DELIVERY = 'WRONG_DELIVERY';
    case DAMAGED = 'DAMAGED';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CHANGE_OF_MIND => '단순 변심',
            self::SIZE_OR_COLOR => '사이즈·색상 변경',
            self::DEFECTIVE => '상품 불량',
            self::WRONG_DELIVERY => '오배송',
            self::DAMAGED => '배송 중 파손',
            self::OTHER => '기타',
        };
    }

    /**
     * 기본 귀책. 관리자가 승인 시 바꿀 수 있다 —
     * '기타'는 내용을 봐야 하고, 변심으로 접수됐지만 실제로는 불량인 경우도 있다.
     */
    public function defaultResponsibility(): ReturnResponsibility
    {
        return match ($this) {
            self::CHANGE_OF_MIND, self::SIZE_OR_COLOR, self::OTHER => ReturnResponsibility::CUSTOMER,
            self::DEFECTIVE, self::WRONG_DELIVERY, self::DAMAGED => ReturnResponsibility::SELLER,
        };
    }

    /**
     * 재판매 가능한 사유인가. 불량·파손품을 재고로 되돌리면 다시 팔린다.
     */
    public function defaultRestockable(): bool
    {
        return match ($this) {
            self::DEFECTIVE, self::DAMAGED => false,
            default => true,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
