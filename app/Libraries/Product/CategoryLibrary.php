<?php

declare(strict_types=1);

namespace App\Libraries\Product;

use App\Exceptions\DomainRuleException;
use App\Models\Category;
use App\Models\Product;
use App\Support\SlugMaker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 카테고리 관리 (docs/schema-draft.md §2.1).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class CategoryLibrary
{
    public function __construct(
        private readonly SlugMaker $slugs,
    ) {}

    /**
     * 관리자용 전체 트리. 비활성 항목도 포함한다.
     *
     * @return list<array<string, mixed>>
     */
    public function getTree(): array
    {
        $all = Category::query()->ordered()->get();

        return $this->buildTree($all, null);
    }

    /**
     * 부모 선택 드롭다운용 평면 목록. 이름 앞에 깊이만큼 들여쓰기 기호를 붙인다.
     *
     * @param  int|null  $excludeId  이 카테고리와 그 자손은 제외한다(자기 자신을 부모로 못 고르게)
     * @return list<array{id: int, label: string, depth: int, selectable: bool}>
     */
    public function parentOptions(?int $excludeId = null): array
    {
        $excluded = $excludeId === null
            ? []
            : [$excludeId, ...$this->descendantIds($excludeId)];

        return Category::query()
            ->ordered()
            ->get()
            ->reject(fn (Category $c) => in_array($c->id, $excluded, true))
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'label' => str_repeat('— ', $c->depth).$c->name,
                'depth' => $c->depth,
                // 이미 최하위 깊이면 그 아래에 자식을 못 만든다.
                'selectable' => $c->depth < Category::MAX_DEPTH - 1,
            ])
            ->values()
            ->all();
    }

    /**
     * 고객 화면용 트리. 노출 카테고리만 남긴다.
     *
     * 부모가 숨김이면 자식도 안 보인다 — 부모를 내렸는데 자식이 남으면
     * 메뉴에 고아 항목이 뜬다.
     *
     * @return list<array<string, mixed>>
     */
    public function getVisibleTree(): array
    {
        $active = Category::query()->active()->ordered()->get();

        return $this->buildTree($active, null);
    }

    /**
     * 이 카테고리와 모든 하위 카테고리 id.
     *
     * 고객이 '의류' 를 고르면 그 아래 '상의', '티셔츠' 상품까지 보여야 한다.
     *
     * @return list<int>
     */
    public function subtreeIds(int $categoryId): array
    {
        return [$categoryId, ...$this->descendantIds($categoryId)];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(int $categoryId): array
    {
        $category = Category::query()->findOrFail($categoryId);

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'depth' => $category->depth,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }

    /**
     * @param  array{parent_id: int|null, name: string, slug: string|null, sort_order: int, is_active: bool}  $data
     */
    public function create(array $data): Category
    {
        $depth = $this->depthUnder($data['parent_id']);

        if ($depth > Category::MAX_DEPTH - 1) {
            throw new DomainRuleException(
                '카테고리는 '.Category::MAX_DEPTH.'단계까지만 만들 수 있습니다.',
                'parent_id',
            );
        }

        return Category::query()->create([
            'parent_id' => $data['parent_id'],
            'name' => $data['name'],
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name']),
            'depth' => $depth,
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);
    }

    /**
     * @param  array{parent_id: int|null, name: string, slug: string|null, sort_order: int, is_active: bool}  $data
     */
    public function update(int $categoryId, array $data): Category
    {
        $category = Category::query()->findOrFail($categoryId);
        $newParentId = $data['parent_id'];

        if ($newParentId !== $category->parent_id) {
            $this->assertMovable($category, $newParentId);
        }

        return DB::transaction(function () use ($category, $data, $newParentId) {
            $newDepth = $this->depthUnder($newParentId);
            $shift = $newDepth - $category->depth;

            $category->update([
                'parent_id' => $newParentId,
                'name' => $data['name'],
                'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name'], $category->id),
                'depth' => $newDepth,
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
            ]);

            // 자기만 옮기면 자손들의 depth 가 어긋난다. 통째로 밀어준다.
            if ($shift !== 0) {
                $descendants = $this->descendantIds($category->id);

                if ($descendants !== []) {
                    $query = Category::query()->whereIn('id', $descendants);

                    // DB::raw('depth + N') 대신 increment/decrement 를 쓴다 — 이식성 (CLAUDE.md §5.1).
                    $shift > 0
                        ? $query->increment('depth', $shift)
                        : $query->decrement('depth', abs($shift));
                }
            }

            return $category;
        });
    }

    public function delete(int $categoryId): void
    {
        $category = Category::query()->withCount('children')->findOrFail($categoryId);

        // DB 는 nullOnDelete 라 자식이 조용히 최상위로 올라간다. 그건 사고다.
        if ($category->children_count > 0) {
            throw new DomainRuleException(
                "하위 카테고리가 {$category->children_count}개 있습니다. 먼저 옮기거나 삭제하세요.",
            );
        }

        // DB 는 restrictOnDelete 로 막혀 있지만, 여기서 먼저 걸러 사람이 읽을 메시지를 준다.
        $productCount = Product::query()->where('category_id', $category->id)->count();

        if ($productCount > 0) {
            throw new DomainRuleException(
                "이 카테고리를 쓰는 상품이 {$productCount}개 있습니다. 먼저 다른 카테고리로 옮기세요.",
            );
        }

        $category->delete();
    }

    /**
     * 같은 부모 안에서의 정렬 순서를 한꺼번에 저장한다.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                Category::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });
    }

    // ---------------------------------------------------------------- 내부

    private function depthUnder(?int $parentId): int
    {
        if ($parentId === null) {
            return 0;
        }

        return Category::query()->findOrFail($parentId)->depth + 1;
    }

    /**
     * 부모를 바꿀 수 있는지 확인한다. 막아야 할 경우가 둘이다.
     */
    private function assertMovable(Category $category, ?int $newParentId): void
    {
        if ($newParentId !== null) {
            // 1) 자기 자신이나 자손을 부모로 삼으면 트리가 끊어진 고리가 된다.
            if ($newParentId === $category->id
                || in_array($newParentId, $this->descendantIds($category->id), true)) {
                throw new DomainRuleException(
                    '자기 자신이나 하위 카테고리를 상위로 지정할 수 없습니다.',
                    'parent_id',
                );
            }
        }

        // 2) 자손까지 따라 내려가므로, 서브트리 높이를 더해도 최대 깊이를 넘지 않아야 한다.
        $newDepth = $this->depthUnder($newParentId);
        $height = $this->subtreeHeight($category);

        if ($newDepth + $height > Category::MAX_DEPTH - 1) {
            throw new DomainRuleException(
                '옮기면 하위 카테고리가 '.Category::MAX_DEPTH.'단계를 넘습니다.',
                'parent_id',
            );
        }
    }

    /** 이 카테고리 아래로 몇 단계가 더 있는지. 자식이 없으면 0. */
    private function subtreeHeight(Category $category): int
    {
        $descendants = $this->descendantIds($category->id);

        if ($descendants === []) {
            return 0;
        }

        $maxDepth = (int) Category::query()->whereIn('id', $descendants)->max('depth');

        return $maxDepth - $category->depth;
    }

    /**
     * 재귀 CTE 는 DB 별 문법이 갈리므로 쓰지 않는다 (CLAUDE.md §5.1).
     * 깊이가 3단계로 제한되어 있어 반복 조회로 충분하다.
     *
     * @return list<int>
     */
    private function descendantIds(int $categoryId): array
    {
        $ids = [];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            if ($children === []) {
                break;
            }

            $ids = [...$ids, ...$children];
            $frontier = $children;
        }

        return $ids;
    }

    /** slug 를 정한다. 관리자가 비워두면 이름에서 만든다 (SlugMaker 가 한글을 보존한다). */
    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        return $this->slugs->make(
            $slug,
            $name,
            fn (string $candidate) => Category::query()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists(),
        );
    }

    /**
     * @param  Collection<int, Category>  $all
     * @return list<array<string, mixed>>
     */
    private function buildTree(Collection $all, ?int $parentId): array
    {
        return $all
            ->where('parent_id', $parentId)
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'depth' => $c->depth,
                'sort_order' => $c->sort_order,
                'is_active' => $c->is_active,
                'children' => $this->buildTree($all, $c->id),
            ])
            ->values()
            ->all();
    }
}
