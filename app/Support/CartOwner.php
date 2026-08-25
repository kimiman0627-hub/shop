<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 장바구니 주인. 회원이면 user_id, 비회원이면 session_token 이다.
 *
 * CartLibrary 가 Request/Session 을 몰라도 되게 만드는 값 객체다 (CLAUDE.md §4.2).
 * 컨트롤러가 세션에서 토큰을 꺼내 이걸 만들어 넘긴다.
 */
final readonly class CartOwner
{
    private function __construct(
        public ?int $userId,
        public ?string $sessionToken,
    ) {}

    public static function user(int $userId): self
    {
        return new self($userId, null);
    }

    public static function guest(string $sessionToken): self
    {
        return new self(null, $sessionToken);
    }

    /**
     * carts 테이블 조회·생성에 쓰는 조건.
     *
     * @return array{user_id: int}|array{session_token: string}
     */
    public function toAttributes(): array
    {
        return $this->userId !== null
            ? ['user_id' => $this->userId]
            : ['session_token' => $this->sessionToken];
    }
}
