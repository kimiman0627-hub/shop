<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * 고객 로그인 시각 기록.
 *
 * `admins.last_login_at` 은 관리자 로그인 컨트롤러가 직접 찍지만(수동 인증이라 §12.3),
 * 고객은 Fortify 가 인증을 처리해서 컨트롤러 자체가 없다 — 대신 Laravel 이 표준으로 쏘는
 * `Login` 이벤트를 듣는다.
 *
 * **등록은 자동 등록에 맡긴다.** `app/Listeners` 안의 클래스는 Laravel 11+ 가
 * `handle()` 의 타입힌트를 보고 스스로 배선한다(`MergeGuestCartOnLogin` 과 동일).
 * `AppServiceProvider` 에 `Event::listen()` 을 또 추가하면 **같은 로그인에 두 번
 * 불린다** — 실제로 그렇게 됐다가 `php artisan event:list` 로 중복 등록을 발견해 지웠다.
 *
 * **`web` 가드일 때만 처리한다.** 이 이벤트는 관리자 로그인(`admin` 가드)에도 같이 쏘아지는데,
 * `admins.last_login_at` 은 `AdminLoginController` 가 이미 직접 찍고 있다 — 여기서 또 손대면
 * 고객·관리자 테이블 분리 원칙(CLAUDE.md §7.1)이 코드 레벨에서 흐려진다.
 */
class RecordUserLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->save();
    }
}
