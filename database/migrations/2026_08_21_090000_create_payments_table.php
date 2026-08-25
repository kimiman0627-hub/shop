<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 결제 시도 이력과 입금 계좌 (docs/schema-draft.md §5).
 *
 * 주문당 결제 시도는 여러 행이다. 실패·재시도 이력이 남는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('bank_name', 30);
            $table->string('account_number', 40);
            $table->string('holder_name', 50);

            // 활성 계좌가 여러 개일 수 있어 하나를 기본으로 지정한다.
            // '활성 기본 계좌는 정확히 1개' 는 BankAccountLibrary 에서 강제한다.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();

            $table->string('method', 20);
            $table->string('status', 20);
            $table->unsignedBigInteger('amount');

            // ---- 무통장입금 전용 ----
            // 계좌 정보를 **스냅샷**으로 남긴다. 계좌를 바꿔도 과거 안내 내용은 불변이어야 한다.
            $table->string('bank_name', 30)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('holder_name', 50)->nullable();
            // 입금자명. 동명이인 대비로 고객이 직접 적는다.
            $table->string('depositor_name', 50)->nullable();

            // ---- PG 연동용 (지금은 비어 있다) ----
            $table->string('pg_provider', 30)->nullable();
            $table->string('pg_transaction_id', 100)->nullable();
            $table->json('raw_response')->nullable();

            // 누가 입금을 확인했는가. 수동 처리라 책임 소재가 남아야 한다.
            $table->foreignId('confirmed_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();
            $table->string('memo', 255)->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'method']);
            $table->index('pg_transaction_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            /*
             * 결제 기한. 이 시각이 지나면 예약이 해제된다.
             *
             * 고정 TTL 대신 컬럼으로 두는 이유: 결제 수단마다 기한이 다르다.
             * 카드는 분 단위지만 무통장입금은 사람이 은행에 가야 하므로 일 단위다.
             * 30분 고정이면 무통장 주문이 전부 취소된다.
             */
            $table->timestamp('payment_due_at')->nullable();
            $table->index('payment_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_due_at']);
            $table->dropColumn('payment_due_at');
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('bank_accounts');
    }
};
