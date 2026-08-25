<?php

declare(strict_types=1);

use App\Enums\Product\ProductImageType;
use App\Enums\Product\QuestionStatus;
use App\Enums\Product\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 상품 상세 이미지 · 후기 · 상품문의 (docs/schema-draft.md §12).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) 기존 이미지에 용도 구분을 더한다. 기존 행은 전부 갤러리다.
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('type', 20)->default(ProductImageType::GALLERY->value);
            $table->index(['product_id', 'type', 'sort_order'], 'product_images_type_order_index');
        });

        /*
         * 2) 평점 비정규화.
         *
         * 목록 20개마다 후기 테이블을 집계하면 화면이 느려진다.
         * **평균이 아니라 합계를 저장한다** — 평균은 부동소수라 누적하면 오차가 쌓이고,
         * 후기 하나가 숨겨질 때 되돌리기도 어렵다. 합계·건수는 정수라 정확히 가감된다.
         */
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('rating_sum')->default(0);
        });

        // 3) 상품 후기
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            /*
             * 어떤 구매로 쓴 후기인가. **구매자만 쓸 수 있게 하는 근거이자
             * 주문 항목당 1건 제한의 근거**다. 관리자 대행 작성은 없다.
             */
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->restrictOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('content', 2000);

            $table->string('status', 20)->default(ReviewStatus::PUBLISHED->value);

            // 판매자 답글. 후기마다 하나면 충분하다.
            $table->string('admin_reply', 1000)->nullable();
            $table->foreignId('replied_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'status', 'id']);
            $table->index('user_id');
        });

        // 4) 상품 문의 (Q&A)
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('content', 1000);

            /*
             * 비밀글. 작성자와 관리자만 내용을 본다.
             * 목록에는 '비밀글입니다' 로 자리만 남긴다 — 아예 감추면
             * 답변 대기 건수가 안 맞아 보인다.
             */
            $table->boolean('is_secret')->default(false);

            $table->string('status', 20)->default(QuestionStatus::PENDING->value);

            $table->string('answer', 1000)->nullable();
            $table->foreignId('answered_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_questions');
        Schema::dropIfExists('product_reviews');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['review_count', 'rating_sum']);
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_type_order_index');
            $table->dropColumn('type');
        });
    }
};
