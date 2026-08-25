<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Product\ProductStatus;
use App\Enums\Product\ShippingFeeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable', 'string', 'max:220',
                Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            // 금액은 원 단위 정수 (CLAUDE.md §5.3).
            'base_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],

            'status' => ['required', Rule::enum(ProductStatus::class)],
            'shipping_fee_type' => ['required', Rule::enum(ShippingFeeType::class)],
            'shipping_policy_id' => ['nullable', 'integer', Rule::exists('shipping_policies', 'id')],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],

            'options' => ['array', 'max:3'],
            'options.*.name' => ['required', 'string', 'max:30'],
            'options.*.values' => ['required', 'array', 'min:1'],
            'options.*.values.*' => ['required', 'string', 'max:50'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.values' => ['present', 'array'],
            'variants.*.values.*' => ['required', 'string', 'max:50'],
            'variants.*.sku' => ['nullable', 'string', 'max:50'],
            'variants.*.additional_price' => ['required', 'integer', 'min:-1000000000', 'max:1000000000'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $base = (int) $this->input('base_price');
                $sale = $this->input('sale_price');

                // 할인가가 정가보다 크면 노출가 계산이 뒤집힌다.
                if ($sale !== null && $sale !== '' && (int) $sale > $base) {
                    $validator->errors()->add('sale_price', '할인가는 정가보다 클 수 없습니다.');
                }

                // 배송비 부과 상품인데 정책이 없으면 기본 정책으로 계산된다.
                // 의도한 것일 수 있으므로 막지는 않되, SKU 중복은 막는다.
                $skus = array_filter(array_column((array) $this->input('variants', []), 'sku'));

                if (count($skus) !== count(array_unique($skus))) {
                    $validator->errors()->add('variants', 'SKU 가 중복됩니다.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => '카테고리',
            'name' => '상품명',
            'slug' => 'URL 주소',
            'base_price' => '정가',
            'sale_price' => '할인가',
            'status' => '상태',
            'shipping_fee_type' => '배송비 유형',
            'shipping_policy_id' => '배송비 정책',
            'sort_order' => '정렬 순서',
        ];
    }
}
