<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Payment\PaymentMethod;
use Illuminate\Support\Carbon;

/**
 * 결제 기한 계산.
 *
 * OrderLibrary(주문 생성 시 기한 확정)와 PaymentLibrary(안내 문구) 둘 다 쓴다.
 * 어느 한쪽에 두면 순환 참조가 되므로 밖으로 뺐다 (CLAUDE.md §4.2).
 *
 * 아무 데도 의존하지 않는 순수 계산이다.
 */
class PaymentDueCalculator
{
    /**
     * **수단마다 기한이 다르다.**
     *
     * 무통장입금은 사람이 은행에 가야 하므로 일 단위다.
     * 카드와 같은 30분을 적용하면 무통장 주문이 전부 자동취소된다.
     */
    public function for(PaymentMethod $method, ?Carbon $from = null): Carbon
    {
        $base = ($from ?? now())->copy();
        $rule = config('shop.payment.due.'.$method->value, []);

        if (isset($rule['days'])) {
            // 날짜 단위는 그날 자정까지로 맞춘다. '3일 뒤 14시 37분' 은 안내하기 어렵다.
            return $base->addDays((int) $rule['days'])->endOfDay();
        }

        return $base->addMinutes((int) ($rule['minutes'] ?? 30));
    }
}
