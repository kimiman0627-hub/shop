<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * slug 생성. 도메인에 묶이지 않는 헬퍼다 (CLAUDE.md §4.1).
 *
 * Str::slug 는 한글을 전부 버려 빈 문자열을 준다. 그대로 두면 모든 한글
 * 카테고리·상품이 'category-2', 'category-3' 이 된다. 그래서 한글이면
 * 공백만 하이픈으로 바꾸고 글자는 살린다 — URL 에서 퍼센트 인코딩되어 정상 동작한다.
 */
class SlugMaker
{
    /**
     * @param  callable(string): bool  $taken  후보 slug 가 이미 쓰이는지 판단
     */
    public function make(?string $preferred, string $fallbackSource, callable $taken): string
    {
        $source = ($preferred !== null && trim($preferred) !== '') ? $preferred : $fallbackSource;

        $base = $this->normalize($source);

        $candidate = $base;
        $suffix = 2;

        while ($taken($candidate)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    private function normalize(string $source): string
    {
        $slug = Str::slug($source);

        if ($slug !== '') {
            return $slug;
        }

        // 한글 등 Str::slug 가 버리는 문자열. 글자를 살린다.
        $slug = Str::lower(trim(preg_replace('/\s+/u', '-', $source) ?? ''));
        $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug) ?? '';

        return $slug !== '' ? $slug : 'item';
    }
}
