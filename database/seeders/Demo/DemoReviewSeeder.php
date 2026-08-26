<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\Order\OrderStatus;
use App\Enums\Product\ProductImageType;
use App\Libraries\Product\ProductImageLibrary;
use App\Libraries\Product\ProductQuestionLibrary;
use App\Libraries\Product\ProductReviewLibrary;
use App\Models\Admin;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 데모 상세 이미지 · 후기 · 상품문의.
 *
 * 후기는 **실제 구매 이력으로만** 만든다 — 시더가 규칙을 우회하면
 * 화면에서 보는 데이터가 앱이 실제로 만드는 데이터와 달라진다.
 * 그래서 배송완료된 주문 항목이 있는 만큼만 생긴다.
 */
class DemoReviewSeeder extends Seeder
{
    public function __construct(
        private readonly ProductReviewLibrary $reviews,
        private readonly ProductQuestionLibrary $questions,
        private readonly ProductImageLibrary $images,
    ) {}

    public function run(): void
    {
        $this->seedDetailImages();
        $this->seedReviews();
        $this->seedQuestions();
    }

    /* --------------------------------------------------------- 상세 이미지 */

    private function seedDetailImages(): void
    {
        // 전 상품에 넣으면 시드가 느려진다. 앞쪽 4개만 상세 이미지를 갖는다.
        $products = Product::query()->orderBy('sort_order')->limit(4)->get();

        foreach ($products as $index => $product) {
            $this->images->upload($product->id, [
                DemoPhotoLibrary::make('DETAIL', $index * 3, '#f2efe9', '#8d8578'),
                DemoPhotoLibrary::make('DETAIL', $index * 3 + 1, '#e9edf2', '#6f7c8d'),
                DemoPhotoLibrary::make('DETAIL', $index * 3 + 2, '#efe9ef', '#867889'),
            ], ProductImageType::DETAIL);
        }
    }

    /* ------------------------------------------------------------------ 후기 */

    private function seedReviews(): void
    {
        $samples = [
            [5, "사진이랑 똑같아요. 배송도 빨라서 만족합니다.\n다른 색상도 사려고요."],
            [4, '재질은 좋은데 생각보다 한 치수 크게 나온 것 같아요. 참고하세요.'],
            [5, '두 번째 구매입니다. 세탁해도 늘어나지 않아서 계속 사게 되네요.'],
            [3, '무난합니다. 가격 생각하면 이 정도면 괜찮은 편이에요.'],
            [5, '마감이 깔끔합니다. 선물용으로도 좋을 것 같아요.'],
            [4, '색감이 화면보다 살짝 어둡습니다. 그래도 마음에 들어요.'],
        ];

        // 배송완료된 주문 항목만 후기를 쓸 수 있다.
        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::DELIVERED->value))
            ->whereNotNull('product_id')
            ->with('order')
            ->orderBy('id')
            ->get();

        /*
         * **마지막 항목은 일부러 비워둔다.**
         * 전부 후기를 달아버리면 '후기 쓰기' 화면과 상품 상세의 작성 폼이
         * 영원히 빈 채로 보인다 — 만들어둔 기능을 확인할 수가 없다.
         */
        foreach ($items->slice(0, -1) as $index => $item) {
            [$rating, $content] = $samples[$index % count($samples)];

            $this->reviews->create($item->order->user_id, [
                'order_item_id' => $item->id,
                'rating' => $rating,
                'content' => $content,
            ]);
        }

        $this->replyToOne();
        $this->hideOne();
    }

    /** 판매자 답글이 달린 후기도 하나 있어야 화면이 실제처럼 보인다. */
    private function replyToOne(): void
    {
        $admin = Admin::query()->where('login_id', 'manager1')->first();
        $review = ProductReview::query()->orderBy('id')->first();

        if ($admin !== null && $review !== null) {
            $this->reviews->reply(
                $review->id,
                $admin->id,
                '소중한 후기 감사합니다. 말씀하신 색상은 다음 입고에 반영하겠습니다.',
            );
        }
    }

    /** 숨긴 후기도 하나 둔다 — 숨김이 평점에서 빠지는지 화면에서 확인할 수 있다. */
    private function hideOne(): void
    {
        $review = ProductReview::query()->orderByDesc('id')->first();

        if ($review !== null) {
            $this->reviews->changeStatus($review->id, 'HIDDEN');
        }
    }

    /* ------------------------------------------------------------------ 문의 */

    private function seedQuestions(): void
    {
        $admin = Admin::query()->where('login_id', 'manager1')->firstOrFail();
        $users = User::query()->orderBy('id')->get();
        $products = Product::query()->orderBy('sort_order')->limit(3)->get();

        if ($users->isEmpty() || $products->isEmpty()) {
            return;
        }

        $samples = [
            ['165cm 55kg인데 어떤 사이즈가 맞을까요?', false, '평소 M 사이즈를 입으신다면 M을 추천드립니다. 여유 있는 핏을 원하시면 L도 괜찮습니다.'],
            ['재입고 예정이 있을까요?', false, null],
            ['교환 시 배송비는 어떻게 되나요?', true, null],
        ];

        foreach ($samples as $index => [$content, $isSecret, $answer]) {
            $question = $this->questions->create(
                $products[$index % $products->count()]->id,
                $users[$index % $users->count()]->id,
                ['content' => $content, 'is_secret' => $isSecret],
            );

            if ($answer !== null) {
                $this->questions->answer($question->id, $admin->id, $answer);
            }
        }

        // 후기·문의도 주문처럼 과거 시각으로 흩어야 목록이 자연스럽다.
        $this->spread('product_reviews');
        $this->spread('product_questions');
    }

    /**
     * 라이브러리는 항상 now() 를 찍는다. 데모 사정으로만 과거로 민다
     * (DemoOrderSeeder::spreadOverTime 과 같은 이유).
     */
    private function spread(string $table): void
    {
        $rows = DB::table($table)->orderBy('id')->get(['id']);

        foreach ($rows->values() as $index => $row) {
            DB::table($table)->where('id', $row->id)->update([
                'created_at' => now()->subDays(($rows->count() - $index) * 2),
                'updated_at' => now()->subDays(($rows->count() - $index) * 2),
            ]);
        }
    }
}
