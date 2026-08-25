<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use App\Enums\Admin\PermissionAction;
use App\Models\Admin;
use App\Models\AdminRolePermission;

/**
 * 관리자 권한 판정 (CLAUDE.md §7.2).
 *
 * 권한 레코드가 없으면 차단이 기본값이다 (allow-list).
 * SUPER_ADMIN 역할은 레코드와 무관하게 전부 통과한다.
 *
 * Request / Session / Auth 에 의존하지 않는다 — Admin 인스턴스를 인자로 받는다.
 * 덕분에 미들웨어, 커맨드, 테스트 어디서든 같은 로직을 쓴다 (CLAUDE.md §4.2).
 */
class AdminPermissionLibrary
{
    /**
     * 역할이 가진 권한을 menu_code 로 색인해 반환한다.
     *
     * @return array<string, array{can_read: bool, can_write: bool}>
     */
    public function permissionsForRole(int $adminRoleId): array
    {
        return AdminRolePermission::query()
            ->where('admin_role_id', $adminRoleId)
            ->orderBy('menu_code')
            ->get()
            ->mapWithKeys(fn (AdminRolePermission $p) => [
                $p->menu_code => [
                    'can_read' => $p->can_read,
                    'can_write' => $p->can_write,
                ],
            ])
            ->all();
    }

    /**
     * 이 관리자가 해당 메뉴에 대해 요청한 동작을 할 수 있는가.
     */
    public function allows(Admin $admin, string $menuCode, PermissionAction $action): bool
    {
        if (! $admin->canLogin()) {
            return false;
        }

        if ($admin->isSuperAdmin()) {
            return true;
        }

        $permission = AdminRolePermission::query()
            ->where('admin_role_id', $admin->admin_role_id)
            ->where('menu_code', $menuCode)
            ->first();

        if ($permission === null) {
            return false;
        }

        return match ($action) {
            PermissionAction::READ => $permission->can_read,
            // 쓰기는 조회를 전제로 한다. 읽기 없이 쓰기만 가능한 조합은 허용하지 않는다.
            PermissionAction::WRITE => $permission->can_write && $permission->can_read,
        };
    }

    /**
     * 역할의 권한을 통째로 교체한다. 권한설정 화면에서 쓴다.
     *
     * @param  array<string, array{can_read: bool, can_write: bool}>  $permissions
     */
    public function syncRolePermissions(int $adminRoleId, array $permissions): void
    {
        foreach ($permissions as $menuCode => $flags) {
            $canRead = (bool) ($flags['can_read'] ?? false);
            $canWrite = (bool) ($flags['can_write'] ?? false);

            // 쓰기가 켜지면 조회도 켠다 — 불가능한 조합을 저장하지 않는다.
            if ($canWrite) {
                $canRead = true;
            }

            AdminRolePermission::query()->updateOrCreate(
                ['admin_role_id' => $adminRoleId, 'menu_code' => $menuCode],
                ['can_read' => $canRead, 'can_write' => $canWrite],
            );
        }
    }
}
