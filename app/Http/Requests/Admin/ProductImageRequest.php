<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductImageRequest extends FormRequest
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
        // 허용 확장자·용량은 config/shop.php 가 원천이다. 여기에 숫자를 박지 않는다 (CLAUDE.md §6.2).
        $allowed = implode(',', config('shop.image.allowed'));
        $maxKb = (int) config('shop.image.max_kb');

        return [
            'images' => ['required', 'array', 'min:1'],
            // 'image' 규칙은 실제 이미지인지까지 본다 — 확장자만 바꾼 파일을 걸러낸다.
            'images.*' => ['required', 'file', 'image', "mimes:{$allowed}", "max:{$maxKb}"],
            // 용도. 값이 없으면 컨트롤러가 갤러리로 본다.
            'type' => ['nullable', 'string', 'in:GALLERY,DETAIL'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $allowed = implode(', ', config('shop.image.allowed'));
        $maxKb = (int) config('shop.image.max_kb');

        return [
            'images.*.mimes' => "이미지는 {$allowed} 형식만 올릴 수 있습니다.",
            'images.*.max' => '이미지 한 장은 '.number_format($maxKb).'KB 를 넘을 수 없습니다.',
            'images.*.image' => '이미지 파일이 아닙니다.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['images' => '이미지', 'images.*' => '이미지'];
    }
}
