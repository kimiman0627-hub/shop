<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Coupon\CouponDiscountType;
use App\Enums\Coupon\CouponIssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
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
            'code' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($this->route('coupon')),
            ],
            'name' => ['required', 'string', 'max:50'],
            'issue_type' => ['required', Rule::enum(CouponIssueType::class)],
            'discount_type' => ['required', Rule::enum(CouponDiscountType::class)],

            'discount_value' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'max_discount_amount' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'min_order_amount' => ['required', 'integer', 'min:0', 'max:1000000000'],

            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],

            'total_issue_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => '쿠폰 코드는 영문·숫자·하이픈·언더스코어만 사용합니다.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => '쿠폰 코드',
            'name' => '쿠폰명',
            'issue_type' => '발급 방식',
            'discount_type' => '할인 방식',
            'discount_value' => '할인 값',
            'max_discount_amount' => '최대 할인금액',
            'min_order_amount' => '최소 주문금액',
            'valid_days' => '유효일수',
            'valid_from' => '발급 시작일',
            'valid_until' => '발급 종료일',
            'total_issue_limit' => '총 발급 한도',
            'per_user_limit' => '1인당 발급 한도',
        ];
    }
}
