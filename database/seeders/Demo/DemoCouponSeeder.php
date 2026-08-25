<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Libraries\Order\CouponLibrary;
use Illuminate\Database\Seeder;

/**
 * 데모 쿠폰.
 *
 * 발급 방식 3종(SIGNUP / CODE / MANUAL)과 할인 방식 2종(FIXED / PERCENT)이
 * 모두 한 번씩 나오게 구성했다. 쿠폰 관리 화면과 주문서 쿠폰 선택이
 * 실제로 어떻게 보이는지 확인하려면 종류가 섞여 있어야 한다.
 */
class DemoCouponSeeder extends Seeder
{
    public function __construct(private readonly CouponLibrary $coupons) {}

    public function run(): void
    {
        foreach ($this->coupons() as $spec) {
            $this->coupons->create([
                'code' => $spec['code'] ?? null,
                'name' => $spec['name'],
                'issue_type' => $spec['issue_type'],
                'discount_type' => $spec['discount_type'],
                'discount_value' => $spec['discount_value'],
                'max_discount_amount' => $spec['max_discount_amount'] ?? null,
                'min_order_amount' => $spec['min_order_amount'] ?? 0,
                'valid_days' => $spec['valid_days'] ?? null,
                'valid_from' => $spec['valid_from'] ?? null,
                'valid_until' => $spec['valid_until'] ?? null,
                'total_issue_limit' => $spec['total_issue_limit'] ?? null,
                'per_user_limit' => $spec['per_user_limit'] ?? 1,
                'is_active' => $spec['is_active'] ?? true,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function coupons(): array
    {
        return [
            [
                // 가입하면 자동으로 들어온다 (CouponLibrary::issueSignupCoupons).
                'name' => '신규가입 5,000원 할인',
                'issue_type' => 'SIGNUP',
                'discount_type' => 'FIXED',
                'discount_value' => 5000,
                'min_order_amount' => 20000,
                'valid_days' => 30,
            ],
            [
                // 정률 + 상한. 상한이 없으면 고가 상품에서 손실이 커진다.
                'name' => '첫 구매 10% 할인 (최대 1만원)',
                'issue_type' => 'SIGNUP',
                'discount_type' => 'PERCENT',
                'discount_value' => 10,
                'max_discount_amount' => 10000,
                'min_order_amount' => 30000,
                'valid_days' => 14,
            ],
            [
                'code' => 'WELCOME2026',
                'name' => '웰컴 코드 3,000원',
                'issue_type' => 'CODE',
                'discount_type' => 'FIXED',
                'discount_value' => 3000,
                'min_order_amount' => 20000,
                'valid_days' => 60,
            ],
            [
                'code' => 'SEASONOFF15',
                'name' => '시즌오프 15% (최대 2만원)',
                'issue_type' => 'CODE',
                'discount_type' => 'PERCENT',
                'discount_value' => 15,
                'max_discount_amount' => 20000,
                'min_order_amount' => 50000,
                'valid_days' => 21,
                'total_issue_limit' => 100,
            ],
            [
                // 관리자가 개별 지급하는 쿠폰. 목록에서 발급 방식이 구분돼 보인다.
                'name' => 'VIP 감사 쿠폰 20,000원',
                'issue_type' => 'MANUAL',
                'discount_type' => 'FIXED',
                'discount_value' => 20000,
                'min_order_amount' => 100000,
                'valid_days' => 90,
            ],
            [
                // 내려간 쿠폰. 쿠폰은 삭제하지 않고 비활성으로 둔다 (schema-draft.md §8.2).
                'name' => '2025 블랙프라이데이 (종료)',
                'code' => 'BF2025',
                'issue_type' => 'CODE',
                'discount_type' => 'PERCENT',
                'discount_value' => 30,
                'max_discount_amount' => 50000,
                'min_order_amount' => 50000,
                'valid_days' => 7,
                'is_active' => false,
            ],
        ];
    }
}
