<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use App\Enums\Admin\PermissionAction;
use App\Models\Admin;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\LocalTime;

/**
 * 관리자 대시보드.
 *
 * **관리자가 볼 권한이 있는 것만 내린다.** 사이드바에서 메뉴를 숨겨놓고
 * 대시보드에서 같은 숫자를 보여주면 권한 분리가 무의미해진다 (CLAUDE.md §7.5).
 * 그래서 카드마다 대응하는 `menu_code` 를 두고 `allows()` 로 거른다.
 */
class DashboardLibrary
{
    /** 재고가 이 수량 이하로 떨어지면 경고한다. */
    private const LOW_STOCK = 5;

    public function __construct(
        private readonly StatLibrary $stats,
        private readonly AdminPermissionLibrary $permissions,
        private readonly AdminTodoLibrary $todos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAdmin(Admin $admin): array
    {
        return [
            'sales' => $this->when($admin, 'ORDER_LIST', fn () => $this->sales()),
            'todo' => $this->todos->forAdmin($admin),
            'series' => $this->when($admin, 'ORDER_LIST', fn () => $this->stats->dailySeries(
                ...$this->stats->lastDays(14),
            )),
            'top_products' => $this->when($admin, 'PRODUCT_LIST', fn () => $this->stats->byProduct(
                ...[...$this->stats->lastDays(30), 5],
            )),
            'low_stock' => $this->when($admin, 'PRODUCT_LIST', fn () => $this->lowStock()),
            'recent_orders' => $this->when($admin, 'ORDER_LIST', fn () => $this->recentOrders()),
            'members' => $this->when($admin, 'MEMBER_LIST', fn () => $this->members()),

            // 매출 숫자가 언제 기준인지. 집계가 멈추면 여기서 티가 난다.
            'aggregated_at' => $this->when($admin, 'ORDER_LIST', fn () => $this->stats->aggregatedAt()),
        ];
    }

    /**
     * 권한이 없으면 아예 null 을 내린다. 화면은 null 인 카드를 그리지 않는다.
     *
     * @template T
     *
     * @param  \Closure(): T  $build
     * @return T|null
     */
    private function when(Admin $admin, string $menuCode, \Closure $build): mixed
    {
        return $this->permissions->allows($admin, $menuCode, PermissionAction::READ)
            ? $build()
            : null;
    }

    /* ------------------------------------------------------------------ 매출 */

    /**
     * @return array<string, mixed>
     */
    private function sales(): array
    {
        $today = $this->stats->summary(...$this->stats->today());
        $month = $this->stats->summary(...$this->stats->monthToDate());

        return [
            'today' => $today,
            'month' => $month,
        ];
    }

    /* ------------------------------------------------------------------ 재고 */

    /**
     * 판매가능 수량이 바닥난 조합.
     *
     * 판매가능 = 실물 − 예약이라 **컬럼 하나로 비교할 수 없다.**
     * `whereRaw` 없이 하려면 후보를 넉넉히 가져와 PHP 에서 거른다 (CLAUDE.md §5.1).
     *
     * @return list<array<string, mixed>>
     */
    private function lowStock(): array
    {
        return ProductVariant::query()
            ->where('is_active', true)
            // 예약이 실물을 넘는 경우는 없으므로, 실물이 넉넉하면 볼 필요도 없다.
            ->where('stock_quantity', '<=', self::LOW_STOCK * 3)
            ->with(['product:id,name,status', 'optionValues.option'])
            ->orderBy('stock_quantity')
            ->limit(50)
            ->get()
            ->filter(fn (ProductVariant $v) => $v->product !== null
                && $v->product->status->isPurchasable()
                && $v->availableQuantity() <= self::LOW_STOCK)
            ->take(8)
            ->map(fn (ProductVariant $v) => [
                'product_id' => $v->product_id,
                'product_name' => $v->product->name,
                'option_label' => $v->optionValues
                    ->sortBy(fn ($value) => $value->option?->sort_order ?? 0)
                    ->pluck('value')
                    ->implode(' / ') ?: $v->sku,
                'sku' => $v->sku,
                'available' => $v->availableQuantity(),
                'stock_quantity' => $v->stock_quantity,
                'reserved_quantity' => $v->reserved_quantity,
            ])
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ 주문 */

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Order::query()
            ->with('items')
            ->ordered()
            ->limit(6)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'orderer_name' => $o->orderer_name,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'total_amount' => $o->total_amount,
                'summary' => $this->itemSummary($o),
                'ordered_at' => LocalTime::dateTime($o->ordered_at),
            ])
            ->all();
    }

    private function itemSummary(Order $order): string
    {
        $first = $order->items->first();

        if ($first === null) {
            return '-';
        }

        $extra = $order->items->count() - 1;

        return $first->product_name.($extra > 0 ? " 외 {$extra}건" : '');
    }

    /* ------------------------------------------------------------------ 회원 */

    /**
     * @return array<string, int>
     */
    private function members(): array
    {
        [$weekStart, $weekEnd] = $this->stats->lastDays(7);

        return [
            'total' => User::query()->count(),
            'new_this_week' => User::query()->whereBetween('created_at', [$weekStart, $weekEnd])->count(),
            'unverified' => User::query()->whereNull('email_verified_at')->count(),
        ];
    }
}
