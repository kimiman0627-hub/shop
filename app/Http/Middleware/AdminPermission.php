<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Admin\PermissionAction;
use App\Libraries\Admin\AdminPermissionLibrary;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 관리자 페이지 접근 통제 (CLAUDE.md §7.5).
 *
 * 사용: ->middleware('admin.permission:PRODUCT_LIST,READ')
 *
 * 화면에서 메뉴를 숨기는 것과 별개로, 차단의 근거는 항상 이 미들웨어다.
 */
class AdminPermission
{
    public function __construct(
        private readonly AdminPermissionLibrary $permissions,
    ) {}

    public function handle(Request $request, Closure $next, string $menuCode, string $action = 'READ'): Response
    {
        $admin = $request->user('admin');

        if ($admin === null) {
            return redirect()->route('admin.login');
        }

        // 로그인 후 정지된 관리자는 즉시 차단한다.
        if (! $admin->canLogin()) {
            auth('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['login_id' => '정지된 계정입니다.']);
        }

        $permissionAction = PermissionAction::tryFrom(strtoupper($action)) ?? PermissionAction::READ;

        if (! $this->permissions->allows($admin, $menuCode, $permissionAction)) {
            abort(403, '이 페이지에 접근할 권한이 없습니다.');
        }

        return $next($request);
    }
}
