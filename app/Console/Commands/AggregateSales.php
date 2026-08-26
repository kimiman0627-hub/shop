<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Libraries\Admin\SalesAggregateLibrary;
use App\Libraries\Product\ProductStatLibrary;
use Illuminate\Console\Command;

/**
 * 일별 매출 집계(`daily_sales_stats`)를 만든다.
 *
 * **이 커맨드가 돌지 않으면 대시보드·매출통계 숫자가 갱신되지 않는다.**
 * 주문은 정상적으로 쌓이지만 통계 화면만 과거에 머문다 — 그래서 화면에
 * "집계 기준 시각" 을 같이 띄운다(멈춘 걸 눈치챌 수 있게).
 *
 * 기본은 최근 며칠만 다시 계산한다. 과거 주문을 손댔거나 처음 도입할 때는
 * `--all` 로 전 기간을 다시 만든다 — 언제 돌려도 결과가 같다(멱등).
 */
class AggregateSales extends Command
{
    protected $signature = 'shop:aggregate-sales
        {--days=3 : 다시 계산할 최근 일수(오늘 포함)}
        {--all : 주문이 있는 첫날부터 전부 다시 계산한다}';

    protected $description = '일별 매출·상품 집계 테이블을 갱신한다';

    public function handle(SalesAggregateLibrary $aggregates, ProductStatLibrary $productStats): int
    {
        if ($this->option('all')) {
            $count = $aggregates->recomputeAll();

            // 상품 집계도 같은 기간을 따라간다. 여기서 갱신되는 건 판매 실적뿐이고
            // 조회·장바구니 카운트는 손대지 않는다 (ProductStatLibrary 참고).
            $productStats->recomputeRecentSales(max($count, 1));

            $this->info($count === 0
                ? '집계할 주문이 없습니다.'
                : "전 기간 재집계 완료: {$count}일");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $count = $aggregates->recomputeRecent($days);
        $productStats->recomputeRecentSales($days);

        $this->info("최근 {$count}일 집계 완료 (매출 + 상품별)");

        return self::SUCCESS;
    }
}
