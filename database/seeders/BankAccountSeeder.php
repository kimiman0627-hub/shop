<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

/**
 * 기본 입금 계좌.
 *
 * 계좌가 없으면 고객이 무통장입금으로 주문할 수 없다
 * (BankAccountLibrary::defaultAccount 가 예외를 던진다).
 * 그래서 로컬에서 바로 주문 흐름을 볼 수 있도록 하나 넣어둔다.
 *
 * **실제 계좌가 아니다. 운영 전 반드시 교체한다.**
 */
class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        BankAccount::query()->updateOrCreate(
            ['account_number' => '123456-01-123456'],
            [
                'bank_name' => '국민은행',
                'holder_name' => '쇼핑몰(로컬테스트)',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );
    }
}
