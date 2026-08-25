<?php

declare(strict_types=1);

namespace App\Listeners;

use SocialiteProviders\Kakao\KakaoProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Naver\Provider as NaverProvider;

/**
 * 카카오·네이버 소셜 드라이버를 Socialite 에 등록한다.
 *
 * `socialiteproviders/*` 패키지들은 자기 서비스 프로바이더가 없다 — 대신
 * `SocialiteWasCalled` 이벤트를 듣는 리스너가 `extendSocialite()` 를 불러줘야
 * 드라이버가 생긴다(패키지 공식 설치 방법). 이 리스너 자체는 여느 리스너처럼
 * `app/Listeners` 자동 등록에 얹힌다 — `Event::listen()` 을 또 추가하지 않는다
 * (RecordUserLogin 과 같은 이유, 중복 실행 함정).
 */
class ExpandSocialiteProviders
{
    public function handle(SocialiteWasCalled $event): void
    {
        $event->extendSocialite('kakao', KakaoProvider::class);
        $event->extendSocialite('naver', NaverProvider::class);
    }
}
