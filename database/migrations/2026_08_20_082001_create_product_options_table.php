<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 옵션 그룹과 옵션 값 (docs/schema-draft.md §2.3).
 *
 * product_options.sort_order 가 곧 선택 단계다 (0 = 1단계, 1 = 2단계).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 30);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained('product_options')->cascadeOnDelete();
            $table->string('value', 50);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_option_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
    }
};
