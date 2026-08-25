<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * 최근 본 상품 — 쿠키에 상품 id 만 담는다.
 *
 * **DB 를 쓰지 않는 이유:** 비회원도 동작해야 하고, 로그인 전후로 끊기면
 * 오히려 이상하다. 개인정보라 할 것도 없는 목록이라 브라우저에 두는 편이 맞다.
 * 기기 간 동기화가 필요해지면 그때 회원용 테이블을 더한다.
 *
 * **이 클래스는 `app/Support` 에 있다.** Request·Cookie 에 의존하므로
 * 라이브러리 규칙(§4.2)상 `app/Libraries` 에 둘 수 없다.
 * 라이브러리에는 여기서 꺼낸 id 배열만 넘긴다.
 */
class RecentlyViewed
{
    private const COOKIE = 'recently_viewed';

    /** 너무 길면 쿠키가 커지고 화면에도 다 못 쓴다. */
    private const MAX = 12;

    private const DAYS = 30;

    /**
     * 쿠키에서 상품 id 를 읽는다. 최근 본 것이 앞이다.
     *
     * 쿠키 값은 사용자가 고칠 수 있다 — **정수만 남기고 전부 버린다.**
     *
     * @return list<int>
     */
    public static function ids(Request $request): array
    {
        $raw = $request->cookie(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn (string $id) => (int) trim($id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->take(self::MAX)
            ->values()
            ->all();
    }

    /**
     * 방금 본 상품을 맨 앞으로 올린다.
     *
     * 응답에 붙일 쿠키를 큐에 넣는다 — 컨트롤러가 따로 할 일이 없다.
     */
    public static function push(Request $request, int $productId): void
    {
        $ids = array_values(array_filter(
            self::ids($request),
            fn (int $id) => $id !== $productId,
        ));

        array_unshift($ids, $productId);

        Cookie::queue(
            self::COOKIE,
            implode(',', array_slice($ids, 0, self::MAX)),
            // 분 단위다. 초로 착각하면 30일이 30분이 된다.
            self::DAYS * 24 * 60,
        );
    }

    /** 목록에서 하나 뺀다. 고객이 화면에서 지울 때 쓴다. */
    public static function forget(Request $request, int $productId): void
    {
        $ids = array_values(array_filter(
            self::ids($request),
            fn (int $id) => $id !== $productId,
        ));

        Cookie::queue(self::COOKIE, implode(',', $ids), self::DAYS * 24 * 60);
    }

    public static function clear(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
