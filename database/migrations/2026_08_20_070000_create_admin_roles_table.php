<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 역할. 관리자 1명 = 역할 1개 (CLAUDE.md §7.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();

            // 대문자 코드. 'SUPER_ADMIN' | 'MANAGER' | 'STAFF' 등 (CLAUDE.md §6.1).
            // 한번 정한 코드는 바꾸지 않는다 — 권한 로직이 이 값을 참조한다.
            $table->string('code', 50)->unique();

            $table->string('name', 50);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_roles');
    }
};
