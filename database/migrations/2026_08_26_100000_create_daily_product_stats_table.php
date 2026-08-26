<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 상품별 일자별 집계 — 조회 → 장바구니 → 구매 → 매출.
 *
 * `daily_sales_stats` 가 "전체 매출이 얼마인가" 라면, 여기는 **"어떤 상품이
 * 관심을 받고 실제로 팔리는가"** 를 본다. 조회는 많은데 안 팔리는 상품을
 * 찾아내는 게 목적이다.
 *
 * **컬럼이 두 종류다. 섞어서 쓰면 데이터가 날아간다.**
 *
 * - `view_count` / `cart_count` 는 **이벤트가 일어날 때 즉시 +1 한다.**
 *   화면 조회·장바구니 담기는 다른 테이블에 흔적이 남지 않아서 그때 세지 않으면
 *   영영 알 수 없다. **재계산이 불가능하다.**
 * - `order_count` / `quantity` / `revenue` 는 `order_items` 에서 **다시 만들 수 있다.**
 *   `daily_sales_stats` 와 같은 방식으로 배치가 덮어쓴다.
 *
 * 그래서 `ProductStatLibrary` 는 두 그룹을 건드리는 메서드를 따로 둔다.
 * 재집계가 `view_count` 까지 덮어쓰면 조회수가 0 으로 사라진다.
 *
 * 날짜는 영업 시간대(KST) 달력 날짜다 — `daily_sales_stats` 와 같다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_product_stats', function (Blueprint $table) {
            $table->id();

            $table->date('stat_date');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // --- 이벤트 시점에 누적한다. 되돌릴 수 없다 ---
            $table->integer('view_count')->default(0);
            $table->integer('cart_count')->default(0);

            // --- order_items 에서 재계산한다 ---
            $table->integer('order_count')->default(0);   // 이 상품이 담긴 주문 건수
            $table->integer('quantity')->default(0);      // 판매 수량
            $table->bigInteger('revenue')->default(0);    // 판매 금액(할인 전 상품 합계)

            $table->timestamps();

            $table->unique(['stat_date', 'product_id']);
            // 기간 조회에서 상품별로 묶어 읽는다.
            $table->index(['product_id', 'stat_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_product_stats');
    }
};
