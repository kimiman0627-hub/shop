<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 장바구니 (docs/schema-draft.md §3).
 *
 * 회원은 user_id, 비회원은 session_token 으로 식별한다.
 * 가격을 저장하지 않는다 — 장바구니는 항상 현재 가격을 보여준다.
 * 스냅샷은 주문 시점에만 뜬다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            // 비회원용. 로그인 시 회원 장바구니로 병합하고 이 행은 지운다.
            $table->string('session_token', 64)->nullable();

            $table->timestamps();

            // 회원/비회원 각각 장바구니는 하나뿐이다.
            $table->unique('user_id');
            $table->unique('session_token');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();

            // 조합이 사라지면 담아둔 항목도 의미가 없다.
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')->cascadeOnDelete();

            $table->unsignedInteger('quantity');
            $table->timestamps();

            // 같은 조합을 두 줄로 담지 않는다. 수량을 합친다.
            $table->unique(['cart_id', 'product_variant_id'], 'cart_items_cart_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
