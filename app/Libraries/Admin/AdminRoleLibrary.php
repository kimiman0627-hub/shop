<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use App\Exceptions\AdminPolicyException;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Support\Collection;

/**
 * 관리자 역할 관리 (CLAUDE.md §7.6 (1)).
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class AdminRoleLibrary
{
    public function __construct(
        private readonly AdminPermissionLibrary $permissions,
    ) {}

    /**
     * 역할 목록. 소속 관리자 수를 함께 센다.
     *
     * @return Collection<int, array{id: int, code: string, name: string, description: string|null, admin_count: int, is_super_admin: bool}>
     */
    public function getList(): Collection
    {
        return AdminRole::query()
            ->withCount('admins')
            ->orderBy('id')
            ->get()
            ->map(fn (AdminRole $role) => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'description' => $role->description,
                'admin_count' => $role->admins_count,
                'is_super_admin' => $role->isSuperAdmin(),
            ]);
    }

    public function find(int $roleId): AdminRole
    {
        return AdminRole::query()->findOrFail($roleId);
    }

    /**
     * 역할 하나와 그 권한 맵.
     *
     * @return array{role: array{id: int, code: string, name: string, description: string|null, is_super_admin: bool}, permissions: array<string, array{can_read: bool, can_write: bool}>}
     */
    public function getDetail(int $roleId): array
    {
        $role = $this->find($roleId);

        return [
            'role' => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'description' => $role->description,
                'is_super_admin' => $role->isSuperAdmin(),
            ],
            'permissions' => $this->permissions->permissionsForRole($role->id),
        ];
    }

    /**
     * @param  array{code: string, name: string, description: string|null}  $data
     */
    public function create(array $data): AdminRole
    {
        // SUPER_ADMIN 은 권한 검사를 통째로 우회하는 특수 코드다.
        // 화면에서 임의로 만들 수 있으면 권한 체계가 무의미해진다.
        if (strtoupper($data['code']) === AdminRole::SUPER_ADMIN) {
            throw new AdminPolicyException(
                'SUPER_ADMIN 코드는 화면에서 생성할 수 없습니다.',
                'code',
            );
        }

        return AdminRole::query()->create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * 이름과 설명만 수정한다. code 는 권한 레코드가 참조하므로 바꾸지 않는다 (CLAUDE.md §7.3).
     *
     * @param  array{name: string, description: string|null}  $data
     */
    public function update(int $roleId, array $data): AdminRole
    {
        $role = $this->find($roleId);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $role;
    }

    /**
     * 역할별 메뉴 권한 저장.
     *
     * @param  array<string, array{can_read: bool, can_write: bool}>  $permissions
     */
    public function updatePermissions(int $roleId, array $permissions, Admin $actor): void
    {
        $role = $this->find($roleId);

        // 최고관리자 역할은 레코드와 무관하게 전부 통과하므로 편집이 의미가 없다.
        if ($role->isSuperAdmin()) {
            throw new AdminPolicyException('최고관리자 역할의 권한은 편집할 수 없습니다.');
        }

        // 본인이 속한 역할의 권한을 본인이 바꾸면 권한 상승이 가능해진다 (CLAUDE.md §7.6).
        if ($actor->admin_role_id === $role->id) {
            throw new AdminPolicyException('본인이 속한 역할의 권한은 수정할 수 없습니다.');
        }

        $this->permissions->syncRolePermissions($role->id, $permissions);
    }

    public function delete(int $roleId, Admin $actor): void
    {
        $role = AdminRole::query()->withCount('admins')->findOrFail($roleId);

        if ($role->isSuperAdmin()) {
            throw new AdminPolicyException('최고관리자 역할은 삭제할 수 없습니다.');
        }

        if ($actor->admin_role_id === $role->id) {
            throw new AdminPolicyException('본인이 속한 역할은 삭제할 수 없습니다.');
        }

        // 소속 관리자가 있으면 삭제 불가 (CLAUDE.md §7.6). DB 도 restrictOnDelete 로 막혀 있다.
        if ($role->admins_count > 0) {
            throw new AdminPolicyException(
                "이 역할에 소속된 관리자가 {$role->admins_count}명 있습니다. 먼저 다른 역할로 옮기세요.",
            );
        }

        $role->delete();
    }
}
