<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
        $roleId = $this->route('role');

        // 생성일 때만 code 를 받는다. 수정 시 code 는 불변이다 (CLAUDE.md §7.3).
        $codeRules = $roleId === null
            ? ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('admin_roles', 'code')]
            : ['nullable'];

        return [
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => '역할 코드는 영문 대문자로 시작하고 대문자·숫자·언더스코어만 사용합니다. (예: MANAGER)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => '역할 코드',
            'name' => '역할 이름',
            'description' => '설명',
        ];
    }
}
