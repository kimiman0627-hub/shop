<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Enums\Support\InquiryCategory;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Member\InquiryLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 1:1 문의.
 *
 * 비회원은 문의를 남길 수 없다 — 답변을 전달할 곳이 없고 본인 확인도 안 된다.
 * 비회원은 주문조회 화면의 연락처로 안내한다.
 */
class InquiryController extends Controller
{
    public function __construct(
        private readonly InquiryLibrary $inquiries,
    ) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        return Inertia::render('Store/Inquiry/Index', [
            'inquiries' => $this->inquiries->myInquiries($userId),
            'orderOptions' => $this->inquiries->selectableOrders($userId),
            'categoryOptions' => InquiryCategory::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::enum(InquiryCategory::class)],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer'],
        ], [], [
            'category' => '문의 유형',
            'title' => '제목',
            'content' => '내용',
        ]);

        try {
            $this->inquiries->create($request->user()->id, $validated);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '문의를 접수했습니다. 답변은 이 화면에서 확인하실 수 있습니다.');
    }
}
