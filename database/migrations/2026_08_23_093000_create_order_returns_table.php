<?php

declare(strict_types=1);

use App\Enums\Returns\ReturnStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 반품·교환.
 *
 * 부분 반품을 지원하므로 주문당 여러 건이 생길 수 있다.
 * 금액은 **승인 시점에 계산해 스냅샷으로 저장**한다 —
 * 나중에 쿠폰·배송비 정책이 바뀌어도 이미 승인한 환불액은 변하면 안 된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();

            $table->string('type', 20);
            $table->string('reason', 30);
            $table->string('reason_detail', 500)->nullable();

            // 배송비를 누가 부담하는지. 승인 시 관리자가 최종 결정한다.
            $table->string('responsibility', 20);

            $table->string('status', 20)->default(ReturnStatus::REQUESTED->value);

            /*
             * 금액 스냅샷 (원 단위 정수, CLAUDE.md §5.3).
             * 부분 반품이면 쿠폰 할인을 항목 비율로 안분해 차감한다.
             */
            $table->unsignedBigInteger('items_refund')->default(0);
            $table->unsignedBigInteger('coupon_deduction')->default(0);
            $table->unsignedBigInteger('shipping_deduction')->default(0);
            $table->unsignedBigInteger('shipping_refund')->default(0);
            $table->unsignedBigInteger('refund_amount')->default(0);

            // 회수 송장. shipments 는 '최초 배송' 만 담으므로 여기 따로 둔다.
            $table->string('pickup_carrier', 30)->nullable();
            $table->string('pickup_tracking_no', 50)->nullable();

            // 교환 재발송 송장.
            $table->string('exchange_carrier', 30)->nullable();
            $table->string('exchange_tracking_no', 50)->nullable();

            // 입고된 물건을 재판매할 수 있는가. 불량·파손이면 false 로 두고 재고를 안 되돌린다.
            $table->boolean('restock')->default(true);

            $table->string('reject_reason', 500)->nullable();
            $table->string('admin_memo', 500)->nullable();

            $table->foreignId('handled_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();

            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'requested_at']);
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_return_id')->constrained('order_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();

            $table->unsignedInteger('quantity');

            // 교환일 때 바꿀 조합. 같은 상품의 다른 옵션이다.
            $table->foreignId('exchange_variant_id')->nullable()
                ->constrained('product_variants')->nullOnDelete();

            $table->timestamps();

            $table->index('order_return_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};
