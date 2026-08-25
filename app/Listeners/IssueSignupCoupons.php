<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Libraries\Order\CouponLibrary;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

/**
 * 회원가입 시 SIGNUP 쿠폰을 자동 발급한다 (docs/schema-draft.md §8.1).
 *
 * Fortify 의 회원가입도 Illuminate\Auth\Events\Registered 를 발생시킨다.
 */
class IssueSignupCoupons
{
    public function __construct(
        private readonly CouponLibrary $coupons,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        try {
            $this->coupons->issueSignupCoupons($event->user->id);
        } catch (\Throwable $e) {
            // 쿠폰 발급이 실패해도 회원가입은 성공해야 한다.
            // 가입 자체를 막을 만큼 중요한 부수 작업이 아니다.
            Log::warning('가입 쿠폰 발급 실패', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
