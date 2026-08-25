<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Setting;

use App\Enums\Admin\AdminStatus;
use App\Exceptions\AdminPolicyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAccountRequest;
use App\Http\Requests\Admin\AdminPasswordResetRequest;
use App\Libraries\Admin\AdminAccountLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자설정 > 관리자관리 (CLAUDE.md §7.6 (2)).
 *
 * 안전장치(마지막 최고관리자 보호, 본인 권한 자가수정 차단)는
 * AdminAccountLibrary 안에 있다. 여기서 우회하지 않는다.
 */
class AdminController extends Controller
{
    public function __construct(
        private readonly AdminAccountLibrary $accounts,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Setting/Admin/Index', [
            'admins' => $this->accounts->getList(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Setting/Admin/Edit', [
            'admin' => null,
            'roleOptions' => $this->accounts->roleOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function edit(int $admin): Response
    {
        return Inertia::render('Admin/Setting/Admin/Edit', [
            'admin' => $this->accounts->getDetail($admin),
            'roleOptions' => $this->accounts->roleOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(AdminAccountRequest $request): RedirectResponse
    {
        $this->accounts->create($request->validated());

        return redirect()
            ->route('admin.settings.admins.index')
            ->with('status', '관리자를 생성했습니다.');
    }

    public function update(AdminAccountRequest $request, int $admin): RedirectResponse
    {
        try {
            $this->accounts->update($admin, $request->validated(), $request->user('admin'));
        } catch (AdminPolicyException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '관리자 정보를 수정했습니다.');
    }

    public function resetPassword(AdminPasswordResetRequest $request, int $admin): RedirectResponse
    {
        $this->accounts->resetPassword($admin, $request->validated()['password']);

        return back()->with('status', '비밀번호를 초기화했습니다.');
    }

    public function suspend(Request $request, int $admin): RedirectResponse
    {
        try {
            $this->accounts->suspend($admin, $request->user('admin'));
        } catch (AdminPolicyException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '관리자를 정지했습니다.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (AdminStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            AdminStatus::cases(),
        );
    }
}
