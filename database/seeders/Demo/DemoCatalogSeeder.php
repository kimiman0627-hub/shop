<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Libraries\Product\CategoryLibrary;
use App\Libraries\Product\ProductImageLibrary;
use App\Libraries\Product\ProductLibrary;
use App\Models\Category;
use App\Models\ShippingPolicy;
use Illuminate\Database\Seeder;

/**
 * 데모 카테고리 · 상품.
 *
 * **모든 생성을 라이브러리로 한다.** 모델을 직접 만들면 slug 생성·depth 계산·
 * 조합 동기화 같은 불변식이 통째로 빠진다 (docs/worklog.md 밟으면 아픈 곳 §2).
 */
class DemoCatalogSeeder extends Seeder
{
    public function __construct(
        private readonly CategoryLibrary $categories,
        private readonly ProductLibrary $products,
        private readonly ProductImageLibrary $images,
    ) {}

    public function run(): void
    {
        $this->seedCategories();
        $this->seedProducts();
    }

    /* --------------------------------------------------------------- 카테고리 */

    private function seedCategories(): void
    {
        $tree = [
            '의류' => ['상의', '하의', '아우터'],
            '신발' => ['스니커즈', '부츠'],
            '가방·액세서리' => ['백팩', '모자'],
        ];

        $sort = 0;

        foreach ($tree as $topName => $children) {
            $top = $this->categories->create([
                'parent_id' => null,
                'name' => $topName,
                'slug' => null,
                'sort_order' => $sort++,
                'is_active' => true,
            ]);

            $childSort = 0;

            foreach ($children as $childName) {
                $this->categories->create([
                    'parent_id' => $top->id,
                    'name' => $childName,
                    'slug' => null,
                    'sort_order' => $childSort++,
                    'is_active' => true,
                ]);
            }
        }
    }

    /* ------------------------------------------------------------------ 상품 */

    private function seedProducts(): void
    {
        $policy = ShippingPolicy::query()->where('is_default', true)->firstOrFail();

        foreach ($this->catalog() as $index => $spec) {
            $product = $this->products->create([
                'category_id' => $this->categoryId($spec['category']),
                'name' => $spec['name'],
                'slug' => null,
                'summary' => $spec['summary'],
                'description' => $spec['description'],
                'base_price' => $spec['base_price'],
                'sale_price' => $spec['sale_price'] ?? null,
                'status' => $spec['status'] ?? 'ON_SALE',
                'shipping_fee_type' => $spec['shipping'] ?? 'PAID',
                'shipping_policy_id' => $policy->id,
                'sort_order' => $index,
                'options' => $spec['options'] ?? [],
                'variants' => $this->expandVariants($spec),
            ]);

            // 상품마다 2컷. 목록은 대표 이미지만 쓰지만 상세에서 갤러리가 보인다.
            // 실사 사진을 쓰고, 파일이 없으면 그려서 채운다 (DemoPhotoLibrary).
            $this->images->upload($product->id, [
                DemoPhotoLibrary::make($spec['code'], 0, $spec['colors'][0], $spec['colors'][1]),
                DemoPhotoLibrary::make($spec['code'], 1, $spec['colors'][1], $spec['colors'][0]),
            ]);
        }
    }

    private function categoryId(string $name): int
    {
        return Category::query()->where('name', $name)->value('id');
    }

    /**
     * 조합 한 줄 만들기. 옵션 없는 상품은 `values` 가 빈 배열이다.
     *
     * @param  list<string>  $values
     * @return array<string, mixed>
     */
    private function variant(array $values, int $stock, int $extra = 0, bool $active = true): array
    {
        return [
            'values' => $values,
            'sku' => null,
            'additional_price' => $extra,
            'stock_quantity' => $stock,
            'is_active' => $active,
        ];
    }

    /**
     * 색상 × 사이즈 조합을 펼친다.
     *
     * @param  array<string, int>  $colors  색상 => 그 색상의 사이즈별 기본 재고
     * @param  list<string>  $sizes
     * @return list<array<string, mixed>>
     */
    private function grid(array $colors, array $sizes, int $sizeExtra = 0): array
    {
        $rows = [];

        foreach ($colors as $color => $stock) {
            foreach ($sizes as $i => $size) {
                $rows[] = $this->variant([$color, $size], max(0, $stock - $i), $i === count($sizes) - 1 ? $sizeExtra : 0);
            }
        }

        return $rows;
    }

    /**
     * 데모 카탈로그.
     *
     * 일부러 섞어 놓은 것들:
     * - `SOLD_OUT` / `HIDDEN` 상품 — 고객 목록에 안 나오는지 확인용
     * - 재고 0 인 조합 — 옵션 선택 UI 에서 비활성되는지 확인용
     * - 무료배송 상품 — 배송비 계산이 유료 상품 합계로만 도는지 확인용
     * - 할인가 있는 상품 — 목록·상세의 가격 표기 확인용
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'code' => 'TEE 01',
                'category' => '상의',
                'name' => '베이직 코튼 반팔 티셔츠',
                'summary' => '20수 싱글 코튼, 사계절 데일리로 입는 기본 티셔츠',
                'description' => "무겁지 않은 20수 싱글 코튼을 사용했습니다.\n적당한 여유가 있는 레귤러 핏이라 이너로도 단독으로도 입기 좋습니다.\n\n소재: 면 100%\n세탁: 찬물 손세탁 권장",
                'base_price' => 29000,
                'sale_price' => 23000,
                'colors' => ['#f4f1ea', '#c9c2b4'],
                'options' => [
                    ['name' => '색상', 'values' => ['화이트', '블랙', '네이비']],
                    ['name' => '사이즈', 'values' => ['S', 'M', 'L', 'XL']],
                ],
                'variants' => null, // 아래에서 grid 로 채운다
                'grid' => [['화이트' => 20, '블랙' => 18, '네이비' => 12], ['S', 'M', 'L', 'XL'], 1000],
            ],
            [
                'code' => 'SHIRT 02',
                'category' => '상의',
                'name' => '옥스퍼드 버튼다운 셔츠',
                'summary' => '구김이 적은 옥스퍼드 원단, 오피스와 캐주얼 모두',
                'description' => "옥스퍼드 조직으로 짜 도톰하면서 통기성이 좋습니다.\n버튼다운 칼라라 별도 다림질 없이도 형태가 유지됩니다.\n\n소재: 면 100%",
                'base_price' => 59000,
                'colors' => ['#dfe7f0', '#8ea3bd'],
                'options' => [
                    ['name' => '색상', 'values' => ['라이트블루', '화이트']],
                    ['name' => '사이즈', 'values' => ['95', '100', '105']],
                ],
                'variants' => null,
                'grid' => [['라이트블루' => 10, '화이트' => 8], ['95', '100', '105'], 0],
            ],
            [
                'code' => 'SWEAT 03',
                'category' => '상의',
                'name' => '헤비 크루넥 스웨트셔츠',
                'summary' => '기모 없는 사계절용 헤비 코튼 스웨트',
                'description' => "밀도 높은 원단으로 세탁 후에도 늘어짐이 적습니다.\n목·소매 시보리를 두껍게 넣어 형태가 오래 갑니다.",
                'base_price' => 69000,
                'sale_price' => 55000,
                'colors' => ['#e8e2d9', '#7a7367'],
                'options' => [
                    ['name' => '색상', 'values' => ['오트밀', '차콜']],
                    ['name' => '사이즈', 'values' => ['M', 'L']],
                ],
                'variants' => null,
                'grid' => [['오트밀' => 7, '차콜' => 5], ['M', 'L'], 0],
            ],
            [
                'code' => 'DENIM 04',
                'category' => '하의',
                'name' => '와이드 스트레이트 데님 팬츠',
                'summary' => '밑단까지 일자로 떨어지는 와이드 실루엣',
                'description' => "신축성 없는 정통 데님입니다.\n무릎 아래로 좁아지지 않아 다리 라인이 깔끔하게 떨어집니다.\n\n소재: 면 100%",
                'base_price' => 79000,
                'colors' => ['#c3cfe0', '#4a5a76'],
                'options' => [
                    ['name' => '색상', 'values' => ['라이트워시', '인디고']],
                    ['name' => '사이즈', 'values' => ['28', '30', '32', '34']],
                ],
                'variants' => null,
                'grid' => [['라이트워시' => 9, '인디고' => 11], ['28', '30', '32', '34'], 0],
            ],
            [
                'code' => 'CHINO 05',
                'category' => '하의',
                'name' => '코튼 치노 팬츠',
                'summary' => '주름이 잘 잡히지 않는 코튼 트윌 치노',
                'description' => "적당한 두께의 코튼 트윌로 사계절 착용 가능합니다.\n밑위가 넉넉해 앉아 있어도 불편하지 않습니다.",
                'base_price' => 49000,
                'colors' => ['#e6dcc8', '#a08f6f'],
                'options' => [
                    ['name' => '색상', 'values' => ['베이지', '올리브']],
                    ['name' => '사이즈', 'values' => ['30', '32']],
                ],
                'variants' => null,
                'grid' => [['베이지' => 6, '올리브' => 4], ['30', '32'], 0],
            ],
            [
                'code' => 'COAT 06',
                'category' => '아우터',
                'name' => '오버핏 싱글 블레이저',
                'summary' => '어깨를 넉넉하게 뺀 셋업 가능 블레이저',
                'description' => "안감을 절반만 넣어 계절 폭이 넓습니다.\n같은 원단의 슬랙스와 셋업으로 입을 수 있습니다.",
                'base_price' => 189000,
                'sale_price' => 149000,
                'colors' => ['#d5d0c8', '#4b4740'],
                'shipping' => 'FREE',
                'options' => [
                    ['name' => '색상', 'values' => ['블랙', '베이지']],
                    ['name' => '사이즈', 'values' => ['S', 'M', 'L']],
                ],
                'variants' => null,
                'grid' => [['블랙' => 5, '베이지' => 3], ['S', 'M', 'L'], 0],
            ],
            [
                'code' => 'VEST 07',
                'category' => '아우터',
                'name' => '경량 패딩 베스트',
                'summary' => '접어서 파우치에 들어가는 경량 충전재',
                'description' => "가볍고 부피가 작아 가방에 넣고 다니기 좋습니다.\n간절기 레이어링용으로 적합합니다.",
                'base_price' => 99000,
                'colors' => ['#dbe6e4', '#3f5c56'],
                'options' => [
                    ['name' => '색상', 'values' => ['블랙', '카키']],
                    ['name' => '사이즈', 'values' => ['M', 'L']],
                ],
                'variants' => null,
                // 카키 M/L 이 0 → 옵션 선택에서 '카키' 자체가 비활성되는지 확인용
                'grid' => [['블랙' => 6, '카키' => 0], ['M', 'L'], 0],
            ],
            [
                'code' => 'SNKR 08',
                'category' => '스니커즈',
                'name' => '레더 미니멀 스니커즈',
                'summary' => '장식 없는 소가죽 로우탑',
                'description' => "천연 소가죽을 사용해 신을수록 발에 맞게 길이 듭니다.\n인솔은 분리형이라 교체할 수 있습니다.",
                'base_price' => 139000,
                'colors' => ['#f0eeea', '#b9b2a6'],
                'shipping' => 'FREE',
                'options' => [
                    ['name' => '사이즈', 'values' => ['250', '260', '270', '280']],
                ],
                'variants' => [
                    ['values' => ['250'], 'stock' => 4],
                    ['values' => ['260'], 'stock' => 7],
                    ['values' => ['270'], 'stock' => 6],
                    ['values' => ['280'], 'stock' => 0],
                ],
            ],
            [
                'code' => 'CNVS 09',
                'category' => '스니커즈',
                'name' => '캔버스 하이탑 스니커즈',
                'summary' => '두꺼운 캔버스와 고무 아웃솔',
                'description' => "발목을 덮는 하이탑입니다.\n캔버스가 도톰해 형태가 무너지지 않습니다.",
                'base_price' => 69000,
                'colors' => ['#e3e6ec', '#5b6272'],
                'options' => [
                    ['name' => '색상', 'values' => ['블랙', '아이보리']],
                    ['name' => '사이즈', 'values' => ['250', '260', '270']],
                ],
                'variants' => null,
                'grid' => [['블랙' => 8, '아이보리' => 6], ['250', '260', '270'], 0],
            ],
            [
                'code' => 'BOOT 10',
                'category' => '부츠',
                'name' => '스웨이드 첼시 부츠',
                'summary' => '밴딩으로 신고 벗는 발목 부츠',
                'description' => "양옆 밴딩으로 착용이 편합니다.\n스웨이드는 방수 스프레이를 뿌려 사용하세요.",
                'base_price' => 179000,
                'colors' => ['#d8cbbc', '#6b5445'],
                'options' => [
                    ['name' => '사이즈', 'values' => ['250', '260', '270']],
                ],
                'variants' => [
                    ['values' => ['250'], 'stock' => 3],
                    ['values' => ['260'], 'stock' => 3],
                    ['values' => ['270'], 'stock' => 2],
                ],
            ],
            [
                'code' => 'BAG 11',
                'category' => '백팩',
                'name' => '데일리 스퀘어 백팩',
                'summary' => '15인치 노트북이 들어가는 사각 백팩',
                'description' => "노트북 전용 칸이 따로 있습니다.\n바닥에 발이 달려 있어 세워둘 때 오염이 덜합니다.",
                'base_price' => 89000,
                'sale_price' => 71000,
                'colors' => ['#dde0e3', '#4f5459'],
                'options' => [
                    ['name' => '색상', 'values' => ['블랙', '그레이', '네이비']],
                ],
                'variants' => [
                    ['values' => ['블랙'], 'stock' => 12],
                    ['values' => ['그레이'], 'stock' => 9],
                    ['values' => ['네이비'], 'stock' => 5],
                ],
            ],
            [
                'code' => 'CAP 12',
                'category' => '모자',
                'name' => '워싱 코튼 볼캡',
                'summary' => '뒤 스트랩으로 사이즈 조절',
                'description' => "워싱 처리로 처음부터 자연스러운 색감입니다.\n뒤 메탈 스트랩으로 머리 둘레를 조절합니다.",
                'base_price' => 32000,
                'colors' => ['#eae4d8', '#8d8272'],
                // 옵션 없는 단일 상품. 조합이 1개만 생긴다.
                'options' => [],
                'variants' => [
                    ['values' => [], 'stock' => 25],
                ],
            ],
            [
                'code' => 'KNIT 13',
                'category' => '상의',
                'name' => '램스울 라운드 니트',
                'summary' => '겨울 시즌 상품 · 현재 품절',
                'description' => "램스울 혼방으로 따뜻하면서 가볍습니다.\n다음 시즌 재입고 예정입니다.",
                'base_price' => 89000,
                'status' => 'SOLD_OUT',
                'colors' => ['#e7dfe6', '#7d6f7c'],
                'options' => [
                    ['name' => '사이즈', 'values' => ['M', 'L']],
                ],
                'variants' => [
                    ['values' => ['M'], 'stock' => 0],
                    ['values' => ['L'], 'stock' => 0],
                ],
            ],
            [
                'code' => 'TEST 14',
                'category' => '아우터',
                'name' => '내부 검토용 샘플 코트',
                'summary' => '아직 공개하지 않은 상품',
                'description' => '기획 단계 상품입니다. 고객 화면에 노출되면 안 됩니다.',
                'base_price' => 259000,
                'status' => 'HIDDEN',
                'colors' => ['#d9d9d9', '#8a8a8a'],
                'options' => [],
                'variants' => [
                    ['values' => [], 'stock' => 2],
                ],
            ],
        ];
    }

    /**
     * catalog() 의 축약 표기(`grid`, `variants[].stock`)를 라이브러리가 받는 형태로 편다.
     *
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    private function expandVariants(array $spec): array
    {
        if (isset($spec['grid'])) {
            [$colors, $sizes, $extra] = $spec['grid'];

            return $this->grid($colors, $sizes, $extra);
        }

        return array_map(
            fn (array $row) => $this->variant($row['values'], $row['stock'], $row['extra'] ?? 0),
            $spec['variants'],
        );
    }
}
