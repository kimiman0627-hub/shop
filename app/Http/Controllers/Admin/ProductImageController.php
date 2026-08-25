<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Product\ProductImageType;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImageRequest;
use App\Libraries\Product\ProductImageLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 관리자 > 상품 이미지 (menu_code: PRODUCT_LIST).
 *
 * 상품이 이미 있어야 올릴 수 있다. 등록 직후 수정 화면으로 보내는 이유가 이것이다 —
 * 상품 없이 먼저 올리면 등록을 취소했을 때 고아 파일이 남는다.
 */
class ProductImageController extends Controller
{
    public function __construct(
        private readonly ProductImageLibrary $images,
    ) {}

    public function store(ProductImageRequest $request, int $product): RedirectResponse
    {
        try {
            $this->images->upload($product, $request->file('images'), $this->typeOf($request));
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '이미지를 올렸습니다.');
    }

    public function destroy(int $product, int $image): RedirectResponse
    {
        $this->images->delete($product, $image);

        return back()->with('status', '이미지를 삭제했습니다.');
    }

    public function reorder(Request $request, int $product): RedirectResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['required', 'integer'],
            'primary_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->images->reorder(
                $product,
                $validated['ordered_ids'],
                $validated['primary_id'] ?? null,
                $this->typeOf($request),
            );
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '이미지 순서를 저장했습니다.');
    }

    /**
     * 어떤 용도의 이미지인가. 값이 없거나 이상하면 갤러리로 본다 —
     * 기존 화면이 type 을 안 보내고 있고, 그쪽이 기본이다.
     */
    private function typeOf(Request $request): ProductImageType
    {
        return ProductImageType::tryFrom((string) $request->input('type'))
            ?? ProductImageType::GALLERY;
    }
}
