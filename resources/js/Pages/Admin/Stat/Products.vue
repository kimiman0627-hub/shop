<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    sort: { type: String, default: 'revenue' },
    aggregatedAt: { type: String, default: null },
});

const range = reactive({ from: props.filters.from, to: props.filters.to });

const apply = (sort = props.sort) => router.get('/admin/stats/products', { ...range, sort }, {
    preserveState: true,
    replace: true,
});

const preset = (days) => {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - (days - 1));
    const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    range.from = fmt(start);
    range.to = fmt(end);
    apply();
};

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;
const num = (n) => Number(n ?? 0).toLocaleString('ko-KR');

// 조회가 0 이면 서버가 null 을 준다. 0% 로 찍으면 "안 팔린다"는 오해가 된다.
const rate = (v) => (v === null || v === undefined ? '-' : `${v}%`);

const columns = [
    { key: 'view_count', label: '조회' },
    { key: 'cart_count', label: '장바구니' },
    { key: 'order_count', label: '주문' },
    { key: 'quantity', label: '판매수량' },
    { key: 'revenue', label: '매출' },
    { key: 'order_rate', label: '구매전환' },
];

// 전체 전환율. 상품별 전환율의 평균이 아니라 합계 기준이다 —
// 조회 3건짜리 상품의 100% 가 전체 평균을 끌어올리면 안 된다.
const totalOrderRate = computed(() => (
    props.totals.view_count > 0
        ? `${Math.round((props.totals.order_count / props.totals.view_count) * 1000) / 10}%`
        : '-'
));

const inputClass = 'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
const card = 'rounded-xl border border-neutral-800 bg-neutral-900/30 p-5';
</script>

<template>
    <AdminLayout title="상품분석">
        <h2 class="text-xl font-semibold tracking-tight">상품분석</h2>
        <p class="mt-1 text-sm text-neutral-500">
            상품별로 얼마나 보고, 담고, 실제로 샀는지를 봅니다. 조회는 상품 상세를 연 횟수입니다.
        </p>
        <p v-if="aggregatedAt" class="mt-1 text-xs text-neutral-600">
            판매 실적 집계 기준 {{ aggregatedAt }} · 조회·장바구니는 실시간으로 쌓입니다.
        </p>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <input v-model="range.from" type="date" :class="inputClass">
            <span class="text-neutral-600">~</span>
            <input v-model="range.to" type="date" :class="inputClass">
            <button type="button" :class="inputClass" @click="apply()">조회</button>
            <button type="button" :class="inputClass" @click="preset(7)">최근 7일</button>
            <button type="button" :class="inputClass" @click="preset(30)">최근 30일</button>
            <button type="button" :class="inputClass" @click="preset(90)">최근 90일</button>
        </div>

        <!-- 합계 -->
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div :class="card">
                <p class="text-xs text-neutral-500">전체 조회</p>
                <p class="mt-1 text-2xl font-semibold">{{ num(totals.view_count) }}</p>
                <p class="mt-1 text-xs text-neutral-500">장바구니 {{ num(totals.cart_count) }}회</p>
            </div>
            <div :class="card">
                <p class="text-xs text-neutral-500">구매 전환</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-300">{{ totalOrderRate }}</p>
                <p class="mt-1 text-xs text-neutral-500">조회 대비 주문 건수</p>
            </div>
            <div :class="card">
                <p class="text-xs text-neutral-500">판매 수량</p>
                <p class="mt-1 text-2xl font-semibold">{{ num(totals.quantity) }}</p>
                <p class="mt-1 text-xs text-neutral-500">주문 {{ num(totals.order_count) }}건</p>
            </div>
            <div :class="card">
                <p class="text-xs text-neutral-500">상품 매출</p>
                <p class="mt-1 text-2xl font-semibold">{{ won(totals.revenue) }}</p>
                <p class="mt-1 text-xs text-neutral-500">할인 전 상품 합계</p>
            </div>
        </div>

        <!-- 상품별 -->
        <div :class="[card, 'mt-6']">
            <p class="text-sm text-neutral-400">
                열 제목을 누르면 그 기준으로 정렬합니다.
                <span class="text-neutral-600">· 현재 {{ columns.find((c) => c.key === sort)?.label ?? '매출' }} 순</span>
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="border-b border-neutral-800 text-left text-neutral-500">
                        <tr>
                            <th class="py-2 font-medium">상품</th>
                            <th
                                v-for="c in columns"
                                :key="c.key"
                                class="py-2 text-right font-medium"
                            >
                                <button
                                    type="button"
                                    class="transition hover:text-neutral-200"
                                    :class="sort === c.key ? 'text-neutral-100' : ''"
                                    @click="apply(c.key)"
                                >
                                    {{ c.label }}
                                    <span v-if="sort === c.key" aria-hidden="true">↓</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.product_id" class="border-b border-neutral-900">
                            <td class="max-w-[16rem] truncate py-3">
                                {{ r.name }}
                                <span v-if="r.status === 'HIDDEN'" class="ml-1 text-xs text-neutral-600">숨김</span>
                                <span v-else-if="r.status === 'SOLD_OUT'" class="ml-1 text-xs text-amber-500/80">품절</span>
                            </td>
                            <td class="py-3 text-right text-neutral-300">{{ num(r.view_count) }}</td>
                            <td class="py-3 text-right text-neutral-300">{{ num(r.cart_count) }}</td>
                            <td class="py-3 text-right text-neutral-300">{{ num(r.order_count) }}</td>
                            <td class="py-3 text-right text-neutral-300">{{ num(r.quantity) }}</td>
                            <td class="py-3 text-right">{{ won(r.revenue) }}</td>
                            <td
                                class="py-3 text-right"
                                :class="r.order_rate === null ? 'text-neutral-600' : 'text-emerald-300'"
                            >
                                {{ rate(r.order_rate) }}
                            </td>
                        </tr>

                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="py-8 text-center text-neutral-500">
                                이 기간에는 기록이 없습니다.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-neutral-600">
                구매전환 = 주문 건수 ÷ 조회수. 조회 기록이 없는 상품은 비율을 내지 않고 '-' 로 둡니다.
                매출은 쿠폰·배송비를 뺀 상품 합계 기준이라 매출통계의 결제 매출과 다를 수 있습니다.
            </p>
        </div>
    </AdminLayout>
</template>
