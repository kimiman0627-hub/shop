<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * 본인 비밀번호 변경. 현재 비밀번호 확인이 필수다 (CLAUDE.md §7.6 (2)).
 *
 * 세션이 탈취된 상태에서 비밀번호가 바뀌는 것을 막는다.
 */
class AdminOwnPasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:admin'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(10)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => '현재 비밀번호가 올바르지 않습니다.',
            'password.different' => '새 비밀번호가 현재 비밀번호와 같습니다.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => '현재 비밀번호',
            'password' => '새 비밀번호',
        ];
    }
}
