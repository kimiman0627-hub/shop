<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 쿠폰 마스터와 발급분 (docs/schema-draft.md §8).
 *
 * user_coupons.order_id 는 orders 테이블이 생긴 뒤 별도 마이그레이션으로 붙인다
 * (파일 하나 = 목적 하나, CLAUDE.md §5.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // 코드 입력형(CODE)일 때만 쓴다. 대문자로 저장한다.
            $table->string('code', 30)->nullable()->unique();

            $table->string('name', 50);
            $table->string('issue_type', 20);
            $table->string('discount_type', 20);

            // 금액은 원 단위 정수, 정률은 퍼센트 값 (CLAUDE.md §5.3).
            $table->unsignedBigInteger('discount_value');

            // 정률 쿠폰의 할인 상한. 없으면 고가 상품에서 손실이 무한정 커진다.
            $table->unsignedBigInteger('max_discount_amount')->nullable();

            $table->unsignedBigInteger('min_order_amount')->default(0);

            // 발급일 기준 유효일수. valid_from/until 과는 다른 축이다 (§8.1).
            $table->unsignedInteger('valid_days')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            $table->unsignedInteger('total_issue_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'issue_type']);
        });

        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();

            // 발급분이 있는 쿠폰은 지울 수 없다. 쿠폰은 삭제 대신 is_active=false 로 내린다.
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('issued_at');

            // 발급 시점에 확정 저장한다. 마스터의 valid_days 를 나중에 바꿔도
            // 이미 발급된 쿠폰의 만료일은 변하지 않아야 한다 — 주문 스냅샷과 같은 원칙 (§8.2).
            $table->timestamp('expires_at');

            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
            $table->index('coupon_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_coupons');
        Schema::dropIfExists('coupons');
    }
};
