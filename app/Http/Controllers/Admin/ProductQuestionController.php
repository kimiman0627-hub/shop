<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Product\QuestionStatus;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Product\ProductQuestionLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 상품 문의 (PRODUCT_QNA).
 */
class ProductQuestionController extends Controller
{
    public function __construct(private readonly ProductQuestionLibrary $questions) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Question/Index', [
            'questions' => $this->questions->getAdminList($request->only(['status', 'keyword'])),
            'filters' => $request->only(['status', 'keyword']),
            'counts' => $this->questions->statusCounts(),
            'statusOptions' => QuestionStatus::options(),
        ]);
    }

    public function answer(Request $request, int $question): RedirectResponse
    {
        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->questions->answer($question, $request->user('admin')->id, $validated['answer']);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '답변을 등록했습니다.');
    }
}
