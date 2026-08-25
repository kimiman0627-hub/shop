<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Libraries\Order\OrderLibrary;
use App\Libraries\Order\StockLibrary;
use Illuminate\Console\Command;

/**
 * 만료된 미결제 주문을 취소하고 재고 예약을 푼다 (docs/schema-draft.md §7.3).
 *
 * **이 커맨드가 돌지 않으면 재고가 조용히 잠긴다.** 운영에서는 스케줄러에 등록한다.
 */
class ExpireStaleOrders extends Command
{
    protected $signature = 'shop:expire-orders {--check-drift : 예약 정합성도 함께 점검한다}';

    protected $description = '결제 시간이 지난 주문을 취소하고 재고 예약을 해제한다';

    public function handle(OrderLibrary $orders, StockLibrary $stock): int
    {
        $minutes = config('shop.stock.reservation_minutes');

        $count = $orders->expireStaleOrders();

        $this->info("만료 주문 {$count}건 정리 (기준: {$minutes}분)");

        if ($this->option('check-drift')) {
            $drift = $stock->findReservationDrift();

            if ($drift === []) {
                $this->info('예약 정합성 이상 없음');

                return self::SUCCESS;
            }

            // 자동 수정하지 않는다. 원인을 봐야 한다 (§7.5).
            $this->warn('예약 수량이 진행중 주문과 어긋납니다:');
            $this->table(['variant', 'SKU', '컬럼값', '계산값'], array_map(
                fn (array $d) => [$d['variant_id'], $d['sku'], $d['column'], $d['expected']],
                $drift,
            ));
        }

        return self::SUCCESS;
    }
}
