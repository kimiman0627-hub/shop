<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Fortify 가 기본 등록해주는 `PUT /user/profile-information` 경로를 그대로 쓴다.
 *
 * name·email 외에 phone·마케팅 수신동의를 여기서 같이 받는다 —
 * "내 정보" 화면(Store/Profile/Edit.vue) 폼 하나가 이 라우트 하나로 간다.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],

            'phone' => ['nullable', 'string', 'max:20'],
            'marketing_email_agreed' => ['nullable', 'boolean'],
            'marketing_sms_agreed' => ['nullable', 'boolean'],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }

        $this->updateMarketingConsent($user, $input);
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }

    /**
     * 동의 여부가 아니라 **동의 시각**을 남긴다.
     *
     * 껐다 켰다 해도 이전 동의 이력이 지워지진 않는다 — 지금 이 순간의 상태만
     * 컬럼에 반영되고, "언제 바꿨는지"는 어차피 이 저장 자체가 최신값이다.
     * 더 엄격한 이력이 필요해지면 별도 로그 테이블로 옮긴다.
     *
     * @param  array<string, mixed>  $input
     */
    private function updateMarketingConsent(User $user, array $input): void
    {
        $user->forceFill([
            'phone' => $input['phone'] ?? $user->phone,
            'marketing_email_agreed_at' => ($input['marketing_email_agreed'] ?? false)
                ? ($user->marketing_email_agreed_at ?? now())
                : null,
            'marketing_sms_agreed_at' => ($input['marketing_sms_agreed'] ?? false)
                ? ($user->marketing_sms_agreed_at ?? now())
                : null,
        ])->save();
    }
}
