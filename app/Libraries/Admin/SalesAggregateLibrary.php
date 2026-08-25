<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use App\Enums\Order\OrderStatus;
use App\Enums\Returns\ReturnStatus;
use App\Models\DailySalesStat;
use App\Models\Order;
use App\Models\OrderReturn;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * 일별 매출 집계 생성기. `daily_sales_stats` 에 쓰는 유일한 자리다.
 *
 * **재실행해도 안전하다.** 날짜별로 `updateOrCreate` 라 몇 번을 돌리든 결과가 같다 —
 * 스케줄러가 최근 날짜를 계속 다시 계산해도 값이 누적되지 않는다.
 *
 * **왜 이벤트가 아니라 배치인가.** 주문이 결제될 때마다 집계를 갱신하면 항상
 * 최신이지만, 결제 트랜잭션에 통계 쓰기가 끼어든다 — 통계가 실패하면 주문이
 * 실패하거나, 조용히 어긋난 채로 남는다. 매출 숫자 몇 분 늦는 것보다 주문이
 * 안전한 게 중요하다. 대신 화면에 "집계 기준 시각" 을 같이 보여준다.
 */
class SalesAggregateLibrary
{
    /**
     * 한 날짜(영업 시간대 기준)를 다시 계산한다.
     */
    public function recompute(CarbonInterface $date): DailySalesStat
    {
        $tz = config('shop.timezone');

        $day = Carbon::parse($date->toDateString(), $tz);
        $from = $day->copy()->startOfDay()->utc();
        $to = $day->copy()->endOfDay()->utc();

        $orders = Order::query()
            ->whereIn('status', OrderStatus::saleValues())
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->get(['total_amount', 'items_total', 'discount_total', 'shipping_fee']);

        // 환불은 반품 '처리완료' 기준이다. 승인만 된 건은 아직 돈이 안 나갔다.
        $refunded = (int) OrderReturn::query()
            ->where('status', ReturnStatus::COMPLETED->value)
            ->whereBetween('completed_at', [$from, $to])
            ->sum('refund_amount');

        return DailySalesStat::query()->updateOrCreate(
            ['stat_date' => $day->toDateString()],
            [
                'order_count' => $orders->count(),
                'items_total' => (int) $orders->sum('items_total'),
                'discount_total' => (int) $orders->sum('discount_total'),
                'shipping_fee' => (int) $orders->sum('shipping_fee'),
                'revenue' => (int) $orders->sum('total_amount'),
                'refunded' => $refunded,
            ],
        );
    }

    /**
     * 최근 n일(오늘 포함)을 다시 계산한다.
     *
     * @return int 계산한 날짜 수
     */
    public function recomputeRecent(int $days): int
    {
        $tz = config('shop.timezone');
        $cursor = Carbon::now($tz)->subDays($days - 1)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        $count = 0;

        while ($cursor->lessThanOrEqualTo($today)) {
            $this->recompute($cursor);
            $cursor->addDay();
            $count++;
        }

        return $count;
    }

    /**
     * 주문이 있는 가장 오래된 날부터 오늘까지 전부. 처음 도입할 때와,
     * 과거 데이터를 손댄 뒤 되살릴 때 쓴다.
     *
     * @return int 계산한 날짜 수
     */
    public function recomputeAll(): int
    {
        $tz = config('shop.timezone');

        $first = Order::query()->whereNotNull('paid_at')->min('paid_at');
        $firstReturn = OrderReturn::query()->whereNotNull('completed_at')->min('completed_at');

        $earliest = collect([$first, $firstReturn])->filter()->min();

        if ($earliest === null) {
            return 0;
        }

        $cursor = Carbon::parse($earliest)->timezone($tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        $count = 0;

        while ($cursor->lessThanOrEqualTo($today)) {
            $this->recompute($cursor);
            $cursor->addDay();
            $count++;
        }

        return $count;
    }
}
