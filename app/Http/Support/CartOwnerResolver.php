<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Support\CartOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 요청에서 장바구니 주인을 알아낸다.
 *
 * 세션을 만지는 유일한 지점이다. 라이브러리는 결과(CartOwner)만 받는다.
 */
class CartOwnerResolver
{
    public const SESSION_KEY = 'cart_token';

    public function resolve(Request $request): CartOwner
    {
        $user = $request->user();

        if ($user !== null) {
            return CartOwner::user($user->id);
        }

        return CartOwner::guest($this->guestToken($request));
    }

    /**
     * 비회원 토큰. 없으면 만들어 세션에 넣는다.
     *
     * 로그인 시 session()->regenerate() 는 ID 만 바꾸고 데이터는 유지하므로
     * 이 토큰은 살아남는다. 그래서 로그인 직후 병합이 가능하다.
     */
    public function guestToken(Request $request): string
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            $request->session()->put(self::SESSION_KEY, $token);
        }

        return $token;
    }
}
