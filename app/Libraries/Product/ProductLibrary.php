<?php

declare(strict_types=1);

namespace App\Libraries\Product;

use App\Enums\Product\ProductImageType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ShippingFeeType;
use App\Exceptions\DomainRuleException;
use App\Libraries\Shipping\ShippingPolicyLibrary;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\SlugMaker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 상품 관리 (docs/schema-draft.md §2.2~2.3).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class ProductLibrary
{
    public function __construct(
        private readonly SlugMaker $slugs,
        private readonly ProductImageLibrary $images,
        // 배송비 안내를 위해 정책을 읽는다. ShippingPolicyLibrary 는
        // ProductLibrary 를 부르지 않으므로 순환 참조가 아니다 (CLAUDE.md §4.2).
        private readonly ShippingPolicyLibrary $shipping,
    ) {}

    /**
     * 관리자 상품 목록.
     *
     * @param  array{keyword?: string|null, category_id?: int|null, status?: string|null}  $filters
     */
    public function getAdminList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return Product::query()
            ->with(['category:id,name'])
            // N+1 방지 + 재고 합계를 한 번에 (CLAUDE.md §9).
            ->withSum('variants as stock_total', 'stock_quantity')
            ->withSum('variants as reserved_total', 'reserved_quantity')
            ->when($keyword !== '', function ($q) use ($keyword) {
                // ILIKE 는 PostgreSQL 전용이라 쓰지 않는다. 양쪽 소문자로 맞춘다 (CLAUDE.md §5.2).
                $q->whereLike('name', '%'.$keyword.'%', caseSensitive: false);
            })
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->ordered()
            ->paginate(config('shop.per_page.product'))
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                // 이미지 테이블을 조인하지 않으려고 대표 경로를 비정규화해뒀다.
                'thumbnail_url' => $p->thumbnail_path === null
                    ? null
                    : Storage::disk(config('shop.image.disk'))->url($p->thumbnail_path),
                'category_name' => $p->category?->name,
                'base_price' => $p->base_price,
                'sale_price' => $p->sale_price,
                'display_price' => $p->displayPrice(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'stock_total' => (int) ($p->stock_total ?? 0),
                'reserved_total' => (int) ($p->reserved_total ?? 0),
                'available_total' => (int) ($p->stock_total ?? 0) - (int) ($p->reserved_total ?? 0),
            ]);
    }

    /**
     * 고객 상품 목록. 노출 상태인 상품만.
     *
     * 관리자용(getAdminList)과 메서드를 나눠 노출 조건 차이를 표현한다 (CLAUDE.md §4.2).
     * 재고 숫자는 고객에게 내리지 않는다 — 품절 여부만 알면 된다.
     *
     * @param  array{category_ids?: list<int>|null, keyword?: string|null}  $filters
     */
    public function getSaleList(array $filters = []): LengthAwarePaginator
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return Product::query()
            ->visible()
            ->with(['category:id,name'])
            ->withSum('variants as stock_total', 'stock_quantity')
            ->withSum('variants as reserved_total', 'reserved_quantity')
            ->when($keyword !== '', fn ($q) => $q->whereLike('name', '%'.$keyword.'%', caseSensitive: false))
            ->when(
                $filters['category_ids'] ?? null,
                fn ($q, array $ids) => $q->whereIn('category_id', $ids),
            )
            ->ordered()
            ->paginate(config('shop.per_page.product'))
            ->withQueryString()
            ->through(fn (Product $p) => $this->saleCard($p));
    }

    /**
     * id 목록으로 상품 카드를 만든다. **넘긴 순서를 그대로 지킨다.**
     *
     * 추천·최근 본 상품처럼 "순서 자체가 결과" 인 목록에서 쓴다.
     * `whereIn` 은 순서를 보장하지 않으므로 조회 후 다시 정렬한다.
     *
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>
     */
    public function cardsFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $products = Product::query()
            ->visible()
            ->with(['category:id,name'])
            ->withSum('variants as stock_total', 'stock_quantity')
            ->withSum('variants as reserved_total', 'reserved_quantity')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $cards = [];

        foreach ($productIds as $id) {
            $product = $products->get($id);

            // 그 사이 숨김 처리됐거나 삭제된 상품은 조용히 뺀다.
            if ($product !== null) {
                $cards[] = $this->saleCard($product);
            }
        }

        return $cards;
    }

    /**
     * 고객 상품 상세. 조합형 옵션 선택에 필요한 것만 내린다.
     *
     * 프론트는 이 데이터로 '1단계 선택 → 2단계 후보 필터링' 을 계산한다
     * (docs/schema-draft.md §2.3). 없는 조합은 애초에 variant 가 없어 후보에서 빠진다.
     *
     * @return array<string, mixed>
     */
    public function getSaleDetail(string $slug): array
    {
        $product = Product::query()
            ->visible()
            ->with(['category:id,name', 'options.values', 'variants.optionValues', 'shippingPolicy'])
            ->where('slug', $slug)
            ->firstOrFail();

        $displayPrice = $product->displayPrice();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'summary' => $product->summary,
            'description' => $product->description,
            'category_name' => $product->category?->name,
            'base_price' => $product->base_price,
            'sale_price' => $product->sale_price,
            'display_price' => $displayPrice,
            'is_discounted' => $product->isDiscounted(),
            'status' => $product->status->value,
            'is_purchasable' => $product->status->isPurchasable(),

            // 정책을 안 고른 상품은 기본 정책을 쓴다. null 을 그대로 내리면
            // 화면에 '배송비 0원' 이 뜨고, 결제 때 실제로는 부과되어 어긋난다.
            'shipping' => $this->shippingInfo($product),

            'images' => $this->images->getListFor($product->id),

            // 상세 설명 영역에 세로로 이어 붙이는 이미지. 갤러리와 별개다.
            'detail_images' => $this->images->getListFor($product->id, ProductImageType::DETAIL),

            // 평점은 비정규화 컬럼에서 읽는다. 목록·상세 모두 후기 테이블을 안 건드린다.
            'rating' => [
                'average' => $product->ratingAverage(),
                'count' => $product->review_count,
            ],

            'options' => $product->options->map(fn (ProductOption $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'values' => $o->values->map(fn (ProductOptionValue $v) => [
                    'id' => $v->id,
                    'value' => $v->value,
                ])->all(),
            ])->all(),

            // 판매 불가 조합도 내린다. 화면에서 '품절' 로 보여야 하기 때문이다.
            // 재고 숫자 자체는 내리지 않는다.
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'option_value_ids' => $v->optionValues->pluck('id')->all(),
                'additional_price' => $v->additional_price,
                'price' => $displayPrice + $v->additional_price,
                'purchasable' => $v->isPurchasable(),
            ])->all(),
        ];
    }

    /**
     * 상세 화면에 보여줄 배송비 안내.
     *
     * 실제 청구액은 주문 시 ShippingPolicyLibrary::calculateFee() 가 정한다.
     * 여기 값은 "이 상품 하나만 샀을 때" 기준 안내다.
     *
     * @return array{is_free: bool, fee: int, free_threshold: int|null}
     */
    private function shippingInfo(Product $product): array
    {
        if (! $product->shipping_fee_type->isPaid()) {
            return ['is_free' => true, 'fee' => 0, 'free_threshold' => null];
        }

        // 상품이 정책을 안 골랐으면 기본 정책이 적용된다 (schema-draft.md §6.2).
        $policy = $product->shippingPolicy ?? $this->shipping->defaultPolicy();

        return [
            'is_free' => false,
            'fee' => $policy->base_fee,
            'free_threshold' => $policy->free_threshold,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function saleCard(Product $p): array
    {
        $available = (int) ($p->stock_total ?? 0) - (int) ($p->reserved_total ?? 0);

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'summary' => $p->summary,
            'category_name' => $p->category?->name,
            'base_price' => $p->base_price,
            'sale_price' => $p->sale_price,
            'display_price' => $p->displayPrice(),
            'is_discounted' => $p->isDiscounted(),
            'thumbnail_url' => $p->thumbnail_path === null
                ? null
                : Storage::disk(config('shop.image.disk'))->url($p->thumbnail_path),
            // 재고 숫자가 아니라 '살 수 있는가' 만 내린다.
            'sold_out' => ! $p->status->isPurchasable() || $available <= 0,

            // 목록에서도 별점을 보여준다. 비정규화 컬럼이라 조인이 없다.
            'rating_average' => $p->ratingAverage(),
            'review_count' => $p->review_count,
        ];
    }

    /**
     * 수정 화면용 상세. 옵션·조합을 프론트가 그대로 다룰 수 있는 형태로 편다.
     *
     * @return array<string, mixed>
     */
    public function getDetail(int $productId): array
    {
        $product = Product::query()
            ->with(['options.values', 'variants.optionValues.option', 'images'])
            ->findOrFail($productId);

        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'summary' => $product->summary,
            'description' => $product->description,
            'base_price' => $product->base_price,
            'sale_price' => $product->sale_price,
            'status' => $product->status->value,
            'shipping_fee_type' => $product->shipping_fee_type->value,
            'shipping_policy_id' => $product->shipping_policy_id,
            'sort_order' => $product->sort_order,

            'options' => $product->options->map(fn (ProductOption $o) => [
                'name' => $o->name,
                'values' => $o->values->pluck('value')->all(),
            ])->all(),

            'images' => $this->images->getListFor($product->id),
            'detail_images' => $this->images->getListFor($product->id, ProductImageType::DETAIL),

            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'values' => $this->variantValueLabels($v),
                'sku' => $v->sku,
                'additional_price' => $v->additional_price,
                'stock_quantity' => $v->stock_quantity,
                'reserved_quantity' => $v->reserved_quantity,
                'is_active' => $v->is_active,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->create($this->baseAttributes($data));

            $this->syncOptionsAndVariants($product, $data['options'] ?? [], $data['variants'] ?? []);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $productId, array $data): Product
    {
        $product = Product::query()->findOrFail($productId);

        return DB::transaction(function () use ($product, $data) {
            $product->update($this->baseAttributes($data, $product->id));

            $this->syncOptionsAndVariants($product, $data['options'] ?? [], $data['variants'] ?? []);

            return $product;
        });
    }

    public function delete(int $productId): void
    {
        $product = Product::query()->with('variants')->findOrFail($productId);

        // 결제 진행중인 조합이 있으면 지우지 않는다.
        $reserved = $product->variants->sum('reserved_quantity');

        if ($reserved > 0) {
            throw new DomainRuleException(
                "결제 진행중인 주문이 있습니다(예약 {$reserved}개). 잠시 후 다시 시도하세요.",
            );
        }

        // 소프트 삭제를 쓰지 않기로 했으므로(schema-draft.md §1) 이 가드가 이력 보호의 핵심이다.
        // DB 는 order_items FK 가 nullOnDelete 라 지워도 주문서는 스냅샷으로 남지만,
        // 관리자 화면의 '이 주문의 상품 보기' 링크가 끊긴다.
        $orderedCount = OrderItem::query()->where('product_id', $product->id)->count();

        if ($orderedCount > 0) {
            throw new DomainRuleException(
                "주문 이력이 있는 상품은 삭제할 수 없습니다({$orderedCount}건). "
                ."판매를 중단하려면 상태를 '숨김'으로 바꾸세요.",
            );
        }

        $product->delete();

        // DB 는 cascade 로 product_images 행만 지운다. 파일은 아무도 안 지우므로 여기서 처리한다.
        // 순서가 중요하다 — 행을 먼저 지운다. 파일 삭제가 실패하면 고아 파일이 남지만,
        // 반대로 하면 깨진 이미지를 가리키는 행이 남아 화면이 망가진다.
        $this->images->deleteAllFor($product->id);
    }

    // -------------------------------------------------------- 옵션 · 조합 동기화

    /**
     * 옵션 그룹/값을 저장하고, 조합(variant)을 맞춘다.
     *
     * 조합은 프론트에서 **옵션 값 라벨의 조합**으로 넘어온다. 새로 만든 값은
     * 아직 id 가 없기 때문이다. 여기서 라벨 → id 로 해석한다.
     *
     * @param  list<array{name: string, values: list<string>}>  $options
     * @param  list<array{values: list<string>, sku: string|null, additional_price: int, stock_quantity: int, is_active: bool}>  $variants
     */
    private function syncOptionsAndVariants(Product $product, array $options, array $variants): void
    {
        $this->assertOptionsSane($options);

        // 조합 이름은 옵션을 손대기 **전에** 만들어 둔다.
        // syncOptions 가 빠진 옵션 값을 지우면 product_variant_values 가 cascade 로 끊겨
        // 이후에는 '빨강 / S' 가 'S' 로 보인다. 오류 메시지와 조합 매칭이 둘 다 틀어진다.
        $snapshot = $product->variants()->with('optionValues.option')->get();

        $signatureById = $snapshot
            ->mapWithKeys(fn (ProductVariant $v) => [$v->id => $this->signature($this->variantValueLabels($v))])
            ->all();

        $valueIdByPath = $this->syncOptions($product, $options);

        $this->syncVariants($product, $options, $variants, $valueIdByPath, $signatureById);
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @return array<string, int> "옵션순번:값라벨" => option_value_id
     */
    private function syncOptions(Product $product, array $options): array
    {
        $existing = $product->options()->with('values')->get();

        // 옵션 그룹은 순번(sort_order)으로 맞춘다. 이름이 바뀌어도 같은 자리면 같은 그룹이다.
        $map = [];

        foreach ($options as $index => $option) {
            $group = $existing->firstWhere('sort_order', $index)
                ?? new ProductOption(['product_id' => $product->id]);

            $group->fill([
                'product_id' => $product->id,
                'name' => $option['name'],
                'sort_order' => $index,
            ])->save();

            $keptValueIds = [];

            foreach ($option['values'] as $vIndex => $label) {
                $value = ProductOptionValue::query()->firstOrNew([
                    'product_option_id' => $group->id,
                    'value' => $label,
                ]);

                $value->fill(['sort_order' => $vIndex])->save();

                $keptValueIds[] = $value->id;
                $map[$index.':'.$label] = $value->id;
            }

            // 빠진 값 삭제 → product_variant_values 가 cascade 로 정리되고,
            // 그 값을 쓰던 조합은 아래 syncVariants 에서 없어진다.
            ProductOptionValue::query()
                ->where('product_option_id', $group->id)
                ->whereNotIn('id', $keptValueIds === [] ? [0] : $keptValueIds)
                ->delete();
        }

        // 빠진 옵션 그룹 삭제
        ProductOption::query()
            ->where('product_id', $product->id)
            ->where('sort_order', '>=', count($options))
            ->delete();

        return $map;
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @param  list<array<string, mixed>>  $variants
     * @param  array<string, int>  $valueIdByPath
     * @param  array<int, string>  $signatureById  옵션 수정 전에 찍어둔 조합 이름
     */
    private function syncVariants(
        Product $product,
        array $options,
        array $variants,
        array $valueIdByPath,
        array $signatureById,
    ): void {
        // 옵션이 없는 상품도 조합을 1개 만든다. 주문·장바구니가 항상 variant 를
        // 가리키게 해서 분기를 없앤다 (schema-draft.md §2.3).
        if ($options === []) {
            $variants = [
                ($variants[0] ?? []) + [
                    'values' => [],
                    'sku' => null,
                    'additional_price' => 0,
                    'stock_quantity' => 0,
                    'is_active' => true,
                ],
            ];
            $variants[0]['values'] = [];
        }

        // 옵션 수정 전에 찍어둔 이름으로 매칭한다. 지금 다시 계산하면 이미 끊긴 링크 때문에 틀어진다.
        $existing = $product->variants()->get();
        $existingBySignature = $existing->keyBy(fn (ProductVariant $v) => $signatureById[$v->id] ?? $this->signature([]));

        $keptIds = [];

        foreach ($variants as $row) {
            $labels = array_values($row['values'] ?? []);
            $signature = $this->signature($labels);

            /** @var ProductVariant|null $variant */
            $variant = $existingBySignature->get($signature);

            $stock = (int) ($row['stock_quantity'] ?? 0);

            if ($variant !== null && $stock < $variant->reserved_quantity) {
                // 예약분보다 실물이 적어지면 판매가능이 음수가 된다.
                throw new DomainRuleException(
                    "'{$signature}' 조합의 재고를 {$stock}개로 줄일 수 없습니다. "
                    ."결제 진행중인 수량이 {$variant->reserved_quantity}개입니다.",
                    'variants',
                );
            }

            $variant ??= new ProductVariant(['product_id' => $product->id]);

            $variant->fill([
                'product_id' => $product->id,
                'sku' => $this->resolveSku($row['sku'] ?? null, $product, $variant),
                'additional_price' => (int) ($row['additional_price'] ?? 0),
                'stock_quantity' => $stock,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);

            // reserved_quantity 는 시스템이 관리한다. 폼에서 절대 덮어쓰지 않는다.
            $variant->save();

            $valueIds = [];

            foreach ($labels as $optionIndex => $label) {
                $id = $valueIdByPath[$optionIndex.':'.$label] ?? null;

                if ($id === null) {
                    throw new DomainRuleException(
                        "조합에 없는 옵션 값이 있습니다: {$label}",
                        'variants',
                    );
                }

                $valueIds[] = $id;
            }

            $variant->optionValues()->sync($valueIds);

            $keptIds[] = $variant->id;
        }

        // 사라진 조합 정리. 예약이 걸린 조합은 지우지 않는다.
        $removable = $product->variants()
            ->whereNotIn('id', $keptIds === [] ? [0] : $keptIds)
            ->get();

        foreach ($removable as $variant) {
            if ($variant->reserved_quantity > 0) {
                $name = $signatureById[$variant->id] ?? $variant->sku;

                throw new DomainRuleException(
                    "결제 진행중인 조합은 삭제할 수 없습니다: {$name}",
                    'variants',
                );
            }

            $variant->delete();
        }
    }

    // ------------------------------------------------------------------ 내부

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     */
    private function assertOptionsSane(array $options): void
    {
        if (count($options) > 3) {
            throw new DomainRuleException('옵션은 최대 3단계까지입니다.', 'options');
        }

        foreach ($options as $index => $option) {
            $values = $option['values'] ?? [];

            if ($values === []) {
                throw new DomainRuleException(
                    ($index + 1).'번째 옵션에 값이 없습니다.',
                    'options',
                );
            }

            if (count($values) !== count(array_unique($values))) {
                throw new DomainRuleException(
                    ($index + 1).'번째 옵션에 중복된 값이 있습니다.',
                    'options',
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function baseAttributes(array $data, ?int $ignoreId = null): array
    {
        $feeType = ShippingFeeType::from($data['shipping_fee_type']);

        return [
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name'], $ignoreId),
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'base_price' => (int) $data['base_price'],
            'sale_price' => $data['sale_price'] === null ? null : (int) $data['sale_price'],
            'status' => ProductStatus::from($data['status']),
            'shipping_fee_type' => $feeType,
            // 무료배송 상품은 정책을 붙들고 있을 이유가 없다.
            'shipping_policy_id' => $feeType->isPaid() ? ($data['shipping_policy_id'] ?? null) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId): string
    {
        return $this->slugs->make(
            $slug,
            $name,
            fn (string $candidate) => Product::query()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists(),
        );
    }

    private function resolveSku(?string $sku, Product $product, ProductVariant $variant): string
    {
        if ($sku !== null && trim($sku) !== '') {
            return trim($sku);
        }

        // 이미 있는 조합이면 기존 SKU 를 지킨다.
        // SKU 는 재고·물류 식별자라, 저장할 때마다 바뀌면 바깥 시스템과 어긋난다.
        if ($variant->exists && $variant->sku !== null && $variant->sku !== '') {
            return $variant->sku;
        }

        /*
         * **상품명·slug 에서 만들지 않는다.** 한글 slug 는 `Str::slug` 가 전부 버려서
         * 빈 문자열이 되고(docs/worklog.md #3), 그러면 모든 상품이 같은 base 를 쓴다.
         * 실제로 옵션 없는 상품들이 `SKU`, `SKU-2` 로 생성되고 있었다.
         *
         * 상품 id 는 항상 ASCII 이고 바뀌지 않는다. 조합 구분은 랜덤 4자리가 맡는다 —
         * **옵션이 없어도 붙인다.** 안 붙이면 옵션 없는 상품끼리 전부 충돌한다.
         */
        $base = 'P'.$product->id.'-'.Str::upper(Str::random(4));

        $candidate = $base;
        $suffix = 2;

        while ($this->skuTaken($candidate, $variant->id)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    private function skuTaken(string $sku, ?int $ignoreId): bool
    {
        return ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * 조합을 문자열 하나로 표현한다. 기존 조합과 새 조합을 맞추는 열쇠다.
     *
     * @param  list<string>  $labels
     */
    private function signature(array $labels): string
    {
        return $labels === [] ? '__single__' : implode(' / ', $labels);
    }

    /**
     * 조합이 가진 옵션 값을 **옵션 순서대로** 나열한다.
     * 순서가 흔들리면 signature 가 달라져 기존 조합을 못 찾는다.
     *
     * @return list<string>
     */
    private function variantValueLabels(ProductVariant $variant): array
    {
        return $variant->optionValues
            ->sortBy(fn (ProductOptionValue $v) => $v->option?->sort_order ?? 0)
            ->pluck('value')
            ->values()
            ->all();
    }
}
