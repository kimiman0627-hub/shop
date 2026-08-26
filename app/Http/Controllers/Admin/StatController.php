<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Libraries\Admin\StatLibrary;
use App\Libraries\Product\ProductStatLibrary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 매출통계 (STAT_SALES).
 */
class StatController extends Controller
{
    public function __construct(
        private readonly StatLibrary $stats,
        private readonly ProductStatLibrary $productStats,
    ) {}

    public function sales(Request $request): Response
    {
        [$from, $to] = $this->stats->rangeFrom($request->query('from'), $request->query('to'));

        $tz = config('shop.timezone');

        return Inertia::render('Admin/Stat/Sales', [
            'filters' => [
                // 화면에 되돌려줄 때는 영업 시간대 날짜로 바꾼다.
                'from' => $from->copy()->timezone($tz)->toDateString(),
                'to' => $to->copy()->timezone($tz)->toDateString(),
            ],
            'summary' => $this->stats->summary($from, $to),
            'series' => $this->stats->dailySeries($from, $to),
            'byProduct' => $this->stats->byProduct($from, $to, 15),
            'byCategory' => $this->stats->byCategory($from, $to),

            // 집계가 언제 갱신됐는지. 안 움직이면 스케줄러가 멈춘 것이다.
            'aggregatedAt' => $this->stats->aggregatedAt(),
        ]);
    }

    /**
     * 상품분석 — 조회 → 장바구니 → 구매 → 매출.
     *
     * 정렬 기준은 화면에서 고른다. 어떤 걸로 보느냐에 따라 답이 달라진다 —
     * 매출 1위와 '조회는 많은데 안 팔리는 상품' 은 서로 다른 문제다.
     */
    public function products(Request $request): Response
    {
        [$from, $to] = $this->stats->rangeFrom($request->query('from'), $request->query('to'));

        $tz = config('shop.timezone');

        return Inertia::render('Admin/Stat/Products', [
            'filters' => [
                'from' => $from->copy()->timezone($tz)->toDateString(),
                'to' => $to->copy()->timezone($tz)->toDateString(),
                'sort' => (string) $request->query('sort', 'revenue'),
            ],
            ...$this->productStats->report($from, $to, (string) $request->query('sort', 'revenue')),
            'aggregatedAt' => $this->stats->aggregatedAt(),
        ]);
    }
}
