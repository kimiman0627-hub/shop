<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductImageManager from '@/Components/ProductImageManager.vue';

const props = defineProps({
    product: { type: Object, default: null },
    categoryOptions: { type: Array, required: true },
    shippingOptions: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
    feeTypeOptions: { type: Array, required: true },
});

const isEdit = computed(() => props.product !== null);

// 옵션 그룹. 값은 화면에서 쉼표로 입력받고 배열로 다룬다.
const options = reactive(
    (props.product?.options ?? []).map((o) => ({ name: o.name, valueText: o.values.join(', ') })),
);

// 조합 데이터는 signature 로 보관한다. 옵션을 고쳐도 이미 입력한 재고를 잃지 않게 하려는 것이다.
const variantStore = reactive({});

const SINGLE = '__single__';

const signatureOf = (labels) => (labels.length === 0 ? SINGLE : labels.join(' / '));

for (const v of props.product?.variants ?? []) {
    variantStore[signatureOf(v.values)] = {
        sku: v.sku ?? '',
        additional_price: v.additional_price ?? 0,
        stock_quantity: v.stock_quantity ?? 0,
        reserved_quantity: v.reserved_quantity ?? 0,
        is_active: v.is_active ?? true,
    };
}

const parseValues = (text) => text
    .split(',')
    .map((s) => s.trim())
    .filter((s) => s !== '');

/** 옵션 값들의 곱집합. 네이버 조합형과 같은 방식이다. */
const combinations = computed(() => {
    const groups = options
        .map((o) => parseValues(o.valueText))
        .filter((values) => values.length > 0);

    if (groups.length === 0) {
        return [[]];
    }

    return groups.reduce(
        (acc, values) => acc.flatMap((prefix) => values.map((v) => [...prefix, v])),
        [[]],
    );
});

const rowFor = (labels) => {
    const key = signatureOf(labels);

    if (!variantStore[key]) {
        variantStore[key] = {
            sku: '',
            additional_price: 0,
            stock_quantity: 0,
            reserved_quantity: 0,
            is_active: true,
        };
    }

    return variantStore[key];
};

const addOption = () => {
    if (options.length >= 3) {
        return;
    }
    options.push({ name: '', valueText: '' });
};

const removeOption = (index) => options.splice(index, 1);

const form = useForm({
    category_id: props.product?.category_id ?? null,
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    summary: props.product?.summary ?? '',
    description: props.product?.description ?? '',
    base_price: props.product?.base_price ?? 0,
    sale_price: props.product?.sale_price ?? null,
    status: props.product?.status ?? 'DRAFT',
    shipping_fee_type: props.product?.shipping_fee_type ?? 'PAID',
    shipping_policy_id: props.product?.shipping_policy_id ?? null,
    sort_order: props.product?.sort_order ?? 0,
    options: [],
    variants: [],
});

const isPaidShipping = computed(() => form.shipping_fee_type === 'PAID');

// 무료배송으로 바꾸면 정책 선택은 의미가 없다.
watch(isPaidShipping, (paid) => {
    if (!paid) {
        form.shipping_policy_id = null;
    }
});

const buildPayload = () => {
    form.options = options
        .filter((o) => o.name.trim() !== '' && parseValues(o.valueText).length > 0)
        .map((o) => ({ name: o.name.trim(), values: parseValues(o.valueText) }));

    form.variants = combinations.value.map((labels) => {
        const row = rowFor(labels);

        return {
            values: labels,
            sku: row.sku,
            additional_price: Number(row.additional_price) || 0,
            stock_quantity: Number(row.stock_quantity) || 0,
            is_active: row.is_active,
        };
    });
};

const submit = () => {
    buildPayload();

    if (isEdit.value) {
        form.put(`/admin/products/${props.product.id}`);
        return;
    }

    form.post('/admin/products');
};

const remove = () => {
    if (!confirm(`'${props.product.name}' 상품을 삭제할까요?`)) {
        return;
    }

    router.delete(`/admin/products/${props.product.id}`);
};

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const finalPrice = (labels) => {
    const base = Number(form.sale_price ?? form.base_price) || 0;
    return base + (Number(rowFor(labels).additional_price) || 0);
};

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
const cellClass = 'w-full rounded border border-neutral-700 bg-neutral-950 px-2 py-1 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout :title="isEdit ? `상품 수정 · ${product.name}` : '상품 등록'">
        <Link href="/admin/products" class="text-sm text-neutral-500 hover:text-neutral-300">
            &larr; 상품 목록
        </Link>

        <form class="mt-4 space-y-6" @submit.prevent="submit">
            <!-- 기본 정보 -->
            <section class="max-w-3xl space-y-4 rounded-lg border border-neutral-800 p-4">
                <p class="text-sm font-medium">기본 정보</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm text-neutral-400">카테고리</label>
                        <select v-model="form.category_id" :class="inputClass">
                            <option :value="null">선택하세요</option>
                            <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
                        </select>
                        <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-400">
                            {{ form.errors.category_id }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-400">상태</label>
                        <select v-model="form.status" :class="inputClass">
                            <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">상품명</label>
                    <input v-model="form.name" type="text" :class="inputClass">
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">URL 주소</label>
                    <input v-model="form.slug" type="text" placeholder="비워두면 상품명으로 자동 생성" :class="inputClass">
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-400">{{ form.errors.slug }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">요약</label>
                    <input v-model="form.summary" type="text" :class="inputClass">
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">상세 설명</label>
                    <textarea v-model="form.description" rows="5" :class="inputClass" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm text-neutral-400">정가 (원)</label>
                        <input v-model.number="form.base_price" type="number" min="0" :class="inputClass">
                        <p v-if="form.errors.base_price" class="mt-1 text-xs text-red-400">
                            {{ form.errors.base_price }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-400">할인가 (비우면 없음)</label>
                        <input v-model.number="form.sale_price" type="number" min="0" :class="inputClass">
                        <p v-if="form.errors.sale_price" class="mt-1 text-xs text-red-400">
                            {{ form.errors.sale_price }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-400">정렬 순서</label>
                        <input v-model.number="form.sort_order" type="number" min="0" :class="inputClass">
                    </div>
                </div>
            </section>

            <!-- 배송 -->
            <section class="max-w-3xl space-y-4 rounded-lg border border-neutral-800 p-4">
                <p class="text-sm font-medium">배송</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm text-neutral-400">배송비 유형</label>
                        <select v-model="form.shipping_fee_type" :class="inputClass">
                            <option v-for="t in feeTypeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-400">배송비 정책</label>
                        <select v-model="form.shipping_policy_id" :disabled="!isPaidShipping" :class="inputClass">
                            <option :value="null">기본 정책 사용</option>
                            <option v-for="s in shippingOptions" :key="s.id" :value="s.id">
                                {{ s.name }}{{ s.is_default ? ' (기본)' : '' }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-neutral-600">
                            {{ isPaidShipping ? '비우면 기본 정책이 적용됩니다.' : '무료배송 상품은 정책을 쓰지 않습니다.' }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- 이미지: 상품이 있어야 올릴 수 있다. 등록 전에는 고아 파일이 생긴다. -->
            <ProductImageManager
                v-if="isEdit"
                :product-id="product.id"
                :images="product.images ?? []"
                type="GALLERY"
                title="대표·갤러리 이미지"
                hint="상품 상단에 보이는 이미지입니다. 첫 장이 목록 썸네일이 됩니다."
            />

            <ProductImageManager
                v-if="isEdit"
                :product-id="product.id"
                :images="product.detail_images ?? []"
                type="DETAIL"
                title="상세 이미지"
                hint="'상세정보' 탭에 세로로 이어 붙는 이미지입니다. 긴 상세페이지 이미지를 나눠 올리세요."
            />

            <p v-else class="max-w-3xl rounded-lg border border-neutral-800 px-4 py-3 text-sm text-neutral-500">
                이미지는 상품을 등록한 뒤 추가할 수 있습니다.
            </p>

            <!-- 옵션 -->
            <section class="max-w-3xl space-y-4 rounded-lg border border-neutral-800 p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium">옵션</p>
                    <button
                        type="button"
                        :disabled="options.length >= 3"
                        class="rounded-lg border border-neutral-700 px-3 py-1.5 text-sm text-neutral-300 disabled:opacity-40"
                        @click="addOption"
                    >
                        옵션 추가
                    </button>
                </div>

                <p v-if="options.length === 0" class="text-sm text-neutral-500">
                    옵션이 없으면 단일 상품으로 등록됩니다.
                </p>

                <div v-for="(option, index) in options" :key="index" class="flex gap-3">
                    <div class="w-40">
                        <label class="block text-xs text-neutral-500">{{ index + 1 }}단계 옵션명</label>
                        <input v-model="option.name" type="text" placeholder="색상" :class="inputClass">
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs text-neutral-500">값 (쉼표로 구분)</label>
                        <input v-model="option.valueText" type="text" placeholder="빨강, 파랑, 검정" :class="inputClass">
                    </div>
                    <button
                        type="button"
                        class="self-end pb-2 text-sm text-red-400 hover:text-red-300"
                        @click="removeOption(index)"
                    >
                        제거
                    </button>
                </div>

                <p v-if="form.errors.options" class="text-xs text-red-400">{{ form.errors.options }}</p>
            </section>

            <!-- 조합 -->
            <section class="space-y-3 rounded-lg border border-neutral-800 p-4">
                <div class="flex items-baseline justify-between">
                    <p class="text-sm font-medium">
                        조합 <span class="text-neutral-500">({{ combinations.length }}개)</span>
                    </p>
                    <p class="text-xs text-neutral-600">
                        재고와 판매가능은 조합 단위입니다. 예약 수량은 시스템이 관리합니다.
                    </p>
                </div>

                <p v-if="form.errors.variants" class="text-xs text-red-400">{{ form.errors.variants }}</p>

                <table class="w-full text-sm">
                    <thead class="border-b border-neutral-800 text-left text-neutral-500">
                        <tr>
                            <th class="py-2 font-medium">조합</th>
                            <th class="w-40 py-2 font-medium">SKU</th>
                            <th class="w-28 py-2 font-medium">추가금액</th>
                            <th class="w-28 py-2 font-medium">최종가</th>
                            <th class="w-24 py-2 font-medium">재고</th>
                            <th class="w-20 py-2 text-center font-medium">예약</th>
                            <th class="w-16 py-2 text-center font-medium">판매</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="labels in combinations" :key="signatureOf(labels)" class="border-b border-neutral-900">
                            <td class="py-2 pr-3">
                                <span v-if="labels.length">{{ labels.join(' / ') }}</span>
                                <span v-else class="text-neutral-500">단일 상품</span>
                            </td>
                            <td class="py-2 pr-2">
                                <input v-model="rowFor(labels).sku" type="text" placeholder="자동 생성" :class="cellClass">
                            </td>
                            <td class="py-2 pr-2">
                                <input v-model.number="rowFor(labels).additional_price" type="number" :class="cellClass">
                            </td>
                            <td class="py-2 pr-2 text-neutral-400">{{ won(finalPrice(labels)) }}</td>
                            <td class="py-2 pr-2">
                                <input v-model.number="rowFor(labels).stock_quantity" type="number" min="0" :class="cellClass">
                            </td>
                            <td class="py-2 text-center" :class="rowFor(labels).reserved_quantity > 0 ? 'text-amber-300' : 'text-neutral-600'">
                                {{ rowFor(labels).reserved_quantity }}
                            </td>
                            <td class="py-2 text-center">
                                <input v-model="rowFor(labels).is_active" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div class="flex gap-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-neutral-100 px-4 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
                >
                    {{ isEdit ? '저장' : '등록' }}
                </button>

                <button
                    v-if="isEdit"
                    type="button"
                    class="rounded-lg border border-red-500/40 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10"
                    @click="remove"
                >
                    삭제
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
