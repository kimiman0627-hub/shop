<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    statusOptions: { type: Array, required: true },
});

const search = reactive({
    status: props.filters.status ?? '',
    keyword: props.filters.keyword ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const apply = () => router.get('/admin/orders', { ...search }, {
    preserveState: true,
    replace: true,
});

const reset = () => {
    Object.assign(search, { status: '', keyword: '', from: '', to: '' });
    apply();
};

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const statusClass = (status) => ({
    'bg-neutral-700/40 text-neutral-300': status === 'PENDING',
    'bg-emerald-500/15 text-emerald-300': status === 'PAID' || status === 'DELIVERED',
    'bg-amber-500/15 text-amber-300': status === 'PREPARING',
    'bg-sky-500/15 text-sky-300': status === 'SHIPPING',
    'bg-red-500/15 text-red-300': status === 'CANCELED' || status === 'REFUNDED',
});

const inputClass = 'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="주문목록">
        <h2 class="text-xl font-semibold tracking-tight">주문</h2>
        <p class="mt-1 text-sm text-neutral-500">
            모든 상태의 주문을 봅니다. 배송 처리는 <Link href="/admin/shipments" class="underline">배송관리</Link>에서 합니다.
        </p>

        <form class="mt-6 flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <select v-model="search.status" :class="inputClass">
                <option value="">전체 상태</option>
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>

            <input v-model="search.from" type="date" :class="inputClass">
            <span class="text-neutral-600">~</span>
            <input v-model="search.to" type="date" :class="inputClass">

            <input v-model="search.keyword" type="text" placeholder="주문번호 · 주문자 · 연락처 · 수령인" :class="inputClass">

            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
            <button type="button" class="rounded-lg border border-neutral-700 px-3 py-2 text-sm text-neutral-300" @click="reset">
                초기화
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">주문번호</th>
                        <th class="py-2 font-medium">주문자</th>
                        <th class="py-2 font-medium">상품</th>
                        <th class="py-2 text-right font-medium">결제금액</th>
                        <th class="py-2 font-medium">주문일시</th>
                        <th class="py-2 text-center font-medium">상태</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders.data" :key="o.id" class="border-b border-neutral-900">
                        <td class="py-3">
                            <Link :href="`/admin/orders/${o.id}`" class="font-mono text-xs hover:underline">
                                {{ o.order_no }}
                            </Link>
                            <p v-if="o.tracking_no" class="mt-0.5 font-mono text-xs text-neutral-600">
                                {{ o.tracking_no }}
                            </p>
                        </td>
                        <td class="py-3">
                            {{ o.orderer_name }}
                            <span v-if="o.is_guest" class="ml-1 rounded bg-neutral-800 px-1 py-0.5 text-xs text-neutral-400">
                                비회원
                            </span>
                            <p class="mt-0.5 text-xs text-neutral-600">{{ o.orderer_phone }}</p>
                        </td>
                        <td class="py-3 text-neutral-300">{{ o.item_summary }}</td>
                        <td class="py-3 text-right">{{ won(o.total_amount) }}</td>
                        <td class="py-3 text-neutral-400">
                            {{ o.ordered_at }}
                            <p v-if="o.overdue" class="mt-0.5 text-xs text-red-400">
                                입금기한 초과
                            </p>
                        </td>
                        <td class="py-3 text-center">
                            <span class="rounded px-1.5 py-0.5 text-xs" :class="statusClass(o.status)">
                                {{ o.status_label }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="orders.data.length === 0" class="mt-6 text-sm text-neutral-500">
            조건에 맞는 주문이 없습니다.
        </p>

        <div v-if="orders.last_page > 1" class="mt-6 flex gap-1">
            <Link
                v-for="link in orders.links"
                :key="link.label"
                :href="link.url ?? '#'"
                class="rounded px-3 py-1 text-sm"
                :class="link.active ? 'bg-neutral-100 text-neutral-900' : 'text-neutral-400 hover:bg-neutral-900'"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
