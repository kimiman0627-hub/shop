<?php

declare(strict_types=1);

namespace App\Enums\Product;

/**
 * 상품 후기 노출 상태. 값은 대문자 (CLAUDE.md §6.1).
 *
 * 후기는 삭제하지 않고 숨긴다 — 평점 이력과 구매 이력이 엮여 있고,
 * 지워버리면 "왜 평점이 갑자기 올랐나" 를 설명할 수 없다.
 */
enum ReviewStatus: string
{
    case PUBLISHED = 'PUBLISHED';
    case HIDDEN = 'HIDDEN';

    public function label(): string
    {
        return match ($this) {
            self::PUBLISHED => '노출',
            self::HIDDEN => '숨김',
        };
    }

    /** 평점 집계에 들어가는가. 숨긴 후기는 별점에서도 빠져야 한다. */
    public function countsForRating(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
