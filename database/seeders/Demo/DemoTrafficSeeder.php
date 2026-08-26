<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\Order\OrderStatus;
use App\Models\DailyProductStat;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 상품 조회·장바구니 담기 이력(데모).
 *
 * 조회수와 장바구니 담기는 **이벤트 시점에만 기록되는 값**이라 주문 데이터에서
 * 되살릴 수 없다. 데모 주문은 시더가 과거 날짜로 만들어 넣기 때문에, 그때의
 * 조회 기록은 어디에도 없다 — 여기서 그럴듯하게 만들어 넣지 않으면 상품분석
 * 화면이 "판매는 있는데 조회는 0" 인 이상한 모습이 된다.
 *
 * **실제 판매량에 맞춰 깔때기를 만든다.** 판매가 있는 상품은 조회도 많게,
 * 조회 → 장바구니 → 구매 순으로 줄어들게 한다. 무작위로 뿌리면 전환율이
 * 100%를 넘거나 팔린 상품의 조회가 0 이 되는 앞뒤 안 맞는 표가 나온다.
 */
class DemoTrafficSeeder extends Seeder
{
    /** 며칠치를 만들 것인가. 매출통계 기본 구간(30일)을 덮는다. */
    private const DAYS = 30;

    public function run(): void
    {
        $tz = config('shop.timezone');
        $products = Product::query()->get(['id']);

        if ($products->isEmpty()) {
            return;
        }

        /*
         * 판매량은 `order_items` 에서 직접 센다. `daily_product_stats.quantity` 를
         * 읽으면 **집계 커맨드가 먼저 돌았는지에 결과가 달라진다** — 시더 실행
         * 순서에 기대는 코드는 언젠가 조용히 어긋난다.
         */
        $sales = $this->soldQuantityByDayAndProduct($tz);

        for ($ago = self::DAYS - 1; $ago >= 0; $ago--) {
            $date = Carbon::now($tz)->subDays($ago)->toDateString();

            foreach ($products as $product) {
                $stat = DailyProductStat::query()->firstOrNew([
                    'stat_date' => $date,
                    'product_id' => $product->id,
                ]);

                $sold = (int) ($sales[$date][$product->id] ?? 0);

                /*
                 * 조회수: 팔린 상품은 확실히 많이 보이도록, 안 팔린 상품도
                 * 최소한의 유입은 있도록 만든다. 판매 1건당 대략 12~25 조회.
                 */
                $views = $sold > 0
                    ? $sold * random_int(12, 25) + random_int(3, 10)
                    : random_int(0, 9);

                // 장바구니는 조회의 8~20%, 그리고 반드시 판매 건수 이상이어야 한다.
                $carts = (int) round($views * random_int(8, 20) / 100);
                $carts = max($carts, $sold);

                $stat->fill([
                    'view_count' => $views,
                    'cart_count' => $carts,
                ])->save();
            }
        }

        $this->command?->info('상품 조회·장바구니 데모 기록을 '.self::DAYS.'일치 넣었습니다.');
    }

    /**
     * [영업일자][상품id] => 판매수량.
     *
     * @return array<string, array<int, int>>
     */
    private function soldQuantityByDayAndProduct(string $tz): array
    {
        $map = [];

        OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereIn('status', OrderStatus::saleValues())
                ->whereNotNull('paid_at'))
            ->with('order:id,paid_at')
            ->get(['order_id', 'product_id', 'quantity'])
            ->each(function (OrderItem $item) use (&$map, $tz) {
                if ($item->product_id === null || $item->order?->paid_at === null) {
                    return;
                }

                $day = $item->order->paid_at->copy()->timezone($tz)->toDateString();
                $map[$day][$item->product_id] = ($map[$day][$item->product_id] ?? 0) + $item->quantity;
            });

        return $map;
    }
}
