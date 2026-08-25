<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Product\ProductReviewLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 상품 후기.
 *
 * 회원 전용이다 — 구매 이력으로 자격을 확인하므로 비회원은 애초에 쓸 수 없다.
 */
class ProductReviewController extends Controller
{
    public function __construct(private readonly ProductReviewLibrary $reviews) {}

    /** 내가 쓸 수 있는 후기 + 내가 쓴 후기. */
    public function index(Request $request): Response
    {
        return Inertia::render('Store/Review/Index', [
            'writable' => $this->reviews->writableItems($request->user()->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_item_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->reviews->create($request->user()->id, $validated);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '후기가 등록되었습니다. 감사합니다.');
    }

    public function destroy(Request $request, int $review): RedirectResponse
    {
        try {
            $this->reviews->deleteByOwner($review, $request->user()->id);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', '후기를 삭제했습니다.');
    }
}
