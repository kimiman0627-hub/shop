<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { adminInput } from '@/ui';

const props = defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, required: true },
    statusOptions: { type: Array, required: true },
});

const errors = computed(() => usePage().props.errors ?? {});

const search = reactive({
    status: props.filters.status ?? 'READY',
    keyword: props.filters.keyword ?? '',
});

const memo = ref({});

const apply = () => router.get('/admin/payments/deposits', { ...search }, {
    preserveState: true,
    replace: true,
});

const confirm_ = (row) => {
    if (!confirm(
        `입금을 확인하셨습니까?\n\n`
        + `주문번호: ${row.order_no}\n`
        + `입금자명: ${row.depositor_name}\n`
        + `금액: ${won(row.amount)}\n\n`
        + `결제완료로 처리하면 재고가 실제로 차감됩니다.`,
    )) {
        return;
    }

    router.put(`/admin/payments/deposits/${row.id}/confirm`, {
        memo: memo.value[row.id] ?? '',
    }, { preserveScroll: true });
};

const cancel_ = (row) => {
    if (!confirm(`주문 ${row.order_no} 을(를) 취소할까요?\n재고 예약이 해제됩니다.`)) {
        return;
    }

    router.put(`/admin/payments/deposits/${row.id}/cancel`, {
        memo: memo.value[row.id] ?? '입금 미확인 취소',
    }, { preserveScroll: true });
};

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const inputClass = adminInput;
</script>

<template>
    <AdminLayout title="무통장처리">
        <h2 class="text-xl font-semibold tracking-tight">무통장입금</h2>
        <p class="mt-1 text-sm text-neutral-500">
            은행에서 입금을 확인한 뒤 결제완료 처리하세요.
            <span class="text-neutral-400">처리하면 재고가 실제로 차감되고, 누가 처리했는지 기록됩니다.</span>
        </p>

        <p
            v-if="errors.general"
            class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
        >
            {{ errors.general }}
        </p>

        <form class="mt-6 flex flex-wrap gap-2" @submit.prevent="apply">
            <select v-model="search.status" :class="inputClass">
                <option value="">전체 상태</option>
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <input v-model="search.keyword" type="text" placeholder="주문번호 · 입금자명" :class="inputClass">
            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">주문번호</th>
                        <th class="py-2 font-medium">입금자명</th>
                        <th class="py-2 text-right font-medium">입금액</th>
                        <th class="py-2 font-medium">요청일시</th>
                        <th class="py-2 font-medium">입금기한</th>
                        <th class="py-2 text-center font-medium">상태</th>
                        <th class="w-64 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in payments.data" :key="row.id" class="border-b border-neutral-900 align-top">
                        <td class="py-3">
                            <p class="font-mono text-xs">{{ row.order_no }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                {{ row.orderer_name }} · {{ row.orderer_phone }}
                            </p>
                            <p class="mt-0.5 text-xs text-neutral-600">주문: {{ row.order_status_label }}</p>
                        </td>
                        <td class="py-3">{{ row.depositor_name }}</td>
                        <td class="py-3 text-right font-medium">{{ won(row.amount) }}</td>
                        <td class="py-3 text-neutral-400">{{ row.requested_at }}</td>
                        <td class="py-3" :class="row.overdue ? 'text-red-400' : 'text-neutral-400'">
                            {{ row.due_at ?? '-' }}
                            <span v-if="row.overdue" class="block text-xs">기한 초과</span>
                        </td>
                        <td class="py-3 text-center">
                            <span
                                class="rounded px-1.5 py-0.5 text-xs"
                                :class="row.status === 'PAID'
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : row.status === 'READY'
                                        ? 'bg-amber-500/15 text-amber-300'
                                        : 'bg-neutral-700/40 text-neutral-400'"
                            >
                                {{ row.status_label }}
                            </span>
                            <p v-if="row.confirmed_by" class="mt-1 text-xs text-neutral-600">
                                {{ row.confirmed_by }}
                            </p>
                            <p v-if="row.paid_at" class="text-xs text-neutral-600">{{ row.paid_at }}</p>
                        </td>
                        <td class="py-3">
                            <template v-if="row.status === 'READY'">
                                <input
                                    v-model="memo[row.id]"
                                    type="text"
                                    placeholder="메모 (선택)"
                                    class="w-full rounded border border-neutral-700 bg-neutral-950 px-2 py-1 text-xs outline-none focus:border-neutral-400"
                                >
                                <div class="mt-2 flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-500"
                                        @click="confirm_(row)"
                                    >
                                        결제완료
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border border-red-500/40 px-3 py-1 text-xs text-red-400 hover:bg-red-500/10"
                                        @click="cancel_(row)"
                                    >
                                        주문취소
                                    </button>
                                </div>
                            </template>
                            <p v-else-if="row.memo" class="text-xs text-neutral-500">{{ row.memo }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="payments.data.length === 0" class="mt-6 text-sm text-neutral-500">
            해당하는 건이 없습니다.
        </p>

        <Pagination :paginator="payments" theme="dark" />
    </AdminLayout>
</template>
