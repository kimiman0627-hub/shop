<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Product\ProductQuestionLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 고객 상품 문의 (Q&A).
 *
 * 답변을 전달할 창구가 있어야 하므로 회원 전용이다.
 * 비회원은 상담 채널(config('shop.support.channel'))로 유도한다.
 */
class ProductQuestionController extends Controller
{
    public function __construct(private readonly ProductQuestionLibrary $questions) {}

    public function store(Request $request, int $product): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:5', 'max:1000'],
            'is_secret' => ['required', 'boolean'],
        ]);

        try {
            $this->questions->create($product, $request->user()->id, $validated);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '문의가 등록되었습니다. 답변은 이 화면에서 확인하실 수 있습니다.');
    }

    public function destroy(Request $request, int $question): RedirectResponse
    {
        try {
            $this->questions->deleteByOwner($question, $request->user()->id);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['question' => $e->getMessage()]);
        }

        return back()->with('status', '문의를 삭제했습니다.');
    }
}
