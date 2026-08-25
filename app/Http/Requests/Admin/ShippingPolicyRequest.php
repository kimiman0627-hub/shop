<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShippingPolicyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],

            // 금액은 원 단위 정수. 소수점을 받지 않는다 (CLAUDE.md §5.3).
            'base_fee' => ['required', 'integer', 'min:0', 'max:10000000'],
            'free_threshold' => ['nullable', 'integer', 'min:1', 'max:100000000'],

            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '정책명',
            'base_fee' => '기본 배송비',
            'free_threshold' => '무료배송 기준금액',
            'is_default' => '기본 정책',
            'is_active' => '사용 여부',
        ];
    }
}
