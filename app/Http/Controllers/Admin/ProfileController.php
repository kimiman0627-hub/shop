<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminOwnPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 로그인한 관리자 본인의 계정 관리.
 *
 * 메뉴 권한(SETTING_ADMIN)과 무관하다 — 권한이 없는 관리자도
 * 자기 비밀번호는 바꿀 수 있어야 한다.
 */
class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Profile/Edit');
    }

    public function updatePassword(AdminOwnPasswordRequest $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $admin->update(['password' => $request->validated()['password']]);

        // 비밀번호가 바뀌었으니 세션을 새로 판다.
        $request->session()->regenerate();

        return back()->with('status', '비밀번호를 변경했습니다.');
    }
}
