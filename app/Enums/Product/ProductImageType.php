<?php

declare(strict_types=1);

namespace App\Enums\Product;

/**
 * 상품 이미지 용도. 값은 대문자 (CLAUDE.md §6.1).
 *
 * 같은 테이블을 쓰지만 화면에서의 역할이 완전히 다르다.
 * 섞이면 목록 썸네일에 상세 설명 이미지가 뜨는 사고가 난다.
 */
enum ProductImageType: string
{
    /** 상단 갤러리. 첫 장이 목록 썸네일이 된다. */
    case GALLERY = 'GALLERY';

    /** 상세 설명 영역에 세로로 이어 붙이는 이미지. */
    case DETAIL = 'DETAIL';

    public function label(): string
    {
        return match ($this) {
            self::GALLERY => '대표·갤러리',
            self::DETAIL => '상세 이미지',
        };
    }

    /** 대표 이미지(썸네일) 개념이 있는가. 상세 이미지는 순서만 있다. */
    public function hasPrimary(): bool
    {
        return $this === self::GALLERY;
    }

    /** 용도마다 장수 제한이 다르다. 상세는 길게 이어 붙이는 게 보통이다. */
    public function maxCount(): int
    {
        return match ($this) {
            self::GALLERY => (int) config('shop.image.max_per_product', 10),
            self::DETAIL => (int) config('shop.image.max_detail_per_product', 20),
        };
    }
}
