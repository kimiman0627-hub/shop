<?php

declare(strict_types=1);

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 로그인 자체는 라우트 미들웨어가 막는다. 여기는 소유권만 본다(라이브러리에서).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:20'],
            'receiver_name' => ['required', 'string', 'max:50'],
            'receiver_phone' => ['required', 'string', 'max:20'],
            'postcode' => ['required', 'string', 'max:10'],
            'address1' => ['required', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => '별칭',
            'receiver_name' => '수령인',
            'receiver_phone' => '연락처',
            'postcode' => '우편번호',
            'address1' => '주소',
            'address2' => '상세주소',
        ];
    }
}
