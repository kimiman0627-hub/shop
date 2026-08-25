<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 간편로그인(카카오·네이버) 연동 계정.
 *
 * `users` 는 그대로 두고 — 이메일·비밀번호 중심 스키마를 안 건드린다 — 소셜
 * 식별자만 별도 테이블에 붙인다. 한 회원이 카카오·네이버를 둘 다 연동할 수
 * 있어서 1:N 이다(User 1 - N UserSocialAccount).
 *
 * `provider` + `provider_user_id` 조합이 유니크다 — 같은 카카오 계정이
 * 서로 다른 회원에 두 번 연동되면 안 된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_social_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 'kakao' | 'naver'. 문자열로 두는 이유는 bank_accounts.bank_code 와 같다 —
            // enum 을 쓰면 DB마다 문법이 달라 이식성이 깨진다 (CLAUDE.md §5.1).
            $table->string('provider', 20);

            // 제공자가 발급하는 회원 고유 ID(카카오 id, 네이버 id). 이메일이 아니다 —
            // 이메일은 나중에 바뀔 수 있지만 이 값은 안 바뀐다.
            $table->string('provider_user_id', 191);

            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
    }
};
