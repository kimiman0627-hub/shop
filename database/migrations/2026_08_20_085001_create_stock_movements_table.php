<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 재고 변동 이력 (docs/schema-draft.md §7.7).
 *
 * 예약 방식은 한 주문에 RESERVE → SELL 또는 RESERVE → RELEASE 로 이벤트가 두 번 일어난다.
 * 이력이 없으면 reserved_quantity 가 틀어졌을 때 어느 주문에서 새는지 찾을 방법이 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // 이력이 있는 조합은 지울 수 없다.
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')->restrictOnDelete();

            $table->string('type', 20);

            // 두 축을 따로 기록한다. 부호 있는 정수다.
            $table->integer('stock_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->integer('stock_after');
            $table->integer('reserved_after');

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->string('memo', 255)->nullable();

            // 갱신되지 않는 이력이므로 updated_at 이 없다.
            $table->timestamp('created_at')->nullable();

            $table->index(['product_variant_id', 'id']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
