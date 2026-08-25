<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 상품 카테고리 (docs/schema-draft.md §2.1).
 *
 * 계층은 자기참조. 깊이는 3단계(depth 0~2)까지로 제한하며,
 * 제한은 DB 가 아니라 CategoryLibrary 의 검증에서 건다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // 최상위는 null. 부모가 지워지면 자식이 최상위로 올라가는 것을 막기 위해
            // 애플리케이션에서 '하위 카테고리가 있으면 삭제 불가' 를 강제한다.
            $table->foreignId('parent_id')->nullable()
                ->constrained('categories')->nullOnDelete();

            $table->string('name', 50);
            $table->string('slug', 80)->unique();

            // parent 로부터 파생되는 값이지만, 트리 조회를 매번 재귀하지 않으려고 비정규화한다.
            $table->unsignedTinyInteger('depth')->default(0);

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
