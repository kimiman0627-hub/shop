<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingPolicy;
use Illuminate\Database\Seeder;

/**
 * 기본 배송비 정책.
 *
 * 활성 기본 정책이 없으면 배송비 계산이 예외를 던진다
 * (ShippingPolicyLibrary::defaultPolicy). 그래서 시드에 반드시 하나 둔다.
 */
class ShippingPolicySeeder extends Seeder
{
    public function run(): void
    {
        ShippingPolicy::query()->updateOrCreate(
            ['name' => '기본 배송비'],
            [
                'base_fee' => 3000,
                'free_threshold' => 50000,
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }
}
