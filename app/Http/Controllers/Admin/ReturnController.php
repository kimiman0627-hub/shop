<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Returns\ReturnResponsibility;
use App\Enums\Returns\ReturnStatus;
use App\Enums\Returns\ReturnType;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Libraries\Order\ReturnLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자 반품·교환 (ORDER_RETURN).
 *
 * 컨트롤러는 요청 → 라이브러리 → 응답만 한다 (CLAUDE.md §4.2).
 * 상태 전이 규칙은 전부 ReturnLibrary 에 있다.
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnLibrary $returns) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Return/Index', [
            'returns' => $this->returns->getAdminList($request->only(['status', 'type', 'keyword'])),
            'filters' => $request->only(['status', 'type', 'keyword']),
            'counts' => $this->returns->statusCounts(),
            'statusOptions' => ReturnStatus::options(),
            'typeOptions' => ReturnType::options(),
            'responsibilityOptions' => ReturnResponsibility::options(),

            // 목록에서 바로 상세를 여는 방식이다. ?selected= 로 열어야
            // 새로고침·뒤로가기·링크 공유가 전부 동작한다.
            'selected' => $request->filled('selected')
                ? $this->returns->getAdminDetail((int) $request->query('selected'))
                : null,
        ]);
    }

    public function approve(Request $request, int $return): RedirectResponse
    {
        $validated = $request->validate([
            'responsibility' => ['required', 'string', 'in:CUSTOMER,SELLER'],
            'restock' => ['required', 'boolean'],
            'admin_memo' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run(fn () => $this->returns->approve($return, $this->adminId($request), $validated),
            '승인했습니다. 환불 예정 금액이 확정되었습니다.');
    }

    public function reject(Request $request, int $return): RedirectResponse
    {
        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->run(
            fn () => $this->returns->reject($return, $this->adminId($request), $validated['reject_reason']),
            '반려했습니다.',
        );
    }

    public function pickup(Request $request, int $return): RedirectResponse
    {
        $validated = $request->validate([
            'pickup_carrier' => ['required', 'string', 'max:30'],
            'pickup_tracking_no' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->run(fn () => $this->returns->registerPickup($return, $this->adminId($request), $validated),
            '회수 정보를 등록했습니다.');
    }

    public function receive(Request $request, int $return): RedirectResponse
    {
        $validated = $request->validate([
            'restock' => ['required', 'boolean'],
            'admin_memo' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run(fn () => $this->returns->markReceived($return, $this->adminId($request), $validated),
            '입고 처리했습니다.');
    }

    public function complete(Request $request, int $return): RedirectResponse
    {
        $validated = $request->validate([
            'exchange_carrier' => ['nullable', 'string', 'max:30'],
            'exchange_tracking_no' => ['nullable', 'string', 'max:50'],
            'admin_memo' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run(fn () => $this->returns->complete($return, $this->adminId($request), $validated),
            '처리를 완료했습니다.');
    }

    /**
     * 관리자 대행 접수. 전화·게시판으로 들어온 요청을 대신 넣는다.
     * 신청 기한을 적용하지 않는 유일한 경로다.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:RETURN,EXCHANGE'],
            'reason' => ['required', 'string', 'max:30'],
            'reason_detail' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.exchange_variant_id' => ['nullable', 'integer'],
        ]);

        return $this->run(
            fn () => $this->returns->create($validated['order_id'], $validated, byAdmin: true),
            '반품·교환을 접수했습니다.',
        );
    }

    private function adminId(Request $request): int
    {
        return $request->user('admin')->id;
    }

    /** 도메인 규칙 위반은 예외로 올라온다. 여기서 화면 메시지로 바꾼다 (§4.2). */
    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainRuleException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
