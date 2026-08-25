<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주문 (docs/schema-draft.md §4).
 *
 * 수령인·연락처·상품명·가격은 전부 **스냅샷**이다. 회원이 주소를 바꾸거나
 * 상품 가격이 바뀌어도 과거 주문서는 변하지 않아야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ORD + 날짜 + 일련. 앱에서 만든다 (DB 함수에 의존하지 않는다, CLAUDE.md §5.1).
            $table->string('order_no', 30)->unique();

            // 비회원 주문이면 null (schema-draft.md §4.2).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // 비회원 주문조회용 해시. 주문번호 + 이 비밀번호로 조회한다.
            $table->string('guest_password', 255)->nullable();

            $table->string('status', 20)->default(OrderStatus::PENDING->value);

            // 금액을 4개로 분해해 저장한다. 합계만 두면 정산·부분환불에서 근거를 잃는다.
            $table->unsignedBigInteger('items_total');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('total_amount');

            $table->foreignId('user_coupon_id')->nullable()
                ->constrained('user_coupons')->nullOnDelete();

            // 주문자 스냅샷
            $table->string('orderer_name', 50);
            $table->string('orderer_phone', 20);
            $table->string('orderer_email', 255)->nullable();

            // 배송지 스냅샷 — 회원 정보에서 조인하지 않는다.
            $table->string('receiver_name', 50);
            $table->string('receiver_phone', 20);
            $table->string('postcode', 10);
            $table->string('address1', 255);
            $table->string('address2', 255)->nullable();
            $table->string('delivery_memo', 255)->nullable();

            $table->timestamp('ordered_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // 예약 해제 멱등성. 이 값이 있으면 이미 푼 주문이므로 두 번 빼지 않는다 (§7.4).
            $table->timestamp('stock_released_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('ordered_at');
            $table->index(['user_id', 'ordered_at']);
            // 만료 예약을 쓸어담는 스케줄러가 쓴다.
            $table->index(['status', 'ordered_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // 링크용일 뿐이다. 상품이 사라져도 주문 이력은 스냅샷으로 남는다.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()
                ->constrained('product_variants')->nullOnDelete();

            // 아래가 원본이다. 화면에 찍는 값은 전부 여기서 읽는다.
            $table->string('product_name', 200);
            $table->string('variant_name', 100)->nullable();
            $table->string('sku', 50)->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');

            // 배송비 계산 근거 스냅샷 (§6.2).
            $table->string('shipping_fee_type', 20);

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
