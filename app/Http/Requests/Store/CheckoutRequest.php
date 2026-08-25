<?php

declare(strict_types=1);

namespace App\Http\Requests\Store;

use App\Enums\Payment\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 주문서 제출.
 *
 * **회원 전용이다** — 라우트의 auth 미들웨어가 앞에서 막으므로 여기서 회원 여부를
 * 다시 갈라 처리하지 않는다. 예전에 있던 비회원 분기(주문조회 비밀번호 필수,
 * 쿠폰 금지, 이메일 필수)는 정책 변경으로 전부 사라졌다.
 */
class CheckoutRequest extends FormRequest
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
            'orderer_name' => ['required', 'string', 'max:50'],
            'orderer_phone' => ['required', 'string', 'max:20'],
            // 회원 계정 이메일이 있으므로 필수가 아니다. 다른 주소로 받고 싶으면 적는다.
            'orderer_email' => ['nullable', 'email', 'max:255'],

            'receiver_name' => ['required', 'string', 'max:50'],
            'receiver_phone' => ['required', 'string', 'max:20'],
            'postcode' => ['required', 'string', 'max:10'],
            'address1' => ['required', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'delivery_memo' => ['nullable', 'string', 'max:255'],

            // 이 배송지를 배송지록에 저장할지.
            'save_address' => ['nullable', 'boolean'],

            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            // 입금자명이 주문자와 다를 수 있다. 비우면 주문자명을 쓴다.
            'depositor_name' => ['nullable', 'string', 'max:50'],

            'user_coupon_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'orderer_name' => '주문자명',
            'orderer_phone' => '주문자 연락처',
            'orderer_email' => '이메일',
            'receiver_name' => '수령인',
            'receiver_phone' => '수령인 연락처',
            'postcode' => '우편번호',
            'address1' => '주소',
            'address2' => '상세주소',
        ];
    }
}
