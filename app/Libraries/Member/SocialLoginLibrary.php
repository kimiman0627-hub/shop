<?php

declare(strict_types=1);

namespace App\Libraries\Member;

use App\Exceptions\DomainRuleException;
use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 간편로그인(카카오·네이버) 회원 찾기/만들기.
 *
 * Socialite 가 돌려주는 프로필을 `User` 로 바꾸는 자리 — HTTP·세션은 컨트롤러가
 * 처리하고, 여기는 "이 소셜 계정에 대응하는 회원이 누구인가" 만 판단한다
 * (CLAUDE.md §4.2, 라이브러리는 Request/Session/Auth 에 의존하지 않는다).
 *
 * **이메일의 출처에 따라 처리가 다르다.** 메서드가 두 개로 갈린 이유가 그것이다:
 *
 * - `linkWithVerifiedEmail()` — 제공자가 검증해서 넘겨준 이메일(네이버). 그 사람이
 *   그 주소의 주인임을 제공자가 보증하므로, **같은 이메일의 기존 회원이 있으면 연동만
 *   추가**해도 안전하다. 새로 만드는 경우도 인증된 것으로 본다.
 * - `createWithUnverifiedEmail()` — 제공자가 이메일을 안 줘서 **본인이 화면에서 직접
 *   입력한** 이메일(카카오 개인 개발자 앱은 `account_email` 권한이 없다). 아무도
 *   검증하지 않았으므로 **기존 회원에 절대 붙이지 않는다** — 남의 이메일을 적어 넣는
 *   것만으로 그 계정을 가져갈 수 있게 된다. 새 회원만 만들고, 인증은 평소의
 *   이메일 인증 절차(`MustVerifyEmail`)에 맡긴다.
 */
class SocialLoginLibrary
{
    /** 이미 연동된 계정. 없으면 null — 가입 절차로 넘어가야 한다는 뜻이다. */
    public function findLinked(string $provider, string $providerUserId): ?User
    {
        return UserSocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first()
            ?->user;
    }

    /**
     * 제공자가 검증한 이메일로 로그인/가입.
     *
     * 같은 이메일의 회원이 이미 있으면(비밀번호로 가입한 회원 등) 연동만 더한다.
     */
    public function linkWithVerifiedEmail(string $provider, string $providerUserId, string $email, string $name): User
    {
        return DB::transaction(function () use ($provider, $providerUserId, $email, $name) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = $this->createUser($email, $name);

                // 제공자가 이미 검증했으므로 인증 메일을 또 보내지 않는다.
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->link($user, $provider, $providerUserId);

            return $user;
        });
    }

    /**
     * 본인이 직접 입력한 이메일로 **신규 가입**.
     *
     * 검증되지 않은 값이라 기존 회원과 합치지 않는다 — 이미 쓰는 이메일이면 막고,
     * 그 계정으로 로그인한 뒤 연동하라고 안내한다.
     */
    public function createWithUnverifiedEmail(string $provider, string $providerUserId, string $email, string $name): User
    {
        if ($this->findLinked($provider, $providerUserId) !== null) {
            throw new DomainRuleException('이미 연동된 계정입니다. 다시 로그인해 주세요.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new DomainRuleException(
                '이미 사용 중인 이메일입니다. 해당 계정으로 로그인한 뒤 연동해 주세요.',
                'email',
            );
        }

        return DB::transaction(function () use ($provider, $providerUserId, $email, $name) {
            $user = $this->createUser($email, $name);

            $this->link($user, $provider, $providerUserId);

            return $user;
        });
    }

    /** 로그인한 회원에게 소셜 계정을 추가로 연동한다. */
    public function link(User $user, string $provider, string $providerUserId): void
    {
        $owner = $this->findLinked($provider, $providerUserId);

        if ($owner !== null && $owner->id !== $user->id) {
            throw new DomainRuleException('이미 다른 회원에게 연동된 계정입니다.');
        }

        if ($owner !== null) {
            return;
        }

        UserSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);
    }

    private function createUser(string $email, string $name): User
    {
        return User::query()->create([
            'name' => $name !== '' ? $name : '회원',
            'email' => $email,
            /*
             * 소셜 전용 계정도 비밀번호 컬럼은 NOT NULL 이다. 본인은 몰라도 되는
             * 임의값을 채운다 — 나중에 비밀번호 로그인을 쓰고 싶으면 "비밀번호 찾기" 로
             * 새로 정하면 된다(별도 화면을 만들지 않는 이유).
             */
            'password' => Hash::make(Str::random(40)),
        ]);
    }
}
