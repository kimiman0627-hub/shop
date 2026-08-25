<?php

declare(strict_types=1);

namespace App\Libraries\Admin;

use Illuminate\Support\Facades\Route;

/**
 * config/admin/menu.php 를 읽어 메뉴 트리를 다룬다 (CLAUDE.md §7.3).
 *
 * 메뉴 정의의 원천은 config 파일이고, DB 는 "어떤 역할이 어떤 menu_code 에
 * 접근 가능한가" 만 저장한다.
 *
 * Request / Session / Auth 에 의존하지 않는다 (CLAUDE.md §4.2).
 */
class AdminMenuLibrary
{
    /**
     * 전체 메뉴 트리 원본.
     *
     * @return array<string, array{name: string, children: array<string, array{name: string, route: string}>}>
     */
    public function tree(): array
    {
        return config('admin.menu', []);
    }

    /**
     * 존재하는 모든 하위 메뉴 코드 (권한 편집 화면에서 쓴다).
     *
     * @return list<string>
     */
    public function allMenuCodes(): array
    {
        $codes = [];

        foreach ($this->tree() as $group) {
            foreach (array_keys($group['children'] ?? []) as $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * 주어진 코드가 실제 정의된 메뉴인지.
     */
    public function exists(string $menuCode): bool
    {
        return in_array($menuCode, $this->allMenuCodes(), true);
    }

    /**
     * 조회 권한이 있는 항목만 남긴 메뉴 트리. 사이드바 렌더링용이다.
     *
     * 화면에서 숨기는 것은 UI 편의일 뿐이고, 차단의 근거는 서버 미들웨어다
     * (CLAUDE.md §7.5). 둘 다 있어야 한다.
     *
     * @param  array<string, array{can_read: bool, can_write: bool}>  $permissions
     * @return list<array{name: string, children: list<array{code: string, name: string, url: string|null}>}>
     */
    public function visibleTree(array $permissions, bool $isSuperAdmin = false): array
    {
        $visible = [];

        foreach ($this->tree() as $group) {
            $children = [];

            foreach ($group['children'] ?? [] as $code => $child) {
                if (! $isSuperAdmin && ! ($permissions[$code]['can_read'] ?? false)) {
                    continue;
                }

                $children[] = [
                    'code' => $code,
                    'name' => $child['name'],
                    // 아직 라우트를 만들지 않은 메뉴는 url 이 null 이다.
                    // 프론트는 null 이면 링크가 아니라 비활성 텍스트로 그린다.
                    'url' => Route::has($child['route']) ? route($child['route']) : null,
                ];
            }

            if ($children !== []) {
                $visible[] = ['name' => $group['name'], 'children' => $children];
            }
        }

        return $visible;
    }
}
