<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Libraries\Product\CategoryLibrary;
use App\Libraries\Product\ProductLibrary;
use App\Libraries\Product\ProductQuestionLibrary;
use App\Libraries\Product\ProductReviewLibrary;
use App\Libraries\Product\ProductStatLibrary;
use App\Libraries\Product\RecommendationLibrary;
use App\Support\RecentlyViewed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 스토어프론트 > 상품.
 *
 * 관리자와 같은 ProductLibrary 를 쓰되 노출 조건이 다른 메서드를 부른다
 * (getSaleList / getSaleDetail). 조회 로직을 두 벌 짜지 않는다 (CLAUDE.md §4.2).
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductLibrary $products,
        private readonly CategoryLibrary $categories,
        private readonly RecommendationLibrary $recommendations,
        private readonly ProductReviewLibrary $reviews,
        private readonly ProductQuestionLibrary $questions,
        private readonly ProductStatLibrary $productStats,
    ) {}

    public function index(Request $request): Response
    {
        $categoryId = $request->integer('category') ?: null;

        return Inertia::render('Store/Product/Index', [
            'products' => $this->products->getSaleList([
                // 상위 카테고리를 고르면 하위 상품까지 보여야 한다.
                'category_ids' => $categoryId === null
                    ? null
                    : $this->categories->subtreeIds($categoryId),
                'keyword' => $request->string('keyword')->toString(),
            ]),
            'filters' => [
                'category' => $categoryId,
                'keyword' => $request->string('keyword')->toString(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $product = $this->products->getSaleDetail($slug);
        $userId = $request->user()?->id;

        // 본 기록은 쿠키에 남긴다. 응답에 붙일 쿠키는 큐에 들어간다.
        RecentlyViewed::push($request, $product['id']);

        /*
         * 관리자 상품분석용 조회수. **여기서 세지 않으면 영영 알 수 없다** —
         * 상품 상세를 열었다는 사실은 다른 테이블에 아무 흔적도 남기지 않는다.
         */
        $this->productStats->recordView($product['id']);

        return Inertia::render('Store/Product/Show', [
            'product' => $product,

            // 이 상품을 산 사람들이 함께 산 상품. 없으면 같은 카테고리로 채운다.
            'related' => $this->recommendations->relatedTo($product['id'], 4),

            /*
             * 최근 본 상품 — **방금 본 이 상품은 뺀다.**
             * push() 로 맨 앞에 올려놨기 때문에 안 빼면 자기 자신이 첫 칸에 뜬다.
             */
            'recentlyViewed' => $this->recommendations->recentlyViewed(
                array_values(array_filter(
                    RecentlyViewed::ids($request),
                    fn (int $id) => $id !== $product['id'],
                )),
            ),

            // 후기 — 목록 + 평점 요약(평균·분포)
            'reviews' => $this->reviews->forProduct($product['id']),

            /*
             * 이 회원이 이 상품으로 쓸 수 있는 후기.
             * 비어 있으면 화면에 작성 폼이 안 뜬다 — 구매하지 않았거나 이미 썼다는 뜻이다.
             */
            'writableReviews' => $userId === null
                ? []
                : $this->reviews->writableItems($userId, $product['id']),

            // 상품 문의. 비밀글은 서버에서 내용을 지워 내린다.
            'questions' => $this->questions->forProduct($product['id'], $userId),

            // 상담 채널. url 이 비면 화면에서 버튼을 안 그린다.
            'supportChannel' => config('shop.support.channel'),
        ]);
    }
}
