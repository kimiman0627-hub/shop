<?php

declare(strict_types=1);

namespace App\Libraries\Product;

use App\Enums\Order\OrderStatus;
use App\Models\DailyProductStat;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * 상품별 트래킹 — 조회 → 장바구니 → 구매 → 매출.
 *
 * **두 종류의 컬럼을 절대 같은 쿼리로 쓰지 않는다** (`daily_product_stats` 주석 참고):
 *
 * - `recordView()` / `recordCartAdd()` — 이벤트 시점에 +1. 다른 데 흔적이 없어서
 *   놓치면 복구할 방법이 없다.
 * - `recomputeSales()` — `order_items` 에서 다시 만든다. 조회·장바구니 컬럼은
 *   **건드리지 않는다.** 여기서 `updateOrCreate` 로 전체 컬럼을 쓰면 조회수가 0 이 된다.
 *
 * **Admin 이 아니라 Product 아래에 둔다.** 쓰는 쪽은 스토어프론트(조회·담기)이고
 * 읽는 쪽만 관리자다 — 고객용과 관리자용이 같은 라이브러리를 공유하는 형태다(§4.2).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class ProductStatLibrary
{
    /** 상품 상세를 열었다. 새로고침도 그대로 센다 — '조회수' 의 통상적 의미다. */
    public function recordView(int $productId): void
    {
        $this->bump($productId, 'view_count');
    }

    /** 장바구니에 담았다. 수량이 아니라 '담은 행위' 를 센다. */
    public function recordCartAdd(int $productId): void
    {
        $this->bump($productId, 'cart_count');
    }

    /**
     * 오늘 행의 카운터 하나를 올린다.
     *
     * 행이 없으면 먼저 만든다. `increment()` 를 쓰는 이유는 읽고-더하고-쓰기를
     * 하면 동시 요청에서 값이 덮여 사라지기 때문이다 — DB 가 원자적으로 더하게 한다.
     */
    private function bump(int $productId, string $column): void
    {
        $today = Carbon::now(config('shop.timezone'))->toDateString();

        DailyProductStat::query()->firstOrCreate([
            'stat_date' => $today,
            'product_id' => $productId,
        ]);

        DailyProductStat::query()
            ->where('stat_date', $today)
            ->where('product_id', $productId)
            ->increment($column);
    }

    /**
     * 하루치 판매 실적을 `order_items` 에서 다시 만든다.
     *
     * **조회·장바구니 컬럼은 손대지 않는다.** 판매 컬럼만 갱신하고, 그날 팔리지
     * 않은 상품은 0 으로 되돌린다(취소·반품으로 매출이 빠진 경우까지 반영하려면
     * 그냥 두면 안 된다).
     */
    public function recomputeSales(CarbonInterface $date): int
    {
        $tz = config('shop.timezone');
        $day = Carbon::parse($date->toDateString(), $tz);
        $from = $day->copy()->startOfDay()->utc();
        $to = $day->copy()->endOfDay()->utc();

        $rows = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereIn('status', OrderStatus::saleValues())
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$from, $to]))
            ->get(['order_id', 'product_id', 'quantity', 'subtotal'])
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        // 그날 이미 판매 실적이 적힌 상품은 일단 0 으로 되돌린다.
        // 안 그러면 주문이 취소돼도 예전 매출이 남는다.
        DailyProductStat::query()
            ->where('stat_date', $day->toDateString())
            ->update(['order_count' => 0, 'quantity' => 0, 'revenue' => 0]);

        foreach ($rows as $productId => $items) {
            DailyProductStat::query()->updateOrCreate(
                ['stat_date' => $day->toDateString(), 'product_id' => (int) $productId],
                [
                    // 같은 주문에 같은 상품이 여러 줄일 수 있다(조합이 다르면). 주문 단위로 센다.
                    'order_count' => $items->pluck('order_id')->unique()->count(),
                    'quantity' => (int) $items->sum('quantity'),
                    'revenue' => (int) $items->sum('subtotal'),
                ],
            );
        }

        return $rows->count();
    }

    /** 최근 n일(오늘 포함) 판매 실적 재계산. */
    public function recomputeRecentSales(int $days): int
    {
        $tz = config('shop.timezone');
        $cursor = Carbon::now($tz)->subDays($days - 1)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        $count = 0;

        while ($cursor->lessThanOrEqualTo($today)) {
            $this->recomputeSales($cursor);
            $cursor->addDay();
            $count++;
        }

        return $count;
    }

    /**
     * 관리자 상품분석 표.
     *
     * 기간 안의 상품별 합계 + 전환율. **조회가 0 인 상품은 전환율을 내지 않는다**
     * (0으로 나눌 수 없고, 0/0 을 0% 로 찍으면 "안 팔린다" 는 오해를 부른다).
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function report(Carbon $from, Carbon $to, string $sort = 'revenue'): array
    {
        $tz = config('shop.timezone');

        $stats = DailyProductStat::query()
            ->whereBetween('stat_date', [
                $from->copy()->timezone($tz)->toDateString(),
                $to->copy()->timezone($tz)->toDateString(),
            ])
            ->get()
            ->groupBy('product_id');

        $names = Product::query()
            ->whereIn('id', $stats->keys()->all())
            ->get(['id', 'name', 'status'])
            ->keyBy('id');

        $rows = $stats->map(function (Collection $days, int $productId) use ($names) {
            $views = (int) $days->sum('view_count');
            $carts = (int) $days->sum('cart_count');
            $orders = (int) $days->sum('order_count');
            $quantity = (int) $days->sum('quantity');
            $revenue = (int) $days->sum('revenue');

            $product = $names->get($productId);

            return [
                'product_id' => $productId,
                'name' => $product?->name ?? '(삭제된 상품)',
                'status' => $product?->status?->value,
                'view_count' => $views,
                'cart_count' => $carts,
                'order_count' => $orders,
                'quantity' => $quantity,
                'revenue' => $revenue,

                // 조회가 없으면 비율을 만들지 않는다 — 화면에서 '-' 로 찍는다.
                'cart_rate' => $views > 0 ? round($carts / $views * 100, 1) : null,
                'order_rate' => $views > 0 ? round($orders / $views * 100, 1) : null,
            ];
        })->values();

        $sortable = ['revenue', 'view_count', 'cart_count', 'order_count', 'quantity', 'order_rate'];
        $key = in_array($sort, $sortable, true) ? $sort : 'revenue';

        $rows = $rows->sortByDesc(fn (array $r) => $r[$key] ?? 0)->values()->all();

        return [
            'rows' => $rows,
            'totals' => [
                'view_count' => array_sum(array_column($rows, 'view_count')),
                'cart_count' => array_sum(array_column($rows, 'cart_count')),
                'order_count' => array_sum(array_column($rows, 'order_count')),
                'quantity' => array_sum(array_column($rows, 'quantity')),
                'revenue' => array_sum(array_column($rows, 'revenue')),
            ],
            'sort' => $key,
        ];
    }
}
