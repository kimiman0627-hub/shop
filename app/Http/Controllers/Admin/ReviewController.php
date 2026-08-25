<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Product\ReviewStatus;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Product\ProductReviewLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 상품 후기 (PRODUCT_REVIEW).
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ProductReviewLibrary $reviews) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Review/Index', [
            'reviews' => $this->reviews->getAdminList($request->only(['status', 'rating', 'keyword'])),
            'filters' => $request->only(['status', 'rating', 'keyword']),
            'counts' => $this->reviews->statusCounts(),
            'statusOptions' => ReviewStatus::options(),
        ]);
    }

    public function status(Request $request, int $review): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:PUBLISHED,HIDDEN'],
        ]);

        try {
            $this->reviews->changeStatus($review, $validated['status']);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', '후기 노출 상태를 변경했습니다. 평점도 함께 반영됩니다.');
    }

    public function reply(Request $request, int $review): RedirectResponse
    {
        $validated = $request->validate([
            'admin_reply' => ['required', 'string', 'max:1000'],
        ]);

        $this->reviews->reply($review, $request->user('admin')->id, $validated['admin_reply']);

        return back()->with('status', '답글을 등록했습니다.');
    }
}
