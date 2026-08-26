<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use App\Enums\Admin\AdminStatus;
use App\Exceptions\AdminPolicyException;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Support\LocalTime;
use Illuminate\Support\Collection;

/**
 * 관리자 계정 관리 (CLAUDE.md §7.6 (2), (3)).
 *
 * 안전장치가 여기 모여 있다. 컨트롤러에서 우회하지 않는다.
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class AdminAccountLibrary
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getList(): Collection
    {
        return Admin::query()
            ->with('role')
            ->orderBy('id')
            ->get()
            ->map(fn (Admin $admin) => [
                'id' => $admin->id,
                'login_id' => $admin->login_id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role_name' => $admin->role?->name,
                'role_id' => $admin->admin_role_id,
                'status' => $admin->status->value,
                'status_label' => $admin->status->label(),
                'is_super_admin' => $admin->isSuperAdmin(),
                'last_login_at' => LocalTime::dateTime($admin->last_login_at),
            ]);
    }

    public function find(int $adminId): Admin
    {
        return Admin::query()->with('role')->findOrFail($adminId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(int $adminId): array
    {
        $admin = $this->find($adminId);

        return [
            'id' => $admin->id,
            'login_id' => $admin->login_id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role_id' => $admin->admin_role_id,
            'status' => $admin->status->value,
            'is_super_admin' => $admin->isSuperAdmin(),
        ];
    }

    /**
     * 역할 선택 목록 (드롭다운용).
     *
     * @return Collection<int, array{id: int, name: string, code: string}>
     */
    public function roleOptions(): Collection
    {
        return AdminRole::query()
            ->orderBy('id')
            ->get()
            ->map(fn (AdminRole $r) => ['id' => $r->id, 'name' => $r->name, 'code' => $r->code]);
    }

    /**
     * @param  array{login_id: string, name: string, email: string|null, password: string, admin_role_id: int, status: string}  $data
     */
    public function create(array $data): Admin
    {
        return Admin::query()->create([
            'login_id' => $data['login_id'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            // 모델 $casts 의 'hashed' 가 해싱한다. 평문이 저장되지 않는다.
            'password' => $data['password'],
            'admin_role_id' => $data['admin_role_id'],
            'status' => AdminStatus::from($data['status']),
        ]);
    }

    /**
     * 이름/이메일/역할/상태 수정. 비밀번호는 별도 메서드로 다룬다.
     *
     * @param  array{name: string, email: string|null, admin_role_id: int, status: string}  $data
     */
    public function update(int $adminId, array $data, Admin $actor): Admin
    {
        $admin = $this->find($adminId);
        $status = AdminStatus::from($data['status']);
        $roleChanged = $admin->admin_role_id !== (int) $data['admin_role_id'];

        // 본인의 역할은 본인이 바꿀 수 없다 — 권한 상승 차단 (CLAUDE.md §7.6).
        if ($admin->id === $actor->id && $roleChanged) {
            throw new AdminPolicyException('본인의 역할은 변경할 수 없습니다.', 'admin_role_id');
        }

        // 본인 계정을 스스로 정지시켜 잠기는 상황을 막는다.
        if ($admin->id === $actor->id && ! $status->canLogin()) {
            throw new AdminPolicyException('본인 계정은 정지할 수 없습니다.', 'status');
        }

        if ($admin->isSuperAdmin()) {
            if ($roleChanged) {
                $this->ensureNotLastSuperAdmin($admin, '마지막 최고관리자는 역할을 변경할 수 없습니다.', 'admin_role_id');
            }

            if (! $status->canLogin()) {
                $this->ensureNotLastSuperAdmin($admin, '마지막 최고관리자는 정지할 수 없습니다.', 'status');
            }
        }

        $admin->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'admin_role_id' => $data['admin_role_id'],
            'status' => $status,
        ]);

        return $admin;
    }

    /**
     * 상위 관리자에 의한 비밀번호 초기화.
     */
    public function resetPassword(int $adminId, string $newPassword): void
    {
        $admin = $this->find($adminId);

        $admin->update(['password' => $newPassword]);
    }

    /**
     * 관리자 "삭제". 물리 삭제 대신 정지시킨다 (CLAUDE.md §7.6).
     * 주문·상품 이력이 관리자를 참조하기 때문이다.
     */
    public function suspend(int $adminId, Admin $actor): void
    {
        $admin = $this->find($adminId);

        if ($admin->id === $actor->id) {
            throw new AdminPolicyException('본인 계정은 정지할 수 없습니다.');
        }

        if ($admin->isSuperAdmin()) {
            $this->ensureNotLastSuperAdmin($admin, '마지막 최고관리자는 정지할 수 없습니다.');
        }

        $admin->update(['status' => AdminStatus::SUSPENDED]);
    }

    /**
     * 활성 상태인 최고관리자가 이 계정 말고 또 있는지 확인한다.
     * 없으면 잠기므로 막는다 — 복구 수단이 DB 직접 수정밖에 없다.
     */
    private function ensureNotLastSuperAdmin(Admin $admin, string $message, string $field = 'general'): void
    {
        $others = Admin::query()
            ->where('id', '!=', $admin->id)
            ->where('admin_role_id', $admin->admin_role_id)
            ->where('status', AdminStatus::ACTIVE->value)
            ->count();

        if ($others === 0) {
            throw new AdminPolicyException($message, $field);
        }
    }
}
