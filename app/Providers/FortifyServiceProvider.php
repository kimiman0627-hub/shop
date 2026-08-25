<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

/**
 * 고객(web 가드) 인증만 담당한다.
 *
 * Fortify 는 단일 가드 전용이다(config/fortify.php 의 'guard' => 'web').
 * 관리자(admin 가드) 인증은 Fortify 를 쓰지 않고 직접 구현한다 — CLAUDE.md §12.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Fortify 가 등록하는 GET 라우트를 Inertia 페이지로 연결한다.
        // 화면은 resources/js/Pages/Store/Auth/ 에 있다.
        Fortify::loginView(fn () => Inertia::render('Store/Auth/Login', [
            'status' => session('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('Store/Auth/Register'));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Store/Auth/ForgotPassword', [
            'status' => session('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Store/Auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
        ]));

        Fortify::verifyEmailView(fn () => Inertia::render('Store/Auth/VerifyEmail', [
            'status' => session('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('Store/Auth/ConfirmPassword'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
