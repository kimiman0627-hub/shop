<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * 화면에 찍을 시각을 영업 시간대로 바꾼다.
 *
 * **DB 는 UTC 로 저장한다**(`config('app.timezone')` = UTC, 전 테이블 공통 규칙).
 * 그대로 화면에 내보내면 한국 사용자에게 9시간 어긋나 보인다 — 주문을 밤 10시에
 * 넣었는데 오후 1시로 찍히는 식이다.
 *
 * **저장 시각을 KST 로 바꾸는 방법은 쓰지 않는다.** `config('app.timezone')` 을
 * Asia/Seoul 로 바꾸면 새로 쌓이는 값과 기존 값의 기준이 달라져 과거 데이터가
 * 통째로 9시간 틀어진다. 저장은 UTC 그대로 두고 **내보낼 때만** 바꾼다.
 *
 * 라이브러리가 배열로 내려주는 모든 `*_at` 값이 여기를 거친다.
 * 통계의 날짜 경계(`StatLibrary`)는 이미 자체적으로 시간대를 다루므로 예외다.
 */
final class LocalTime
{
    /** '2026-08-25 22:47' — 날짜+시각. 초는 화면에서 의미가 없어 버린다. */
    public static function dateTime(?DateTimeInterface $at): ?string
    {
        return self::format($at, 'Y-m-d H:i');
    }

    /** '2026-08-25' — 날짜만. */
    public static function date(?DateTimeInterface $at): ?string
    {
        return self::format($at, 'Y-m-d');
    }

    private static function format(?DateTimeInterface $at, string $format): ?string
    {
        if ($at === null) {
            return null;
        }

        return Carbon::instance($at)
            ->timezone(config('shop.timezone'))
            ->format($format);
    }
}
