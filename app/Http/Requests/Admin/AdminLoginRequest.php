<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
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
            'login_id' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login_id' => '로그인 ID',
            'password' => '비밀번호',
        ];
    }

    /**
     * 스로틀링 키. 로그인 ID + IP 조합으로 잠근다.
     */
    public function throttleKey(): string
    {
        return strtolower((string) $this->input('login_id')).'|'.$this->ip();
    }
}
