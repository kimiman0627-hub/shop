<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 판매 단위(SKU)와 옵션 조합 (docs/schema-draft.md §2.3).
 *
 * 재고는 여기에만 있다. 실물(stock_quantity)과 예약(reserved_quantity)을
 * 분리하며, 판매가능 = stock_quantity - reserved_quantity 다 (§7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('sku', 50)->unique();

            // 조합별 추가금. 음수도 허용하므로 부호 있는 integer 다.
            $table->integer('additional_price')->default(0);

            // 음수 방지는 앱 레벨에서 한다 — PostgreSQL 에는 unsigned 가 없다 (CLAUDE.md §5.2).
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained('product_option_values')->cascadeOnDelete();

            $table->unique(['product_variant_id', 'product_option_value_id'], 'pvv_variant_value_unique');
            $table->index('product_option_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');
    }
};
