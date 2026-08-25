<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
| 예약 만료 정리 (docs/schema-draft.md §7.3).
|
| **여기 등록만 해서는 아무것도 실행되지 않는다.** Laravel 은 스스로 시간을 재지 않는다.
| 1분마다 `schedule:run` 을 불러줄 무언가가 밖에 있어야 한다.
|
|   로컬     php artisan schedule:work     (별도 터미널. `artisan dev` 에는 포함되지 않는다)
|   운영     크론 / 작업 스케줄러 / supervisor 가 1분마다 `schedule:run`
|   수동     php artisan shop:expire-orders --check-drift
|
| 이게 돌지 않으면 결제창을 닫은 주문이 재고를 영원히 물고 있는다.
*/
Schedule::command('shop:expire-orders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
| 일별 매출 집계 (docs/tables.md §14).
|
| 두 번 돈다:
|   5분마다   최근 3일 — '오늘 매출' 이 너무 늦지 않게. 늦게 들어온 환불도 따라잡는다.
|   새벽 4시  최근 90일 — 과거 날짜가 뒤늦게 바뀌는 경우(반품 처리완료 등)를 훑는다.
|
| 재실행해도 값이 누적되지 않는다(날짜별 updateOrCreate).
| 이게 안 돌면 주문은 정상인데 **통계 화면만 과거에 멈춘다** — 화면의
| "집계 기준" 시각이 안 움직이면 스케줄러부터 확인할 것.
*/
Schedule::command('shop:aggregate-sales --days=3')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('shop:aggregate-sales --days=90')
    ->dailyAt('04:00')
    ->withoutOverlapping();
