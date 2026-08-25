<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 로그인. Fortify 는 단일 가드(web) 전용이라 여기는 직접 구현한다 (CLAUDE.md §12).
 *
 * 관리자 계정은 상위 관리자가 생성한다. 공개 회원가입 라우트는 만들지 않는다 (§7.1).
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = [
            'login_id' => $request->input('login_id'),
            'password' => $request->input('password'),
        ];

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey());

            // 존재하지 않는 ID 와 비밀번호 오류를 같은 메시지로 처리한다 — 계정 열거 방지.
            throw ValidationException::withMessages([
                'login_id' => '로그인 ID 또는 비밀번호가 올바르지 않습니다.',
            ]);
        }

        $admin = Auth::guard('admin')->user();

        // 정지된 계정은 인증에 성공해도 들여보내지 않는다.
        if (! $admin->canLogin()) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'login_id' => '정지된 계정입니다. 관리자에게 문의하세요.',
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        // 세션 고정 공격 방어.
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function ensureIsNotRateLimited(AdminLoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($request->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($request->throttleKey());

        throw ValidationException::withMessages([
            'login_id' => "시도 횟수를 초과했습니다. {$seconds}초 후 다시 시도해 주세요.",
        ]);
    }
}
