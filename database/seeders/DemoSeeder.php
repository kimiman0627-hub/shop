<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Database\Seeders\Demo\DemoCatalogSeeder;
use Database\Seeders\Demo\DemoCouponSeeder;
use Database\Seeders\Demo\DemoOrderSeeder;
use Database\Seeders\Demo\DemoReviewSeeder;
use Database\Seeders\Demo\DemoTrafficSeeder;
use Illuminate\Database\Seeder;

/**
 * 데모 데이터 — 화면을 실제 쇼핑몰처럼 채운다.
 *
 * **`DatabaseSeeder` 에 넣지 않는다.** 그쪽은 운영에도 필요한 것만 담는다
 * (관리자 계정, 기본 배송비 정책, 입금 계좌). 데모는 별도로 부른다:
 *
 *     php artisan migrate:fresh --seed
 *     php artisan db:seed --class=DemoSeeder
 *
 * 또는 한 번에:
 *
 *     php artisan shop:demo --fresh
 *
 * 모든 데이터는 **라이브러리를 거쳐** 만든다. 모델을 직접 만들면
 * slug 생성·재고 예약·쿠폰 사용처리 같은 규칙이 통째로 빠져서,
 * 시드 데이터만 앱이 만들어내는 데이터와 다른 모양이 된다.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            // 데모 회원·주문이 운영 DB 에 섞이면 매출 통계부터 틀어진다.
            $this->command?->error('운영 환경에서는 데모 데이터를 넣지 않습니다.');

            return;
        }

        if (Product::query()->exists()) {
            $this->command?->warn(
                '이미 상품이 있습니다. 데모 데이터는 빈 DB 기준으로 만들어졌습니다. '
                .'다시 넣으려면 `php artisan shop:demo --fresh` 를 쓰세요.',
            );

            return;
        }

        $this->call([
            DemoCatalogSeeder::class,
            DemoCouponSeeder::class,
            DemoOrderSeeder::class,
            // 후기는 배송완료 주문이 있어야 만들 수 있다 — 주문 시더 뒤에 온다.
            DemoReviewSeeder::class,
            DemoTrafficSeeder::class,
        ]);

        $this->command?->info('데모 데이터를 넣었습니다. 고객 계정 비밀번호는 전부 demo-local-1234 입니다.');
    }
}
