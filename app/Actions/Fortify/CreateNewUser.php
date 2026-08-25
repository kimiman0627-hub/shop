<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),

            // 전화번호도 수신동의도 필수가 아니다. 가입 자체를 막을 이유가 없다 —
            // 나중에 "내 정보" 에서 언제든 채울 수 있다.
            'phone' => ['nullable', 'string', 'max:20'],
            'marketing_email_agreed' => ['nullable', 'boolean'],
            'marketing_sms_agreed' => ['nullable', 'boolean'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'phone' => $input['phone'] ?? null,
        ]);

        /*
         * `marketing_*_agreed_at` 은 User::$fillable 에 일부러 없다(App\Models\User 참고) —
         * 그래서 위 create() 배열에 넣어도 **조용히 무시된다** (docs/worklog.md #8 과 같은 함정).
         * forceFill 로 따로 쓴다. 가입 시점에 동의했으면 그 시각을 바로 찍는다.
         */
        $user->forceFill([
            'marketing_email_agreed_at' => ($input['marketing_email_agreed'] ?? false) ? now() : null,
            'marketing_sms_agreed_at' => ($input['marketing_sms_agreed'] ?? false) ? now() : null,
        ])->save();

        return $user;
    }
}
