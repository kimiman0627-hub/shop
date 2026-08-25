<?php

declare(strict_types=1);

use App\Enums\Admin\AdminStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 계정. 고객(users)과 완전히 분리된 테이블이다 (CLAUDE.md §7.1).
 * users 에 is_admin 플래그를 두는 방식은 쓰지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();

            // 관리자는 이메일이 아니라 로그인 ID 로 로그인한다 (CLAUDE.md §7.1).
            $table->string('login_id', 50)->unique();

            $table->string('name', 50);
            $table->string('email', 255)->nullable();
            $table->string('password');

            // 역할 삭제 시 소속 관리자가 권한 없는 상태로 남지 않도록 restrict.
            // 역할을 지우려면 소속 관리자를 먼저 옮겨야 한다.
            $table->foreignId('admin_role_id')->constrained('admin_roles')->restrictOnDelete();

            $table->string('status', 20)->default(AdminStatus::ACTIVE->value);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
