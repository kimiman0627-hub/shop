<?php

declare(strict_types=1);

namespace App\Enums\Product;

/**
 * 상품 상태 (docs/schema-draft.md §2.5). 값은 대문자 (CLAUDE.md §6.1).
 *
 * SOLD_OUT 은 재고에서 파생되지 않는다. 관리자가 명시적으로 거는 상태다.
 * 재고와 자동 연동시키면 결제 취소 한 건에 상품이 품절로 바뀌고 되살아나지 않는다.
 */
enum ProductStatus: string
{
    case DRAFT = 'DRAFT';
    case ON_SALE = 'ON_SALE';
    case SOLD_OUT = 'SOLD_OUT';
    case HIDDEN = 'HIDDEN';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => '판매 준비중',
            self::ON_SALE => '판매중',
            self::SOLD_OUT => '품절',
            self::HIDDEN => '숨김',
        };
    }

    /** 고객에게 노출되는가. */
    public function isVisible(): bool
    {
        return in_array($this, [self::ON_SALE, self::SOLD_OUT], true);
    }

    /** 구매 가능한가. 재고는 별도로 확인한다. */
    public function isPurchasable(): bool
    {
        return $this === self::ON_SALE;
    }
}
