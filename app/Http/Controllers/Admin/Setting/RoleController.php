<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Setting;

use App\Exceptions\AdminPolicyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RolePermissionRequest;
use App\Http\Requests\Admin\RoleRequest;
use App\Libraries\Admin\AdminMenuLibrary;
use App\Libraries\Admin\AdminRoleLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 관리자설정 > 권한설정 (CLAUDE.md §7.6 (1)).
 *
 * 컨트롤러는 요청 받기 → 라이브러리 호출 → 응답만 한다 (§4.2).
 * 안전장치 판단은 전부 AdminRoleLibrary 안에 있다.
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly AdminRoleLibrary $roles,
        private readonly AdminMenuLibrary $menus,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Setting/Role/Index', [
            'roles' => $this->roles->getList(),
        ]);
    }

    public function edit(int $role): Response
    {
        $detail = $this->roles->getDetail($role);

        return Inertia::render('Admin/Setting/Role/Edit', [
            'role' => $detail['role'],
            'permissions' => $detail['permissions'],
            'menuTree' => $this->menus->tree(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        try {
            $this->roles->create($request->validated());
        } catch (AdminPolicyException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.settings.roles.index')
            ->with('status', '역할을 추가했습니다.');
    }

    public function update(RoleRequest $request, int $role): RedirectResponse
    {
        $this->roles->update($role, $request->validated());

        return back()->with('status', '역할 정보를 수정했습니다.');
    }

    public function updatePermissions(RolePermissionRequest $request, int $role): RedirectResponse
    {
        try {
            $this->roles->updatePermissions(
                $role,
                $request->validated()['permissions'],
                $request->user('admin'),
            );
        } catch (AdminPolicyException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('status', '권한을 저장했습니다.');
    }

    public function destroy(Request $request, int $role): RedirectResponse
    {
        try {
            $this->roles->delete($role, $request->user('admin'));
        } catch (AdminPolicyException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.settings.roles.index')
            ->with('status', '역할을 삭제했습니다.');
    }
}
