<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\Support\CartOwnerResolver;
use App\Libraries\Order\CartLibrary;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * 로그인 시 비회원 장바구니를 회원 장바구니로 옮긴다.
 *
 * Fortify 도 결국 Illuminate\Auth\Events\Login 을 발생시키므로 여기서 한 번만 처리하면 된다.
 * 관리자(admin 가드) 로그인에는 장바구니가 없으므로 건너뛴다.
 */
class MergeGuestCartOnLogin
{
    public function __construct(
        private readonly CartLibrary $carts,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $token = session(CartOwnerResolver::SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return;
        }

        $this->carts->mergeGuestIntoUser($token, $event->user->id);

        // 병합했으니 비회원 토큰은 버린다. 남겨두면 다음 요청에서
        // 빈 비회원 장바구니가 다시 만들어진다.
        session()->forget(CartOwnerResolver::SESSION_KEY);
    }
}
