<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    coupon: { type: Object, default: null },
    issued: { type: Array, required: true },
    issueTypeOptions: { type: Array, required: true },
    discountTypeOptions: { type: Array, required: true },
});

const isEdit = computed(() => props.coupon !== null);

const form = useForm({
    code: props.coupon?.code ?? '',
    name: props.coupon?.name ?? '',
    issue_type: props.coupon?.issue_type ?? 'SIGNUP',
    discount_type: props.coupon?.discount_type ?? 'FIXED',
    discount_value: props.coupon?.discount_value ?? 5000,
    max_discount_amount: props.coupon?.max_discount_amount ?? null,
    min_order_amount: props.coupon?.min_order_amount ?? 0,
    valid_days: props.coupon?.valid_days ?? 30,
    valid_from: props.coupon?.valid_from ?? null,
    valid_until: props.coupon?.valid_until ?? null,
    total_issue_limit: props.coupon?.total_issue_limit ?? null,
    per_user_limit: props.coupon?.per_user_limit ?? 1,
    is_active: props.coupon?.is_active ?? true,
});

const needsCode = computed(() => form.issue_type === 'CODE');
const isPercent = computed(() => form.discount_type === 'PERCENT');

const unit = computed(
    () => props.discountTypeOptions.find((o) => o.value === form.discount_type)?.unit ?? '',
);

// 정률인데 상한이 없으면 고가 상품에서 손실이 커진다 (schema-draft.md §8.1).
const percentWithoutCap = computed(
    () => isPercent.value && !form.max_discount_amount,
);

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/coupons/${props.coupon.id}`);
        return;
    }

    form.post('/admin/coupons');
};

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400 disabled:opacity-40';
</script>

<template>
    <AdminLayout :title="isEdit ? `쿠폰 수정 · ${coupon.name}` : '쿠폰 생성'">
        <Link href="/admin/coupons" class="text-sm text-neutral-500 hover:text-neutral-300">
            &larr; 쿠폰 목록
        </Link>

        <form class="mt-4 max-w-3xl space-y-6" @submit.prevent="submit">
            <section class="space-y-4 rounded-lg border border-neutral-800 p-4">
                <p class="text-sm font-medium">기본 정보</p>

                <div>
                    <label class="block text-sm text-neutral-400">쿠폰명</label>
                    <input v-model="form.name" type="text" placeholder="신규가입 5천원 할인" :class="inputClass">
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm text-neutral-400">발급 방식</label>
                        <select v-model="form.issue_type" :class="inputClass">
                            <option v-for="t in issueTypeOptions" :key="t.value" :value="t.value">
                                {{ t.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.issue_type" class="mt-1 text-xs text-red-400">
                            {{ form.errors.issue_type }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-400">쿠폰 코드</label>
                        <input
                            v-model="form.code"
                            type="text"
                            :disabled="!needsCode"
                            placeholder="WELCOME2026"
                            :class="inputClass"
                        >
                        <p v-if="form.errors.code" class="mt-1 text-xs text-red-400">{{ form.errors.code }}</p>
                        <p v-else class="mt-1 text-xs text-neutral-600">
                            {{ needsCode ? '고객이 입력할 코드입니다. 대문자로 저장됩니다.' : '코드 입력형에서만 씁니다.' }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="space-y-4 rounded-lg border border-neutral-800 p-4">
                <p class="text-sm font-medium">할인</p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm text-neutral-400">할인 방식</label>
                        <select v-model="form.discount_type" :class="inputClass">
                            <option v-for="t in discountTypeOptions" :key="t.value" :value="t.value">
                                {{ t.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-400">할인 값 ({{ unit }})</label>
                        <input v-model.number="form.discount_value" type="number" min="1" :class="inputClass">
                        <p v-if="form.errors.discount_value" class="mt-1 text-xs text-red-400">
                            {{ form.errors.discount_value }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-400">최대 할인금액 (원)</label>
                        <input
                            v-model.number="form.max_discount_amount"
                            type="number"
                            min="1"
                            :disabled="!isPercent"
                            :class="inputClass"
                        >
                        <p v-if="form.errors.max_discount_amount" class="mt-1 text-xs text-red-400">
                            {{ form.errors.max_discount_amount }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="percentWithoutCap"
                    class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-sm text-amber-200"
                >
                    정률 쿠폰에 최대 할인금액이 없습니다. 고가 상품에서 할인액이 무한정 커집니다.
                    상한을 정하는 편이 안전합니다.
                </p>

                <div>
                    <label class="block text-sm text-neutral-400">최소 주문금액 (원)</label>
                    <input v-model.number="form.min_order_amount" type="number" min="0" :class="inputClass">
                    <p class="mt-1 text-xs text-neutral-600">
                        상품 합계 기준입니다. 배송비는 할인 대상이 아닙니다.
                    </p>
                </div>
            </section>

            <section class="space-y-4 rounded-lg border border-neutral-800 p-4">
                <p class="text-sm font-medium">기간과 한도</p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm text-neutral-400">유효일수 (발급일 기준)</label>
                        <input v-model.number="form.valid_days" type="number" min="1" :class="inputClass">
                        <p v-if="form.errors.valid_days" class="mt-1 text-xs text-red-400">
                            {{ form.errors.valid_days }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-400">발급 시작일</label>
                        <input v-model="form.valid_from" type="date" :class="inputClass">
                        <p v-if="form.errors.valid_from" class="mt-1 text-xs text-red-400">
                            {{ form.errors.valid_from }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-400">발급 종료일</label>
                        <input v-model="form.valid_until" type="date" :class="inputClass">
                        <p v-if="form.errors.valid_until" class="mt-1 text-xs text-red-400">
                            {{ form.errors.valid_until }}
                        </p>
                    </div>
                </div>

                <p class="text-xs text-neutral-600">
                    유효일수(발급일 기준)와 종료일(절대 기간)은 다른 축입니다.
                    둘 다 있으면 <span class="text-neutral-400">더 이른 쪽</span>이 만료일이 됩니다.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm text-neutral-400">총 발급 한도 (비우면 무제한)</label>
                        <input v-model.number="form.total_issue_limit" type="number" min="1" :class="inputClass">
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-400">1인당 발급 한도</label>
                        <input v-model.number="form.per_user_limit" type="number" min="1" :class="inputClass">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-neutral-400">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                    사용
                </label>
            </section>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-neutral-100 px-4 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                {{ isEdit ? '저장' : '생성' }}
            </button>
        </form>

        <section v-if="isEdit" class="mt-8 max-w-3xl">
            <p class="text-sm font-medium">발급 내역 <span class="text-neutral-500">(최근 100건)</span></p>

            <table v-if="issued.length" class="mt-3 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">회원</th>
                        <th class="py-2 font-medium">발급일</th>
                        <th class="py-2 font-medium">만료일</th>
                        <th class="py-2 font-medium">사용</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in issued" :key="row.id" class="border-b border-neutral-900">
                        <td class="py-2">
                            {{ row.user_name }}
                            <span class="ml-1 text-xs text-neutral-600">{{ row.user_email }}</span>
                        </td>
                        <td class="py-2 text-neutral-400">{{ row.issued_at }}</td>
                        <td class="py-2" :class="row.expired ? 'text-red-400' : 'text-neutral-400'">
                            {{ row.expires_at }}
                        </td>
                        <td class="py-2 text-neutral-400">{{ row.used_at ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-else class="mt-3 text-sm text-neutral-500">아직 발급된 내역이 없습니다.</p>
        </section>
    </AdminLayout>
</template>
