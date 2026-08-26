<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    accounts: { type: Array, required: true },
});

const errors = computed(() => usePage().props.errors ?? {});
const editingId = ref(null);

const blank = () => ({
    bank_name: '',
    account_number: '',
    holder_name: '',
    is_default: false,
    is_active: true,
    sort_order: 0,
});

const createForm = useForm(blank());
const editForm = useForm(blank());

const startEdit = (account) => {
    editingId.value = account.id;
    editForm.defaults({ ...account });
    editForm.reset();
    editForm.clearErrors();
};

const submitCreate = () => createForm.post('/admin/settings/bank', {
    onSuccess: () => createForm.reset(),
});

const submitEdit = () => editForm.put(`/admin/settings/bank/${editingId.value}`, {
    onSuccess: () => { editingId.value = null; },
});

const remove = (account) => {
    if (!confirm(`'${account.bank_name} ${account.account_number}' 계좌를 삭제할까요?`)) {
        return;
    }

    router.delete(`/admin/settings/bank/${account.id}`, { preserveScroll: true });
};

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="입금계좌설정">
        <h2 class="text-xl font-semibold tracking-tight">입금 계좌</h2>
        <p class="mt-1 text-sm text-neutral-500">
            기본 계좌가 무통장입금 주문에 안내됩니다.
        </p>

        <p
            v-if="errors.general"
            class="mt-4 max-w-3xl rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
        >
            {{ errors.general }}
        </p>

        <p
            v-if="accounts.length === 0"
            class="mt-4 max-w-3xl rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-sm text-amber-200"
        >
            계좌가 없으면 고객이 무통장입금으로 주문할 수 없습니다.
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full max-w-4xl text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">은행</th>
                        <th class="py-2 font-medium">계좌번호</th>
                        <th class="py-2 font-medium">예금주</th>
                        <th class="py-2 text-center font-medium">기본</th>
                        <th class="py-2 text-center font-medium">사용</th>
                        <th class="w-28 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="a in accounts" :key="a.id">
                        <tr class="border-b border-neutral-900">
                            <td class="py-3">{{ a.bank_name }}</td>
                            <td class="py-3 font-mono text-xs">{{ a.account_number }}</td>
                            <td class="py-3">{{ a.holder_name }}</td>
                            <td class="py-3 text-center">
                                <span
                                    v-if="a.is_default"
                                    class="rounded bg-amber-500/15 px-1.5 py-0.5 text-xs text-amber-300"
                                >기본</span>
                            </td>
                            <td class="py-3 text-center">
                                <span
                                    class="rounded px-1.5 py-0.5 text-xs"
                                    :class="a.is_active
                                        ? 'bg-emerald-500/15 text-emerald-300'
                                        : 'bg-neutral-700/40 text-neutral-400'"
                                >
                                    {{ a.is_active ? '사용' : '중지' }}
                                </span>
                            </td>
                            <td class="py-3 text-right">
                                <button type="button" class="text-neutral-400 hover:text-neutral-100" @click="startEdit(a)">
                                    수정
                                </button>
                                <button
                                    v-if="!a.is_default"
                                    type="button"
                                    class="ml-3 text-red-400 hover:text-red-300"
                                    @click="remove(a)"
                                >
                                    삭제
                                </button>
                            </td>
                        </tr>

                        <tr v-if="editingId === a.id" class="border-b border-neutral-900 bg-neutral-900">
                            <td colspan="6" class="p-4">
                                <form class="space-y-4" @submit.prevent="submitEdit">
                                    <div class="grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <label class="block text-sm text-neutral-400">은행</label>
                                            <input v-model="editForm.bank_name" type="text" :class="inputClass">
                                            <p v-if="editForm.errors.bank_name" class="mt-1 text-xs text-red-400">
                                                {{ editForm.errors.bank_name }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm text-neutral-400">계좌번호</label>
                                            <input v-model="editForm.account_number" type="text" :class="inputClass">
                                            <p v-if="editForm.errors.account_number" class="mt-1 text-xs text-red-400">
                                                {{ editForm.errors.account_number }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm text-neutral-400">예금주</label>
                                            <input v-model="editForm.holder_name" type="text" :class="inputClass">
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-6">
                                        <label class="flex items-center gap-2 text-sm text-neutral-400">
                                            <input v-model="editForm.is_default" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                                            기본 계좌
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
                                            @click="editingId = null"
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
            <p class="text-sm font-medium">계좌 추가</p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm text-neutral-400">은행</label>
                    <input v-model="createForm.bank_name" type="text" placeholder="국민은행" :class="inputClass">
                    <p v-if="createForm.errors.bank_name" class="mt-1 text-xs text-red-400">
                        {{ createForm.errors.bank_name }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-400">계좌번호</label>
                    <input v-model="createForm.account_number" type="text" placeholder="123456-01-123456" :class="inputClass">
                    <p v-if="createForm.errors.account_number" class="mt-1 text-xs text-red-400">
                        {{ createForm.errors.account_number }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-400">예금주</label>
                    <input v-model="createForm.holder_name" type="text" :class="inputClass">
                    <p v-if="createForm.errors.holder_name" class="mt-1 text-xs text-red-400">
                        {{ createForm.errors.holder_name }}
                    </p>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-neutral-400">
                <input v-model="createForm.is_default" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                기본 계좌로 지정
            </label>

            <button
                type="submit"
                :disabled="createForm.processing"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                추가
            </button>
        </form>

        <p class="mt-4 max-w-4xl text-xs text-neutral-600">
            계좌 정보는 주문 시점에 결제 건으로 복사됩니다.
            나중에 계좌를 바꿔도 이미 안내된 주문의 계좌는 변하지 않습니다.
        </p>
    </AdminLayout>
</template>
