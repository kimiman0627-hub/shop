<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    carrierOptions: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
});

const errors = computed(() => usePage().props.errors ?? {});

const search = reactive({
    status: props.filters.status ?? '',
    keyword: props.filters.keyword ?? '',
});

// 행별 출고 입력값. 목록에서 바로 송장을 찍을 수 있어야 실무에서 쓸 만하다.
const form = ref({});

const rowForm = (row) => {
    if (!form.value[row.id]) {
        form.value[row.id] = {
            carrier: row.carrier ?? props.carrierOptions[0]?.value,
            tracking_no: row.tracking_no ?? '',
            memo: '',
        };
    }

    return form.value[row.id];
};

const apply = () => router.get('/admin/shipments', { ...search }, {
    preserveState: true,
    replace: true,
});

const prepare = (row) => router.put(`/admin/shipments/${row.id}/prepare`, {}, { preserveScroll: true });

const ship = (row) => {
    const f = rowForm(row);

    if (!confirm(
        `출고 처리할까요?\n\n`
        + `주문번호: ${row.order_no}\n`
        + `수령인: ${row.receiver_name}\n`
        + `송장: ${f.tracking_no || '(없음)'}\n\n`
        + `주문이 '배송중'으로 바뀌고 고객에게 송장이 보입니다.`,
    )) {
        return;
    }

    router.put(`/admin/shipments/${row.id}/ship`, { ...f }, { preserveScroll: true });
};

const deliver = (row) => {
    if (!confirm(`주문 ${row.order_no} 을(를) 배송완료로 바꿀까요?`)) {
        return;
    }

    router.put(`/admin/shipments/${row.id}/deliver`, {}, { preserveScroll: true });
};

const revert = (row) => {
    if (!confirm(`송장을 잘못 찍었나요?\n출고를 취소하고 준비중으로 되돌립니다.`)) {
        return;
    }

    router.put(`/admin/shipments/${row.id}/revert`, { memo: '송장 오등록 정정' }, { preserveScroll: true });
};

const inputClass = 'rounded border border-neutral-700 bg-neutral-950 px-2 py-1 text-xs outline-none focus:border-neutral-400';
const filterClass = 'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="배송관리">
        <h2 class="text-xl font-semibold tracking-tight">배송</h2>
        <p class="mt-1 text-sm text-neutral-500">
            결제완료된 주문만 보입니다. 송장을 등록하면 주문이 배송중으로 바뀌고 고객에게 조회 링크가 노출됩니다.
        </p>

        <p
            v-if="errors.general"
            class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
        >
            {{ errors.general }}
        </p>
        <p
            v-if="errors.tracking_no"
            class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
        >
            {{ errors.tracking_no }}
        </p>

        <form class="mt-6 flex flex-wrap gap-2" @submit.prevent="apply">
            <select v-model="search.status" :class="filterClass">
                <option value="">전체 상태</option>
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <input v-model="search.keyword" type="text" placeholder="주문번호 · 수령인 · 송장번호" :class="filterClass">
            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">주문</th>
                        <th class="py-2 font-medium">배송지</th>
                        <th class="py-2 text-center font-medium">상태</th>
                        <th class="w-80 py-2 font-medium">배송 처리</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in orders.data" :key="row.id" class="border-b border-neutral-900 align-top">
                        <td class="py-3 pr-3">
                            <p class="font-mono text-xs">{{ row.order_no }}</p>
                            <p class="mt-0.5">{{ row.item_summary }}</p>
                            <p class="mt-0.5 text-xs text-neutral-600">결제 {{ row.paid_at }}</p>
                        </td>

                        <td class="py-3 pr-3">
                            <p>{{ row.receiver_name }} · {{ row.receiver_phone }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ row.address }}</p>
                            <p v-if="row.delivery_memo" class="mt-1 text-xs text-amber-300">
                                메모: {{ row.delivery_memo }}
                            </p>
                        </td>

                        <td class="py-3 text-center">
                            <span
                                class="rounded px-1.5 py-0.5 text-xs"
                                :class="{
                                    'bg-emerald-500/15 text-emerald-300': row.status === 'DELIVERED',
                                    'bg-sky-500/15 text-sky-300': row.status === 'SHIPPING',
                                    'bg-amber-500/15 text-amber-300': row.status === 'PREPARING',
                                    'bg-neutral-700/40 text-neutral-300': row.status === 'PAID',
                                }"
                            >
                                {{ row.status_label }}
                            </span>
                            <p v-if="row.shipped_by" class="mt-1 text-xs text-neutral-600">{{ row.shipped_by }}</p>
                        </td>

                        <td class="py-3">
                            <!-- 출고 전: 송장 입력 -->
                            <template v-if="row.status === 'PAID' || row.status === 'PREPARING'">
                                <div class="flex gap-1">
                                    <select v-model="rowForm(row).carrier" :class="inputClass">
                                        <option v-for="c in carrierOptions" :key="c.value" :value="c.value">
                                            {{ c.label }}
                                        </option>
                                    </select>
                                    <input
                                        v-model="rowForm(row).tracking_no"
                                        type="text"
                                        placeholder="송장번호"
                                        :class="[inputClass, 'flex-1']"
                                    >
                                </div>

                                <div class="mt-2 flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-sky-600 px-3 py-1 text-xs font-medium text-white hover:bg-sky-500"
                                        @click="ship(row)"
                                    >
                                        출고
                                    </button>
                                    <button
                                        v-if="row.status === 'PAID'"
                                        type="button"
                                        class="rounded border border-neutral-700 px-3 py-1 text-xs text-neutral-300"
                                        @click="prepare(row)"
                                    >
                                        준비중으로
                                    </button>
                                </div>
                            </template>

                            <!-- 배송중: 조회 링크 + 완료/되돌리기 -->
                            <template v-else-if="row.status === 'SHIPPING'">
                                <p class="text-xs">
                                    {{ row.carrier_name }}
                                    <a
                                        v-if="row.tracking_url"
                                        :href="row.tracking_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="ml-1 font-mono text-sky-300 hover:underline"
                                    >{{ row.tracking_no }}</a>
                                    <span v-else class="ml-1 font-mono text-neutral-400">
                                        {{ row.tracking_no ?? '송장 없음' }}
                                    </span>
                                </p>
                                <p class="mt-0.5 text-xs text-neutral-600">출고 {{ row.shipped_at }}</p>

                                <div class="mt-2 flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-500"
                                        @click="deliver(row)"
                                    >
                                        배송완료
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border border-red-500/40 px-3 py-1 text-xs text-red-400 hover:bg-red-500/10"
                                        @click="revert(row)"
                                    >
                                        출고취소
                                    </button>
                                </div>
                            </template>

                            <!-- 배송완료 -->
                            <template v-else>
                                <p class="text-xs text-neutral-400">
                                    {{ row.carrier_name }} <span class="font-mono">{{ row.tracking_no }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-neutral-600">완료 {{ row.delivered_at }}</p>
                            </template>

                            <p v-if="row.memo" class="mt-2 text-xs text-neutral-600">{{ row.memo }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="orders.data.length === 0" class="mt-6 text-sm text-neutral-500">
            해당하는 주문이 없습니다.
        </p>

        <Pagination :paginator="orders" theme="dark" />
    </AdminLayout>
</template>
