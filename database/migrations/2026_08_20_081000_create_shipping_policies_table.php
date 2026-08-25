<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 배송비 정책 (docs/schema-draft.md §6.1).
 *
 * 상품(products.shipping_policy_id)이 이 테이블을 참조하므로 상품보다 먼저 만든다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_policies', function (Blueprint $table) {
            $table->id();

            $table->string('name', 50);

            // 금액은 원 단위 정수 (CLAUDE.md §5.3).
            $table->unsignedBigInteger('base_fee')->default(0);

            // 이 금액 이상이면 무료. null 이면 조건부 무료 없음.
            $table->unsignedBigInteger('free_threshold')->nullable();

            // 상품이 정책을 고르지 않으면 이것을 쓴다.
            // '활성 기본 정책은 정확히 1개' 는 DB 로 표현하기 어려워
            // ShippingPolicyLibrary 에서 강제한다.
            $table->boolean('is_default')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_policies');
    }
};
