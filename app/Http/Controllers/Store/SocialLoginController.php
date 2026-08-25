<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\SocialSignupRequest;
use App\Libraries\Member\SocialLoginLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * 간편로그인(카카오·네이버) 리다이렉트·콜백.
 *
 * `{provider}` 는 routes/web.php 에서 kakao|naver 로만 열어둔다 — 여기서
 * 다시 검사하지 않는다(라우트 밖 값은 애초에 이 액션까지 못 온다).
 *
 * **제공자가 이메일을 안 주면 추가입력 화면으로 보낸다.** 카카오는 개인 개발자 앱에서
 * `account_email` 동의항목 자체가 "권한 없음" 이라(비즈 앱 전환 전) 이메일이 안 넘어온다.
 * 그렇다고 가입을 막으면 카카오 로그인이 아예 쓸모없어지므로, 모자란 항목만 본인에게
 * 직접 받는다.
 */
class SocialLoginController extends Controller
{
    /**
     * 콜백에서 받은 소셜 신원을 추가입력 화면까지 들고 가는 세션 키.
     *
     * 이 값이 세션에 있다는 것은 **이 브라우저가 방금 해당 소셜 계정의 소유를
     * 증명했다**는 뜻이다(OAuth 콜백을 통과했으므로). 화면에서 받은 폼 값만으로
     * 계정을 만들지 않는 이유다 — provider_user_id 는 반드시 여기서 꺼내 쓴다.
     */
    private const PENDING_SESSION_KEY = 'social_signup';

    public function __construct(private readonly SocialLoginLibrary $socialLogin) {}

    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            // 동의 화면에서 취소했거나, 세션 만료로 state 가 안 맞거나, 제공자 쪽 오류 —
            // 원인을 세분화해 보여줄 수 없는 것들이라 뭉뚱그린다.
            return $this->failed('간편로그인에 실패했습니다. 다시 시도해 주세요.');
        }

        $providerUserId = (string) $socialUser->getId();
        $email = $socialUser->getEmail();
        $name = (string) ($socialUser->getName() ?? $socialUser->getNickname() ?? '');

        try {
            $user = $this->socialLogin->findLinked($provider, $providerUserId);

            if ($user === null && filled($email)) {
                $user = $this->socialLogin->linkWithVerifiedEmail($provider, $providerUserId, $email, $name);
            }
        } catch (DomainRuleException $e) {
            return $this->failed($e->getMessage());
        }

        // 제공자가 이메일을 안 줬다 — 남은 항목만 본인에게 받는다.
        if ($user === null) {
            $request->session()->put(self::PENDING_SESSION_KEY, [
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'name' => $name,
            ]);

            return redirect()->route('social.complete');
        }

        Auth::guard('web')->login($user);

        return redirect()->intended(route('home'));
    }

    /** 추가입력 화면. 콜백을 거치지 않고 들어오면 보여줄 게 없다. */
    public function complete(Request $request): Response|RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if ($pending === null) {
            return $this->failed('간편로그인 정보가 만료되었습니다. 다시 시도해 주세요.');
        }

        return Inertia::render('Store/Auth/SocialComplete', [
            'provider' => $pending['provider'],
            'name' => $pending['name'],
        ]);
    }

    public function storeComplete(SocialSignupRequest $request): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if ($pending === null) {
            return $this->failed('간편로그인 정보가 만료되었습니다. 다시 시도해 주세요.');
        }

        $data = $request->validated();

        try {
            // provider_user_id 는 폼이 아니라 **세션에서만** 온다 — 화면에서 받으면
            // 아무 소셜 계정이나 자기 것이라고 주장할 수 있다.
            $user = $this->socialLogin->createWithUnverifiedEmail(
                $pending['provider'],
                $pending['provider_user_id'],
                $data['email'],
                $data['name'],
            );
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);

        Auth::guard('web')->login($user);

        // 본인이 적은 주소라 아직 인증 전이다. 평소의 이메일 인증 절차를 그대로 탄다.
        $user->sendEmailVerificationNotification();

        return redirect()->route('home')->with('status', '가입이 완료되었습니다. 이메일 인증을 마쳐 주세요.');
    }

    /**
     * 로그인 화면으로 사유와 함께 되돌린다.
     *
     * 키가 `general` 인 이유: 리다이렉트로 넘어간 에러는 `useForm` 이 못 본다
     * (`form.errors` 는 자기가 보낸 요청의 응답만 받는다). 로그인 화면은 이 키를
     * `usePage().props.errors` 에서 직접 읽어 배너로 띄운다 — 관리자 화면들이
     * 쓰는 `errors.general` 과 같은 방식이다.
     */
    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['general' => $message]);
    }
}
