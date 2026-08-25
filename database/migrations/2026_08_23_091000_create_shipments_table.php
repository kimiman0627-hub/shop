<?php

declare(strict_types=1);

use App\Enums\Order\ShipmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 배송 (docs/schema-draft.md §6.3).
 *
 * 부분배송은 1차 스코프 밖이라 주문당 1건이다.
 * 그래도 별도 테이블로 두면 나중에 분할해도 orders 를 안 건드린다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // 대문자 코드. config/shop.php 의 shipping.carriers 가 원천이다.
            $table->string('carrier', 30)->nullable();
            $table->string('tracking_no', 50)->nullable();

            $table->string('status', 20)->default(ShipmentStatus::READY->value);

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // 누가 출고 처리했는지. 배송 사고 시 책임 소재가 남아야 한다.
            $table->foreignId('shipped_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();

            $table->string('memo', 255)->nullable();
            $table->timestamps();

            // 주문당 1건. 부분배송을 열 때 이 제약을 푼다.
            $table->unique('order_id');
            $table->index('status');
            $table->index('tracking_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
