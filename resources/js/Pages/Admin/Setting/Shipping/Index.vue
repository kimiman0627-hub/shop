<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    policies: { type: Array, required: true },
});

const errors = computed(() => usePage().props.errors ?? {});
const editingId = ref(null);

const createForm = useForm({
    name: '',
    base_fee: 3000,
    free_threshold: 50000,
    is_default: false,
    is_active: true,
});

const editForm = useForm({
    name: '',
    base_fee: 0,
    free_threshold: null,
    is_default: false,
    is_active: true,
});

const startEdit = (policy) => {
    editingId.value = policy.id;
    editForm.defaults({ ...policy });
    editForm.reset();
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
};

const submitCreate = () => createForm.post('/admin/settings/shipping', {
    onSuccess: () => createForm.reset(),
});

const submitEdit = () => editForm.put(`/admin/settings/shipping/${editingId.value}`, {
    onSuccess: () => { editingId.value = null; },
});

const remove = (policy) => {
    if (!confirm(`'${policy.name}' 정책을 삭제할까요?`)) {
        return;
    }

    router.delete(`/admin/settings/shipping/${policy.id}`);
};

const won = (n) => (n === null || n === undefined ? '-' : `${Number(n).toLocaleString('ko-KR')}원`);

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="배송비설정">
        <h2 class="text-xl font-semibold tracking-tight">배송비 정책</h2>
        <p class="mt-1 text-sm text-neutral-500">
            상품마다 이 정책 중 하나를 고릅니다. 정책을 고르지 않은 상품은 기본 정책을 씁니다.
        </p>

        <p
            v-if="errors.general"
            class="mt-4 max-w-3xl rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
        >
            {{ errors.general }}
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full max-w-4xl text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">정책명</th>
                        <th class="py-2 font-medium">기본 배송비</th>
                        <th class="py-2 font-medium">무료배송 기준</th>
                        <th class="py-2 text-center font-medium">기본</th>
                        <th class="py-2 text-center font-medium">사용</th>
                        <th class="w-28 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="policy in policies" :key="policy.id">
                        <tr class="border-b border-neutral-900">
                            <td class="py-3">{{ policy.name }}</td>
                            <td class="py-3">{{ won(policy.base_fee) }}</td>
                            <td class="py-3 text-neutral-400">
                                <span v-if="policy.free_threshold">{{ won(policy.free_threshold) }} 이상 무료</span>
                                <span v-else class="text-neutral-600">조건부 무료 없음</span>
                            </td>
                            <td class="py-3 text-center">
                                <span
                                    v-if="policy.is_default"
                                    class="rounded bg-amber-500/15 px-1.5 py-0.5 text-xs text-amber-300"
                                >기본</span>
                            </td>
                            <td class="py-3 text-center">
                                <span
                                    class="rounded px-1.5 py-0.5 text-xs"
                                    :class="policy.is_active
                                        ? 'bg-emerald-500/15 text-emerald-300'
                                        : 'bg-neutral-700/40 text-neutral-400'"
                                >
                                    {{ policy.is_active ? '사용' : '중지' }}
                                </span>
                            </td>
                            <td class="py-3 text-right">
                                <button type="button" class="text-neutral-400 hover:text-neutral-100" @click="startEdit(policy)">
                                    수정
                                </button>
                                <button
                                    v-if="!policy.is_default"
                                    type="button"
                                    class="ml-3 text-red-400 hover:text-red-300"
                                    @click="remove(policy)"
                                >
                                    삭제
                                </button>
                            </td>
                        </tr>

                        <tr v-if="editingId === policy.id" class="border-b border-neutral-900 bg-neutral-900">
                            <td colspan="6" class="p-4">
                                <form class="space-y-4" @submit.prevent="submitEdit">
                                    <div class="grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <label class="block text-sm text-neutral-400">정책명</label>
                                            <input v-model="editForm.name" type="text" :class="inputClass">
                                            <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-400">
                                                {{ editForm.errors.name }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm text-neutral-400">기본 배송비 (원)</label>
                                            <input v-model.number="editForm.base_fee" type="number" min="0" :class="inputClass">
                                            <p v-if="editForm.errors.base_fee" class="mt-1 text-xs text-red-400">
                                                {{ editForm.errors.base_fee }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm text-neutral-400">무료배송 기준 (원, 비우면 없음)</label>
                                            <input v-model.number="editForm.free_threshold" type="number" min="1" :class="inputClass">
                                            <p v-if="editForm.errors.free_threshold" class="mt-1 text-xs text-red-400">
                                                {{ editForm.errors.free_threshold }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-6">
                                        <label class="flex items-center gap-2 text-sm text-neutral-400">
                                            <input v-model="editForm.is_default" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                                            기본 정책
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-neutral-400">
                                            <input v-model="editForm.is_active" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                                            사용
                                        </label>
                                    </div>

                                    <p v-if="editForm.errors.is_default" class="text-xs text-red-400">
                                        {{ editForm.errors.is_default }}
                                    </p>
                                    <p v-if="editForm.errors.is_active" class="text-xs text-red-400">
                                        {{ editForm.errors.is_active }}
                                    </p>

                                    <div class="flex gap-2">
                                        <button
                                            type="submit"
                                            :disabled="editForm.processing"
                                            class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
                                        >
                                            저장
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-neutral-700 px-3 py-2 text-sm text-neutral-300"
                                            @click="cancelEdit"
                                        >
                                            취소
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <form class="mt-8 max-w-4xl space-y-4 rounded-lg border border-neutral-800 p-4" @submit.prevent="submitCreate">
            <p class="text-sm font-medium">정책 추가</p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm text-neutral-400">정책명</label>
                    <input v-model="createForm.name" type="text" placeholder="기본 배송비" :class="inputClass">
                    <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-400">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-400">기본 배송비 (원)</label>
                    <input v-model.number="createForm.base_fee" type="number" min="0" :class="inputClass">
                    <p v-if="createForm.errors.base_fee" class="mt-1 text-xs text-red-400">
                        {{ createForm.errors.base_fee }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-400">무료배송 기준 (원, 비우면 없음)</label>
                    <input v-model.number="createForm.free_threshold" type="number" min="1" :class="inputClass">
                    <p v-if="createForm.errors.free_threshold" class="mt-1 text-xs text-red-400">
                        {{ createForm.errors.free_threshold }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-neutral-400">
                    <input v-model="createForm.is_default" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                    기본 정책으로 지정
                </label>
                <label class="flex items-center gap-2 text-sm text-neutral-400">
                    <input v-model="createForm.is_active" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                    사용
                </label>
            </div>

            <button
                type="submit"
                :disabled="createForm.processing"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                추가
            </button>
        </form>

        <div class="mt-6 max-w-4xl rounded-lg border border-neutral-800 p-4 text-xs text-neutral-500">
            <p class="mb-2 font-medium text-neutral-400">배송비 계산 규칙</p>
            <ol class="list-inside list-decimal space-y-1">
                <li>주문 상품 중 <span class="text-neutral-300">배송비 부과</span> 상품만 모읍니다.</li>
                <li>하나도 없으면 배송비 0원입니다.</li>
                <li>정책이 섞이면 <span class="text-neutral-300">기본 배송비가 가장 비싼 정책</span>을 적용합니다.</li>
                <li>
                    <span class="text-neutral-300">배송비 부과 상품의 합계</span>가 무료배송 기준 이상이면 0원입니다.
                    주문 총액이 아니라 유료배송 상품 합계 기준입니다.
                </li>
            </ol>
            <p class="mt-3">
                기본 정책은 항상 1개이며 삭제·비활성화·해제할 수 없습니다.
                옮기려면 다른 정책을 기본으로 지정하세요.
            </p>
        </div>
    </AdminLayout>
</template>
