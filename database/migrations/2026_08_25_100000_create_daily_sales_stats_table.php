<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 일별 매출 집계.
 *
 * 지금까지는 조회할 때마다 기간 안의 주문을 **전부 읽어와 PHP 에서 묶었다**
 * (`GROUP BY DATE()` 는 DB 마다 함수가 달라 이식성 규칙 §5.1 에 걸린다).
 * 주문이 쌓이면 그대로 느려지는 구조라, 미리 하루치씩 계산해 여기 넣어둔다.
 *
 * **날짜는 영업 시간대(`config('shop.timezone')`) 기준이다.** UTC 자정으로 끊으면
 * 한국 기준 오전 9시에 날짜가 바뀐다 — 그래서 `stat_date` 는 KST 달력 날짜다.
 *
 * 이 테이블은 **언제든 다시 만들 수 있는 파생 데이터**다(`shop:aggregate-sales`).
 * 원본은 여전히 `orders` / `order_returns` 다 — 값이 어긋나면 원본을 고치고 재집계한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_stats', function (Blueprint $table) {
            $table->id();

            // KST 달력 날짜. 하루 = 한 행.
            $table->date('stat_date')->unique();

            $table->integer('order_count')->default(0);

            // 금액은 전부 원 단위 정수 (§전 테이블 공통 규칙).
            $table->bigInteger('items_total')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('shipping_fee')->default(0);

            // 결제액 합계. items_total - discount_total + shipping_fee 와 같아야 한다.
            $table->bigInteger('revenue')->default(0);

            /*
             * 환불액. **주문일이 아니라 반품 처리완료일 기준이다** — 8월 주문을
             * 9월에 환불하면 9월 행에 잡힌다. 돈이 실제로 나간 날에 다는 게 맞다.
             */
            $table->bigInteger('refunded')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_stats');
    }
};
