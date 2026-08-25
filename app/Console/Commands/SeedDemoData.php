<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 데모 데이터 넣기.
 *
 * `--fresh` 는 DB 를 통째로 지운다. 로컬 개발 편의용이며
 * 운영 환경에서는 커맨드 자체가 실행되지 않는다.
 */
class SeedDemoData extends Command
{
    protected $signature = 'shop:demo {--fresh : DB 를 초기화하고 처음부터 다시 넣는다}';

    protected $description = '로컬 데모 데이터(카테고리·상품·쿠폰·주문)를 넣는다';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('운영 환경에서는 실행할 수 없습니다.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            // 상품 이미지는 DB 밖에 있다. DB 만 지우면 고아 파일이 계속 쌓인다.
            $this->deleteProductImages();

            $this->call('migrate:fresh', ['--seed' => true]);
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class]);

        /*
         * 데모 주문은 과거 날짜에 뿌려진다. 집계는 스케줄러가 최근 며칠만 돌기
         * 때문에, 여기서 전 기간을 한 번 만들어주지 않으면 **대시보드 매출이
         * 0 으로 보인다** (주문은 있는데 집계 행이 없는 상태).
         */
        $this->call('shop:aggregate-sales', ['--all' => true]);

        return self::SUCCESS;
    }

    private function deleteProductImages(): void
    {
        $disk = Storage::disk(config('shop.image.disk'));

        if ($disk->exists('products')) {
            $disk->deleteDirectory('products');
        }
    }
}
