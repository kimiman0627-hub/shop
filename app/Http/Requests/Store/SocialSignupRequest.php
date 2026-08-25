<?php

declare(strict_types=1);

namespace App\Http\Requests\Store;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 간편로그인 추가입력 — 제공자가 안 준 항목만 받는다.
 *
 * 소셜 신원(provider·provider_user_id)은 **여기서 받지 않는다.** 세션에만 있다
 * (SocialLoginController::PENDING_SESSION_KEY) — 폼으로 받으면 남의 소셜 계정을
 * 자기 것이라고 주장할 수 있다.
 */
class SocialSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // 기본 문구("has already been taken")로는 뭘 해야 할지 알 수 없다.
            // 여기서 막는 이유가 보안이라(SocialLoginLibrary 참고 — 입력한 이메일은
            // 검증된 적이 없으므로 기존 계정에 붙이지 않는다) 다음 행동까지 알려준다.
            'email.unique' => '이미 사용 중인 이메일입니다. 해당 계정으로 로그인한 뒤 연동해 주세요.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '이름',
            'email' => '이메일',
        ];
    }
}
