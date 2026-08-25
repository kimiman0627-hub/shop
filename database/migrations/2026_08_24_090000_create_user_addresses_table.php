<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회원 배송지록.
 *
 * 지금까지는 주소가 `orders` 스냅샷에만 있었다 — 재주문할 때마다
 * 처음부터 입력해야 했다. 이 테이블은 **재사용할 배송지**를 회원에 붙여둔다.
 *
 * 주문 자체의 배송지는 여전히 `orders` 컬럼이 원본이다(스냅샷 원칙,
 * schema-draft.md §4). 여기는 그 값을 채워 넣는 "즐겨찾기" 역할만 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // '집', '회사' 같은 별칭. 비워도 된다 — 주소 하나만 쓰는 사람이 더 많다.
            $table->string('label', 20)->nullable();

            $table->string('receiver_name', 50);
            $table->string('receiver_phone', 20);
            $table->string('postcode', 10);
            $table->string('address1', 255);
            $table->string('address2', 255)->nullable();

            // 기본 배송지. 회원당 최대 1개 — DB 제약 대신 AddressLibrary 가 강제한다
            // (bank_accounts·shipping_policies 와 같은 이유: SQLite 부분 유니크 인덱스가
            // Laravel 스키마 빌더로는 이식성 있게 표현되지 않는다, CLAUDE.md §5.1).
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
