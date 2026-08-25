<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Libraries\Product\ProductLibrary;
use App\Libraries\Product\RecommendationLibrary;
use App\Support\RecentlyViewed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly ProductLibrary $products,
        private readonly RecommendationLibrary $recommendations,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Store/Home', [
            // 첫 페이지만 잘라 신상품 진열로 쓴다.
            'products' => $this->products->getSaleList()->take(8)->values(),

            /*
             * 추천 블록. 로그인 여부와 구매 이력에 따라 성격이 달라지므로
             * 제목까지 서버가 정해서 내린다 — 화면에서 조건 분기를 하면
             * "왜 이 문구가 나오는가" 가 두 곳에 흩어진다.
             */
            'recommend' => $this->recommendBlock($user?->id),

            // 최근 본 상품은 쿠키 기반이라 비회원도 뜬다.
            'recentlyViewed' => $this->recommendations->recentlyViewed(
                RecentlyViewed::ids($request),
            ),

            // 다시 구매하기 — 회원이고 구매 이력이 있을 때만.
            'reorder' => $user !== null
                ? $this->recommendations->reorderCandidates($user->id, 4)
                : [],
        ]);
    }

    /**
     * @return array{title: string, subtitle: string, items: list<array<string, mixed>>}
     */
    private function recommendBlock(?int $userId): array
    {
        if ($userId !== null && $this->recommendations->hasPurchaseHistory($userId)) {
            return [
                'title' => '회원님을 위한 추천',
                'subtitle' => '구매하신 상품과 함께 많이 담긴 상품이에요.',
                'items' => $this->recommendations->forUser($userId, 4),
            ];
        }

        return [
            'title' => '많이 찾는 상품',
            'subtitle' => '요즘 가장 많이 팔린 상품이에요.',
            'items' => $this->recommendations->popular(4),
        ];
    }
}
