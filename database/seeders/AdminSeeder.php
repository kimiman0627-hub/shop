<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Admin\AdminStatus;
use App\Libraries\Admin\AdminMenuLibrary;
use App\Libraries\Admin\AdminPermissionLibrary;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

/**
 * 초기 관리자 역할과 최고관리자 계정.
 *
 * 재실행해도 안전하도록 updateOrCreate 를 쓴다.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = AdminRole::query()->updateOrCreate(
            ['code' => AdminRole::SUPER_ADMIN],
            ['name' => '최고관리자', 'description' => '모든 권한. 권한 검사를 전부 통과한다.'],
        );

        $manager = AdminRole::query()->updateOrCreate(
            ['code' => 'MANAGER'],
            ['name' => '운영자', 'description' => '상품·주문 운영 담당.'],
        );

        AdminRole::query()->updateOrCreate(
            ['code' => 'STAFF'],
            ['name' => '스태프', 'description' => '조회 위주 담당.'],
        );

        // 운영자에게 계정·권한 관리를 뺀 나머지 메뉴의 조회/쓰기를 준다.
        //
        // 접두사(SETTING_)로 거르지 않는다. 배송비설정도 SETTING_ 이지만
        // 운영 업무이므로 운영자가 다뤄야 한다. 막을 대상만 명시한다.
        //
        // SETTING_BANK 를 막는 이유: 입금 계좌를 바꾸면 고객 송금이 그 계좌로 간다.
        // 무통장처리(PAYMENT_DEPOSIT)는 운영 업무라 열어두되, 계좌 변경은 최고관리자만.
        $restricted = ['SETTING_ROLE', 'SETTING_ADMIN', 'SETTING_BANK'];

        $menus = new AdminMenuLibrary;
        $permissions = new AdminPermissionLibrary;

        $managerPermissions = [];

        foreach ($menus->allMenuCodes() as $code) {
            $allowed = ! in_array($code, $restricted, true);

            $managerPermissions[$code] = [
                'can_read' => $allowed,
                'can_write' => $allowed,
            ];
        }

        $permissions->syncRolePermissions($manager->id, $managerPermissions);

        // 로컬 개발용 계정. 비밀번호는 운영 전 반드시 교체한다.
        Admin::query()->updateOrCreate(
            ['login_id' => 'superadmin'],
            [
                'name' => '최고관리자',
                'email' => null,
                'password' => 'admin-local-1234',
                'admin_role_id' => $superAdmin->id,
                'status' => AdminStatus::ACTIVE,
            ],
        );

        // 권한 제한이 실제로 걸리는지 확인하려면 최고관리자만으로는 검증이 안 된다.
        Admin::query()->updateOrCreate(
            ['login_id' => 'manager1'],
            [
                'name' => '운영자1',
                'email' => null,
                'password' => 'manager-local-1234',
                'admin_role_id' => $manager->id,
                'status' => AdminStatus::ACTIVE,
            ],
        );
    }
}
