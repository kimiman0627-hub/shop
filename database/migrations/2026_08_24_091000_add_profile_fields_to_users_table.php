<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회원 프로필 확장 — 전화번호, 마케팅 수신동의, 마지막 로그인 시각.
 *
 * 지금까지 `users` 는 Fortify 스캐폴딩 그대로였다(이름·이메일·비밀번호뿐).
 * 전화번호는 주문(`orders`) 스냅샷에만 있어서 매 주문마다 새로 입력해야 했고,
 * 수신동의는 컬럼 자체가 없어서 마케팅 알림을 보낼 법적 근거가 없었고,
 * 관리자는 `admins.last_login_at` 은 보면서 고객의 마지막 로그인은 알 방법이 없었다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');

            /*
             * **동의 여부가 아니라 동의 시각을 저장한다.**
             * 정보통신망법상 광고성 정보 전송에는 수신동의 사실을 증명해야 하는데,
             * boolean 하나로는 "언제 동의했는지" 를 못 남긴다. null = 미동의,
             * 값이 있으면 그 시각에 동의한 것 — 관리자가 켜고 끄는 게 아니라
             * 회원 본인이 바꿀 때마다 시각을 새로 찍는다.
             */
            $table->timestamp('marketing_email_agreed_at')->nullable();
            $table->timestamp('marketing_sms_agreed_at')->nullable();

            // admins.last_login_at 과 같은 패턴. 로그인 시에만 시스템이 찍는다.
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'marketing_email_agreed_at', 'marketing_sms_agreed_at', 'last_login_at']);
        });
    }
};
