<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Support\InquiryStatus;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Member\InquiryLibrary;
use App\Libraries\Member\MemberLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 > 회원관리 (menu_code: MEMBER_LIST).
 *
 * 상세는 별도 페이지가 아니라 **모달**이다. 다만 `?selected=` 쿼리로 열어
 * 새로고침·뒤로가기·링크 공유가 동작하게 했다 — 모달을 상태로만 두면 다 깨진다.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly MemberLibrary $members,
        private readonly InquiryLibrary $inquiries,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'keyword' => $request->string('keyword')->toString(),
            'verified' => $request->string('verified')->toString(),
        ];

        $selectedId = $request->integer('selected') ?: null;

        return Inertia::render('Admin/Member/Index', [
            'members' => $this->members->getAdminList($filters),
            'filters' => $filters,
            'selectedId' => $selectedId,

            // 모달을 열 때만 상세를 만든다. 목록만 볼 때 헛수고를 하지 않는다.
            'detail' => $selectedId === null
                ? null
                : fn () => $this->members->getDetail($selectedId),

            'inquiryStatusOptions' => array_map(
                fn (InquiryStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                InquiryStatus::cases(),
            ),
        ]);
    }

    public function update(Request $request, int $member): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'email_verified' => ['required', 'boolean'],
        ], [], [
            'name' => '이름',
            'email' => '이메일',
        ]);

        try {
            $this->members->update($member, $validated);
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '회원 정보를 수정했습니다.');
    }

    public function storeMemo(Request $request, int $member): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ], ['content.required' => '메모 내용을 입력하세요.']);

        $this->members->addMemo($member, $request->user('admin')->id, $validated['content']);

        return back()->with('status', '메모를 남겼습니다.');
    }

    public function destroyMemo(int $member, int $memo): RedirectResponse
    {
        $this->members->deleteMemo($member, $memo);

        return back()->with('status', '메모를 삭제했습니다.');
    }

    public function answerInquiry(Request $request, int $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:2000'],
        ], ['answer.required' => '답변 내용을 입력하세요.']);

        $this->inquiries->answer($inquiry, $request->user('admin')->id, $validated['answer']);

        return back()->with('status', '문의에 답변했습니다.');
    }
}
