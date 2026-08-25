<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Libraries\Product\CategoryLibrary;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 상품관리 > 카테고리 (menu_code: PRODUCT_CATEGORY).
 *
 * 컨트롤러는 요청 받기 → 라이브러리 호출 → 응답만 한다 (CLAUDE.md §4.2).
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryLibrary $categories,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Category/Index', [
            'tree' => $this->categories->getTree(),
            'parentOptions' => $this->categories->parentOptions(),
            'maxDepth' => Category::MAX_DEPTH,
        ]);
    }

    public function edit(int $category): Response
    {
        return Inertia::render('Admin/Category/Index', [
            'tree' => $this->categories->getTree(),
            // 편집 중인 카테고리와 그 자손은 상위 후보에서 뺀다 — 순환 방지.
            'parentOptions' => $this->categories->parentOptions($category),
            'maxDepth' => Category::MAX_DEPTH,
            'editing' => $this->categories->getDetail($category),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        try {
            $this->categories->create($request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', '카테고리를 추가했습니다.');
    }

    public function update(CategoryRequest $request, int $category): RedirectResponse
    {
        try {
            $this->categories->update($category, $request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', '카테고리를 수정했습니다.');
    }

    public function destroy(int $category): RedirectResponse
    {
        try {
            $this->categories->delete($category);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', '카테고리를 삭제했습니다.');
    }
}
