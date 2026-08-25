<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:50'],

            // 비워두면 이름에서 자동 생성한다 (CategoryLibrary::resolveSlug).
            'slug' => [
                'nullable', 'string', 'max:80',
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],

            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => '상위 카테고리',
            'name' => '카테고리명',
            'slug' => 'URL 주소',
            'sort_order' => '정렬 순서',
            'is_active' => '노출 여부',
        ];
    }
}
