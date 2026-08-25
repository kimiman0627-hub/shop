<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Libraries\Admin\AdminMenuLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RolePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*.can_read' => ['required', 'boolean'],
            'permissions.*.can_write' => ['required', 'boolean'],
        ];
    }

    /**
     * 정의되지 않은 menu_code 가 DB 에 저장되지 않도록 config 기준으로 막는다.
     * 메뉴의 원천은 config/admin/menu.php 다 (CLAUDE.md §7.3).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = app(AdminMenuLibrary::class)->allMenuCodes();

                foreach (array_keys((array) $this->input('permissions', [])) as $code) {
                    if (! in_array($code, $allowed, true)) {
                        $validator->errors()->add('permissions', "정의되지 않은 메뉴 코드입니다: {$code}");
                    }
                }
            },
        ];
    }
}
