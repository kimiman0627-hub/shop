<?php

declare(strict_types=1);

use App\Enums\Support\InquiryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회원 관리용 메모와 1:1 문의.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_memos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 누가 남긴 메모인지 남긴다. 관리자가 지워져도 메모는 유지된다.
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->text('content');
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 특정 주문에 대한 문의일 수 있다. 주문이 지워져도 문의는 남는다.
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('category', 30);
            $table->string('title', 200);
            $table->text('content');

            $table->string('status', 20)->default(InquiryStatus::PENDING->value);

            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'id']);
            // 미답변 문의를 먼저 처리하기 위한 인덱스.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('member_memos');
    }
};
