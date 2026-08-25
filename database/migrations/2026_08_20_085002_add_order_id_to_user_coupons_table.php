<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 쿠폰이 어느 주문에 쓰였는지 연결한다 (docs/schema-draft.md §8.2).
 *
 * 쿠폰 단계에서는 orders 테이블이 없어 미뤄뒀다.
 * 이미 적용된 마이그레이션을 고치지 않고 새 파일로 붙인다 (CLAUDE.md §5.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_coupons', function (Blueprint $table) {
            // 주문이 지워져도 쿠폰 발급 이력은 남아야 한다.
            // after() 는 쓰지 않는다 — MySQL 전용이라 SQLite/PostgreSQL 둘 다 무시한다 (CLAUDE.md §5.2).
            $table->foreignId('order_id')->nullable()
                ->constrained('orders')->nullOnDelete();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_coupons', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
