<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Libraries\Member\InquiryLibrary;
use App\Libraries\Member\MemberLibrary;
use App\Libraries\Order\CartLibrary;
use App\Libraries\Order\CouponLibrary;
use App\Libraries\Order\OrderLibrary;
use App\Libraries\Order\ReturnLibrary;
use App\Libraries\Order\ShipmentLibrary;
use App\Libraries\Payment\PaymentLibrary;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\CartOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 데모 회원 · 주문 · 결제 · 배송 · 반품 · 문의.
 *
 * 관리자 화면이 전부 빈 목록이면 화면을 볼 수가 없다.
 * **주문 상태를 골고루 만든다** — 각 관리 화면에 최소 몇 건씩은 걸리도록.
 *
 * 여기서 만드는 주문 하나는 **쿠폰을 실제로 사용한다.**
 * 쿠폰 적용 경로가 깨지면 시드가 바로 실패하므로, 이 시더 자체가 회귀 테스트다
 * (docs/worklog.md 밟으면 아픈 곳 §13 참고).
 */
class DemoOrderSeeder extends Seeder
{
    public function __construct(
        private readonly CartLibrary $carts,
        private readonly OrderLibrary $orders,
        private readonly PaymentLibrary $payments,
        private readonly ShipmentLibrary $shipments,
        private readonly ReturnLibrary $returns,
        private readonly CouponLibrary $coupons,
        private readonly InquiryLibrary $inquiries,
        private readonly MemberLibrary $members,
    ) {}

    public function run(): void
    {
        $admin = Admin::query()->where('login_id', 'manager1')->firstOrFail();

        $customers = $this->seedCustomers();

        $this->seedOrders($customers, $admin);
        $this->seedInquiries($customers, $admin);
        $this->seedMemos($customers, $admin);
        $this->spreadOverTime();
    }

    /**
     * 주문을 지난 3주에 걸쳐 흩뿌린다.
     *
     * 라이브러리는 항상 `now()` 를 찍으므로 시드로 만든 주문이 전부 같은 순간에 몰린다.
     * 그러면 대시보드의 일별 매출 그래프가 **막대 하나**뿐이라 화면을 볼 수가 없다.
     *
     * **여기서만 라이브러리를 우회한다.** 과거 시각을 만드는 건 도메인 규칙이 아니라
     * 데모 사정이고, 이걸 라이브러리에 넣으면 앱 코드에 '날짜를 조작하는 길' 이 생긴다.
     * 한 주문에 걸린 모든 시각을 **같은 폭으로** 밀어 앞뒤 순서는 그대로 둔다.
     */
    private function spreadOverTime(): void
    {
        $orders = Order::query()->orderBy('id')->get(['id']);
        $span = 20;

        foreach ($orders->values() as $index => $order) {
            // 오래된 주문부터 순서대로 과거로 보낸다. 가장 최근 것은 오늘 남긴다.
            $daysAgo = (int) round($span - ($index * $span / max(1, $orders->count() - 1)));

            if ($daysAgo <= 0) {
                continue;
            }

            $this->shift('orders', ['id' => $order->id], $daysAgo, [
                'ordered_at', 'paid_at', 'canceled_at', 'payment_due_at',
                'stock_released_at', 'created_at', 'updated_at',
            ]);

            $this->shift('payments', ['order_id' => $order->id], $daysAgo, [
                'requested_at', 'paid_at', 'canceled_at', 'created_at', 'updated_at',
            ]);

            $this->shift('shipments', ['order_id' => $order->id], $daysAgo, [
                'shipped_at', 'delivered_at', 'created_at', 'updated_at',
            ]);

            $this->shift('order_returns', ['order_id' => $order->id], $daysAgo, [
                'requested_at', 'approved_at', 'received_at', 'completed_at',
                'rejected_at', 'created_at', 'updated_at',
            ]);
        }

        // 회원 가입일도 주문보다 앞이어야 자연스럽다.
        DB::table('users')->update(['created_at' => now()->subDays($span + 5)]);
    }

    /**
     * 지정한 컬럼들을 같은 일수만큼 과거로 민다. null 인 컬럼은 건드리지 않는다.
     *
     * @param  array<string, mixed>  $where
     * @param  list<string>  $columns
     */
    private function shift(string $table, array $where, int $days, array $columns): void
    {
        $rows = DB::table($table)->where($where)->get();

        foreach ($rows as $row) {
            $updates = [];

            foreach ($columns as $column) {
                if (($row->{$column} ?? null) !== null) {
                    $updates[$column] = Carbon::parse($row->{$column})->subDays($days);
                }
            }

            if ($updates !== []) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }
        }
    }

    /* ------------------------------------------------------------------ 회원 */

    /**
     * @return array<string, User>
     */
    private function seedCustomers(): array
    {
        $specs = [
            'kim' => ['김서연', 'seoyeon@example.com', true],
            'lee' => ['이준호', 'junho@example.com', true],
            'park' => ['박민지', 'minji@example.com', true],
            // 이메일 미인증 회원. 관리자 목록 필터 확인용.
            'choi' => ['최유진', 'yujin@example.com', false],
        ];

        $users = [];

        foreach ($specs as $key => [$name, $email, $verified]) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => 'demo-local-1234',
                'email_verified_at' => $verified ? now() : null,
            ]);

            // 가입 쿠폰은 실제 가입 흐름과 같은 경로로 준다.
            $this->coupons->issueSignupCoupons($user->id);

            $users[$key] = $user;
        }

        return $users;
    }

    /* ------------------------------------------------------------------ 주문 */

    /**
     * @param  array<string, User>  $customers
     */
    private function seedOrders(array $customers, Admin $admin): void
    {
        // 1) 입금대기 — 무통장처리 화면에 걸린다
        $this->order($customers['kim'], [['베이직 코튼 반팔 티셔츠', 0, 2]], stopAt: 'PENDING');

        // 2) 결제완료 — 배송관리에서 '출고 대기' 로 보인다
        $this->order($customers['lee'], [['옥스퍼드 버튼다운 셔츠', 0, 1]], stopAt: 'PAID', admin: $admin);

        // 3) 상품준비중
        $this->order($customers['park'], [
            ['코튼 치노 팬츠', 0, 1],
            ['워싱 코튼 볼캡', 0, 1],
        ], stopAt: 'PREPARING', admin: $admin);

        // 4) 배송중
        $this->order($customers['kim'], [['레더 미니멀 스니커즈', 1, 1]], stopAt: 'SHIPPING', admin: $admin);

        // 5) 배송완료 + **쿠폰 사용**. 여기가 쿠폰 경로 회귀 테스트다.
        $withCoupon = $this->order($customers['lee'], [
            ['오버핏 싱글 블레이저', 0, 1],
        ], stopAt: 'DELIVERED', admin: $admin, useSignupCoupon: true);

        if ($withCoupon->discount_total <= 0) {
            // 조용히 넘어가면 쿠폰이 안 먹는 걸 아무도 모른다.
            throw new \RuntimeException(
                '데모 주문에 쿠폰 할인이 적용되지 않았습니다. CouponLibrary::discountFor 를 확인하세요.',
            );
        }

        // 6) 배송완료 — 반품 신청 대상으로 남겨둔다 (고객 화면에 버튼이 보인다)
        $this->order($customers['park'], [['와이드 스트레이트 데님 팬츠', 0, 2]], stopAt: 'DELIVERED', admin: $admin);

        // 7) 고객 취소
        $canceled = $this->order($customers['choi'], [['캔버스 하이탑 스니커즈', 0, 1]], stopAt: 'PENDING');
        $this->orders->cancel($canceled->id, '고객 변심으로 취소');

        // 8) 반품 진행중 (접수) — 관리자 반품 화면에 처리할 건이 하나 있어야 한다
        $pendingReturn = $this->order($customers['kim'], [['데일리 스퀘어 백팩', 0, 1]], stopAt: 'DELIVERED', admin: $admin);
        $this->returns->create($pendingReturn->id, [
            'type' => 'RETURN',
            'reason' => 'CHANGE_OF_MIND',
            'reason_detail' => '생각보다 커서 반품하고 싶습니다.',
            'items' => [['order_item_id' => $pendingReturn->items()->first()->id, 'quantity' => 1]],
        ]);

        // 9) 교환 처리완료 — 완료된 이력도 하나 있어야 화면이 실제처럼 보인다
        $exchanged = $this->order($customers['lee'], [['헤비 크루넥 스웨트셔츠', 0, 1]], stopAt: 'DELIVERED', admin: $admin);
        $this->completeExchange($exchanged, $admin);

        // 10) 전량 반품 → 환불완료
        $refunded = $this->order($customers['park'], [['스웨이드 첼시 부츠', 0, 1]], stopAt: 'DELIVERED', admin: $admin);
        $this->completeRefund($refunded, $admin);
    }

    /**
     * 주문 하나를 만들고 원하는 상태까지 밀어놓는다.
     *
     * @param  list<array{0: string, 1: int, 2: int}>  $lines  [상품명, 조합 인덱스, 수량]
     */
    private function order(
        User $user,
        array $lines,
        string $stopAt,
        ?Admin $admin = null,
        bool $useSignupCoupon = false,
    ): Order {
        $owner = CartOwner::user($user->id);
        $this->carts->clear($owner);

        foreach ($lines as [$productName, $variantIndex, $quantity]) {
            $this->carts->add($owner, $this->variantId($productName, $variantIndex), $quantity);
        }

        $userCouponId = null;

        if ($useSignupCoupon) {
            // 정액 5,000원 가입 쿠폰. 최소 주문금액이 있으므로 고가 상품 주문에만 붙인다.
            $userCouponId = $this->coupons->usableFor($user->id, 1_000_000)->firstWhere('name', '신규가입 5,000원 할인')['id'] ?? null;
        }

        $order = $this->orders->createFromCart($owner, [
            'orderer_name' => $user->name,
            'orderer_phone' => '010-1234-5678',
            'orderer_email' => $user->email,
            'receiver_name' => $user->name,
            'receiver_phone' => '010-1234-5678',
            'postcode' => '06236',
            'address1' => '서울특별시 강남구 테헤란로 123',
            'address2' => '8층',
            'delivery_memo' => '부재 시 경비실에 맡겨주세요.',
            'user_coupon_id' => $userCouponId,
            'payment_method' => 'BANK_TRANSFER',
            'depositor_name' => null,
        ], $user->id);

        $payment = $this->payments->requestBankTransfer($order);

        if ($stopAt === 'PENDING') {
            return $order->fresh();
        }

        $this->payments->confirmDeposit($payment->id, $admin->id, '입금 확인 완료');

        if ($stopAt === 'PAID') {
            return $order->fresh();
        }

        $this->shipments->markPreparing($order->id);

        if ($stopAt === 'PREPARING') {
            return $order->fresh();
        }

        $this->shipments->ship($order->id, [
            'carrier' => 'CJ',
            'tracking_no' => (string) random_int(100000000000, 999999999999),
            'memo' => null,
        ], $admin->id);

        if ($stopAt === 'SHIPPING') {
            return $order->fresh();
        }

        $this->shipments->markDelivered($order->id);

        return $order->fresh();
    }

    /** 교환을 끝까지 밀어놓는다. */
    private function completeExchange(Order $order, Admin $admin): void
    {
        $item = $order->items()->first();

        // 같은 상품의 다른 조합으로만 교환된다 (schema-draft.md §11.7).
        $other = Product::query()
            ->whereKey($item->product_id)
            ->firstOrFail()
            ->variants()
            ->whereKeyNot($item->product_variant_id)
            ->where('is_active', true)
            ->first();

        if ($other === null) {
            return;
        }

        $return = $this->returns->create($order->id, [
            'type' => 'EXCHANGE',
            'reason' => 'SIZE_OR_COLOR',
            'reason_detail' => '한 사이즈 큰 것으로 교환 부탁드립니다.',
            'items' => [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'exchange_variant_id' => $other->id,
            ]],
        ]);

        $this->returns->approve($return->id, $admin->id, ['responsibility' => 'CUSTOMER', 'restock' => true]);
        $this->returns->registerPickup($return->id, $admin->id, [
            'pickup_carrier' => 'CJ',
            'pickup_tracking_no' => (string) random_int(100000000000, 999999999999),
        ]);
        $this->returns->markReceived($return->id, $admin->id, ['restock' => true]);
        $this->returns->complete($return->id, $admin->id, [
            'exchange_carrier' => 'CJ',
            'exchange_tracking_no' => (string) random_int(100000000000, 999999999999),
            'admin_memo' => '교환품 발송 완료',
        ]);
    }

    /** 불량 전량 반품 → 주문이 REFUNDED 로 내려간다. */
    private function completeRefund(Order $order, Admin $admin): void
    {
        $return = $this->returns->create($order->id, [
            'type' => 'RETURN',
            'reason' => 'DEFECTIVE',
            'reason_detail' => '밑창 접착이 떨어져 있습니다.',
            'items' => [['order_item_id' => $order->items()->first()->id, 'quantity' => 1]],
        ]);

        $this->returns->approve($return->id, $admin->id, ['responsibility' => 'SELLER', 'restock' => false]);
        $this->returns->markReceived($return->id, $admin->id, [
            'restock' => false,
            'admin_memo' => '재판매 불가. 폐기 처리.',
        ]);
        $this->returns->complete($return->id, $admin->id);
    }

    private function variantId(string $productName, int $index): int
    {
        return Product::query()
            ->where('name', $productName)
            ->firstOrFail()
            ->variants()
            ->orderBy('id')
            ->get()
            ->get($index)
            ->id;
    }

    /* ------------------------------------------------------------ 문의 · 메모 */

    /**
     * @param  array<string, User>  $customers
     */
    private function seedInquiries(array $customers, Admin $admin): void
    {
        $answered = $this->inquiries->create($customers['kim']->id, [
            'category' => 'DELIVERY',
            'title' => '배송이 언제쯤 도착할까요?',
            'content' => "주문한 지 이틀 됐는데 아직 송장이 안 뜹니다.\n확인 부탁드립니다.",
            'order_id' => Order::query()->where('user_id', $customers['kim']->id)->orderBy('id')->value('id'),
        ]);

        $this->inquiries->answer(
            $answered->id,
            $admin->id,
            "안녕하세요.\n오늘 출고되어 내일 도착 예정입니다. 주문내역에서 송장번호로 조회하실 수 있습니다.",
        );

        // 미답변 2건 — 관리자 회원목록에 '미답변' 배지가 뜬다
        $this->inquiries->create($customers['lee']->id, [
            'category' => 'RETURN_EXCHANGE',
            'title' => '사이즈 교환 절차가 궁금합니다',
            'content' => 'M 사이즈로 교환하고 싶은데 배송비는 어떻게 되나요?',
            'order_id' => null,
        ]);

        $this->inquiries->create($customers['park']->id, [
            'category' => 'PRODUCT',
            'title' => '치노 팬츠 재입고 문의',
            'content' => '32 사이즈 올리브 색상 재입고 예정이 있을까요?',
            'order_id' => null,
        ]);
    }

    /**
     * @param  array<string, User>  $customers
     */
    private function seedMemos(array $customers, Admin $admin): void
    {
        $this->members->addMemo(
            $customers['kim']->id,
            $admin->id,
            '전화 응대 이력 있음. 배송 관련 문의가 잦은 편.',
        );

        $this->members->addMemo(
            $customers['park']->id,
            $admin->id,
            '재입고 알림 요청 (치노 팬츠 올리브 32).',
        );
    }
}
