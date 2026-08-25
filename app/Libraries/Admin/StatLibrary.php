<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use App\Enums\Order\OrderStatus;
use App\Models\DailySalesStat;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 매출·판매 통계.
 *
 * **날짜 경계는 영업 시간대(`config('shop.timezone')`)로 끊는다.**
 * DB 는 UTC 라 그냥 자정으로 자르면 한국 기준 오전 9시에 날짜가 바뀐다.
 *
 * **매출 요약·일별 추이는 `daily_sales_stats` 를 읽는다.** 예전에는 조회할 때마다
 * 기간 안의 주문을 전부 읽어 PHP 에서 묶었는데(`GROUP BY DATE()` 가 이식성 규칙
 * §5.1 에 걸려서), 주문이 쌓이면 그대로 느려지는 구조였다. 지금은 `shop:aggregate-sales`
 * 가 하루치씩 미리 계산해 넣어두고 여기서는 **읽기만 한다**.
 *
 * **상품별·카테고리별은 아직 실시간 집계다.** 상위 N개를 뽑는 형태라 하루 단위로
 * 미리 접어두려면 상품×날짜 테이블이 따로 필요하다 — 아직 안 만들었다(CLAUDE.md §11).
 */
class StatLibrary
{
    /* ------------------------------------------------------------------ 기간 */

    /** 영업 시간대 기준 '오늘' 의 UTC 구간. */
    public function today(): array
    {
        return $this->dayRange(0, 0);
    }

    /**
     * n일 전부터 오늘까지의 UTC 구간.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function lastDays(int $days): array
    {
        return $this->dayRange($days - 1, 0);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function monthToDate(): array
    {
        $tz = config('shop.timezone');
        $start = Carbon::now($tz)->startOfMonth()->utc();
        $end = Carbon::now($tz)->endOfDay()->utc();

        return [$start, $end];
    }

    /**
     * 문자열 날짜(YYYY-MM-DD) 두 개를 UTC 구간으로.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rangeFrom(?string $from, ?string $to): array
    {
        $tz = config('shop.timezone');

        $start = $from !== null && $from !== ''
            ? Carbon::parse($from, $tz)->startOfDay()
            : Carbon::now($tz)->subDays(29)->startOfDay();

        $end = $to !== null && $to !== ''
            ? Carbon::parse($to, $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();

        return [$start->utc(), $end->utc()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dayRange(int $daysAgoStart, int $daysAgoEnd): array
    {
        $tz = config('shop.timezone');

        return [
            Carbon::now($tz)->subDays($daysAgoStart)->startOfDay()->utc(),
            Carbon::now($tz)->subDays($daysAgoEnd)->endOfDay()->utc(),
        ];
    }

    /* ------------------------------------------------------------------ 요약 */

    /**
     * 기간 요약. 매출·주문수·객단가·환불액.
     *
     * @return array<string, int>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $rows = $this->statRows($from, $to);

        $count = (int) $rows->sum('order_count');
        $revenue = (int) $rows->sum('revenue');
        $refunded = (int) $rows->sum('refunded');

        return [
            'order_count' => $count,
            'revenue' => $revenue,
            'items_total' => (int) $rows->sum('items_total'),
            'discount_total' => (int) $rows->sum('discount_total'),
            'shipping_fee' => (int) $rows->sum('shipping_fee'),
            'refunded' => $refunded,
            // 순매출 = 결제액 − 환불액
            'net_revenue' => $revenue - $refunded,
            'average_order_value' => $count > 0 ? intdiv($revenue, $count) : 0,
        ];
    }

    /**
     * UTC 구간을 영업 시간대 날짜로 바꿔 집계 행을 읽는다.
     *
     * 기간 헬퍼(today/lastDays/monthToDate/rangeFrom)가 전부 KST 하루 경계에
     * 맞춰 구간을 만들기 때문에, 시각을 날짜로 접어도 경계가 어긋나지 않는다.
     *
     * @return Collection<int, DailySalesStat>
     */
    private function statRows(Carbon $from, Carbon $to): Collection
    {
        $tz = config('shop.timezone');

        return DailySalesStat::query()
            ->whereBetween('stat_date', [
                $from->copy()->timezone($tz)->toDateString(),
                $to->copy()->timezone($tz)->toDateString(),
            ])
            ->get();
    }

    /** 집계가 마지막으로 갱신된 시각. 화면에 "몇 시 기준" 인지 밝히는 용도. */
    public function aggregatedAt(): ?string
    {
        $at = DailySalesStat::query()->max('updated_at');

        return $at === null
            ? null
            : Carbon::parse($at)->timezone(config('shop.timezone'))->format('Y-m-d H:i');
    }

    /**
     * 일별 매출 추이. **매출이 없는 날도 0 으로 채운다** —
     * 빈 날을 건너뛰면 그래프의 가로축이 거짓말을 한다.
     *
     * @return list<array{date: string, label: string, revenue: int, order_count: int}>
     */
    public function dailySeries(Carbon $from, Carbon $to): array
    {
        $tz = config('shop.timezone');

        $byDay = $this->statRows($from, $to)
            ->keyBy(fn (DailySalesStat $s) => $s->stat_date->toDateString());

        $series = [];
        $cursor = $from->copy()->timezone($tz)->startOfDay();
        $last = $to->copy()->timezone($tz)->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $cursor->toDateString();
            $row = $byDay->get($key);

            $series[] = [
                'date' => $key,
                'label' => $cursor->format('n/j'),
                'revenue' => $row === null ? 0 : $row->revenue,
                'order_count' => $row === null ? 0 : $row->order_count,
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /* ------------------------------------------------------------------ 상품 */

    /**
     * 상품별 판매. 수량과 금액 둘 다 낸다 —
     * 많이 팔린 것과 돈이 되는 것은 다르다.
     *
     * @return list<array<string, mixed>>
     */
    public function byProduct(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->soldItems($from, $to)
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => [
                // 상품명은 주문 시점 스냅샷을 쓴다. 이름이 바뀌어도 당시 이름이 남는다 (§4.3).
                'product_id' => $rows->first()->product_id,
                'name' => $rows->first()->product_name,
                'quantity' => (int) $rows->sum('quantity'),
                'amount' => (int) $rows->sum('subtotal'),
            ])
            ->sortByDesc('amount')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * 카테고리별 판매.
     *
     * 카테고리는 스냅샷이 아니라 **지금의 상품 정보**에서 읽는다 —
     * 상품이 카테고리를 옮기면 과거 매출도 따라 옮겨간다. 카테고리별 집계의
     * 목적이 '지금 어떤 군이 팔리나' 이므로 그게 자연스럽다.
     *
     * @return list<array<string, mixed>>
     */
    public function byCategory(Carbon $from, Carbon $to): array
    {
        $items = $this->soldItems($from, $to);

        $categories = Product::query()
            ->whereIn('id', $items->pluck('product_id')->filter()->unique()->all())
            ->with('category:id,name')
            ->get(['id', 'category_id'])
            ->mapWithKeys(fn (Product $p) => [$p->id => $p->category?->name ?? '미분류']);

        return $items
            ->groupBy(fn (OrderItem $i) => $categories[$i->product_id] ?? '미분류')
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'quantity' => (int) $rows->sum('quantity'),
                'amount' => (int) $rows->sum('subtotal'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * 기간 안에 실제로 팔린 주문 항목.
     *
     * @return Collection<int, OrderItem>
     */
    private function soldItems(Carbon $from, Carbon $to): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereIn('status', OrderStatus::saleValues())
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$from, $to]))
            ->get(['order_id', 'product_id', 'product_name', 'quantity', 'subtotal']);
    }
}
