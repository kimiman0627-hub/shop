<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Product\ProductStatus;
use App\Enums\Product\ShippingFeeType;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Libraries\Product\CategoryLibrary;
use App\Libraries\Product\ProductLibrary;
use App\Libraries\Shipping\ShippingPolicyLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 상품관리 > 상품목록 (menu_code: PRODUCT_LIST).
 *
 * 컨트롤러는 요청 받기 → 라이브러리 호출 → 응답만 한다 (CLAUDE.md §4.2).
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductLibrary $products,
        private readonly CategoryLibrary $categories,
        private readonly ShippingPolicyLibrary $shipping,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'keyword' => $request->string('keyword')->toString(),
            'category_id' => $request->integer('category_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return Inertia::render('Admin/Product/Index', [
            'products' => $this->products->getAdminList($filters),
            'filters' => $filters,
            'categoryOptions' => $this->categories->parentOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Product/Edit', [
            'product' => null,
            ...$this->formOptions(),
        ]);
    }

    public function edit(int $product): Response
    {
        return Inertia::render('Admin/Product/Edit', [
            'product' => $this->products->getDetail($product),
            ...$this->formOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        try {
            $product = $this->products->create($request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.products.edit', $product->id)
            ->with('status', '상품을 등록했습니다.');
    }

    public function update(ProductRequest $request, int $product): RedirectResponse
    {
        try {
            $this->products->update($product, $request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '상품을 수정했습니다.');
    }

    public function destroy(int $product): RedirectResponse
    {
        try {
            $this->products->delete($product);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.products.index')
            ->with('status', '상품을 삭제했습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categoryOptions' => $this->categories->parentOptions(),
            'shippingOptions' => $this->shipping->selectableOptions(),
            'statusOptions' => $this->statusOptions(),
            'feeTypeOptions' => array_map(
                fn (ShippingFeeType $t) => ['value' => $t->value, 'label' => $t->label()],
                ShippingFeeType::cases(),
            ),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ProductStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            ProductStatus::cases(),
        );
    }
}
