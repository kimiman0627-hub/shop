<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 역할 × 관리자 메뉴 권한 (CLAUDE.md §7.2).
 *
 * menu_code 는 config/admin/menu.php 가 원천이므로 외래키를 걸지 않는다.
 * 권한 레코드가 없으면 차단이 기본값이다 (allow-list).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();

            // 대문자 메뉴 코드. 예: 'PRODUCT_LIST', 'SETTING_ROLE'
            $table->string('menu_code', 50);

            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->timestamps();

            $table->unique(['admin_role_id', 'menu_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
    }
};
