<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Admin\AdminStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminAccountRequest extends FormRequest
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
        $adminId = $this->route('admin');
        $isCreate = $adminId === null;

        return [
            // 로그인 ID 는 생성 시에만 정한다. 이후 불변이다.
            'login_id' => $isCreate
                ? ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('admins', 'login_id')]
                : ['nullable'],

            'name' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'admin_role_id' => ['required', 'integer', Rule::exists('admin_roles', 'id')],
            'status' => ['required', Rule::enum(AdminStatus::class)],

            'password' => $isCreate
                ? ['required', 'string', Password::min(10)]
                : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login_id.regex' => '로그인 ID는 영문 소문자·숫자·언더스코어만 사용합니다.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login_id' => '로그인 ID',
            'name' => '이름',
            'email' => '이메일',
            'admin_role_id' => '역할',
            'status' => '상태',
            'password' => '비밀번호',
        ];
    }
}
