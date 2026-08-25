<?php

declare(strict_types=1);

use App\Enums\Product\ProductStatus;
use App\Enums\Product\ShippingFeeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 상품 (docs/schema-draft.md §2.2).
 *
 * 재고 컬럼을 두지 않는다 — 재고는 product_variants 에만 있다.
 * 양쪽에 두면 반드시 어긋난다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // 상품 있는 카테고리는 못 지운다. CategoryLibrary 에서도 같은 규칙을 건다.
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();

            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->string('summary', 255)->nullable();
            $table->text('description')->nullable();

            // 금액은 원 단위 정수. float 금지 (CLAUDE.md §5.3).
            $table->unsignedBigInteger('base_price');
            $table->unsignedBigInteger('sale_price')->nullable();

            $table->string('status', 20)->default(ProductStatus::DRAFT->value);

            $table->string('shipping_fee_type', 20)->default(ShippingFeeType::PAID->value);
            // 정책 쓰는 상품이 있으면 정책을 못 지운다.
            $table->foreignId('shipping_policy_id')->nullable()
                ->constrained('shipping_policies')->restrictOnDelete();

            $table->string('thumbnail_path', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'sort_order']);
            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
