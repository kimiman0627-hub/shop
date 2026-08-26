<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Support\LocalTime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 "내 정보" 화면.
 *
 * 저장은 이 컨트롤러가 하지 않는다 — Fortify 가 이미 등록한
 * `PUT /user/profile-information` 로 폼이 직접 제출된다
 * (App\Actions\Fortify\UpdateUserProfileInformation 이 phone·수신동의까지 같이 받는다).
 * 여기는 화면을 그리기만 한다.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Store/Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'phone' => $user->phone,
                'marketing_email_agreed' => $user->hasAgreedToEmailMarketing(),
                'marketing_sms_agreed' => $user->hasAgreedToSmsMarketing(),
                'joined_at' => LocalTime::date($user->created_at),
                'last_login_at' => LocalTime::dateTime($user->last_login_at),
            ],
        ]);
    }
}
