<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BarSeries from '@/Components/BarSeries.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    series: { type: Array, required: true },
    byProduct: { type: Array, required: true },
    byCategory: { type: Array, required: true },
    aggregatedAt: { type: String, default: null },
});

const range = reactive({ from: props.filters.from, to: props.filters.to });

const apply = () => router.get('/admin/stats/sales', { ...range }, {
    preserveState: true,
    replace: true,
});

// 자주 쓰는 기간은 버튼으로. 날짜를 두 번 고르는 건 번거롭다.
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

// 카테고리 막대 폭은 최대 매출 대비 비율이다.
const categoryMax = computed(
    () => Math.max(1, ...props.byCategory.map((c) => c.amount)),
);

const inputClass = 'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
const card = 'rounded-xl border border-neutral-800 bg-neutral-900/30 p-5';
</script>

<template>
    <AdminLayout title="매출통계">
        <h2 class="text-xl font-semibold tracking-tight">매출통계</h2>
        <p class="mt-1 text-sm text-neutral-500">
            결제완료 이후 주문만 집계합니다. 미결제·취소 주문은 매출에 잡히지 않습니다.
        </p>
        <p v-if="aggregatedAt" class="mt-1 text-xs text-neutral-600">
            집계 기준 {{ aggregatedAt }} · 5분마다 갱신됩니다. 시각이 멈춰 있으면 스케줄러를 확인하세요.
        </p>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <input v-model="range.from" type="date" :class="inputClass">
            <span class="text-neutral-600">~</span>
            <input v-model="range.to" type="date" :class="inputClass">
            <button
                type="button"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900"
                @click="apply"
            >
                조회
            </button>

            <span class="ml-2 flex gap-1">
                <button
                    v-for="p in [[7, '7일'], [30, '30일'], [90, '90일']]"
                    :key="p[0]"
                    type="button"
                    class="rounded-lg border border-neutral-700 px-3 py-1.5 text-xs text-neutral-400 hover:text-neutral-100"
                    @click="preset(p[0])"
                >
                    최근 {{ p[1] }}
                </button>
            </span>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div :class="card">
                <p class="text-xs text-neutral-500">결제 매출</p>
                <p class="mt-1 text-2xl font-semibold">{{ won(summary.revenue) }}</p>
                <p class="mt-1 text-xs text-neutral-500">주문 {{ num(summary.order_count) }}건</p>
            </div>
            <div :class="card">
                <p class="text-xs text-neutral-500">환불</p>
                <p class="mt-1 text-2xl font-semibold text-rose-300">{{ won(summary.refunded) }}</p>
                <p class="mt-1 text-xs text-neutral-500">반품 처리완료 기준</p>
            </div>
            <div :class="card">
                <p class="text-xs text-neutral-500">순매출</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-300">{{ won(summary.net_revenue) }}</p>
                <p class="mt-1 text-xs text-neutral-500">결제 − 환불</p>
            </div>
            <div :class="card">
                <p class="text-xs text-neutral-500">객단가</p>
                <p class="mt-1 text-2xl font-semibold">{{ won(summary.average_order_value) }}</p>
                <p class="mt-1 text-xs text-neutral-500">쿠폰 할인 {{ won(summary.discount_total) }}</p>
            </div>
        </div>

        <!--
            숫자가 어떻게 맞물리는지 한 줄로 보인다.
            아래 카테고리·상품별 합계는 맨 위 '상품 합계' 와 같은 값이다.
        -->
        <div :class="[card, 'mt-3']">
            <p class="text-sm text-neutral-300">정산 내역</p>

            <div class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <span class="text-neutral-400">상품 합계</span>
                    <span>{{ won(summary.items_total) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-400">쿠폰 할인</span>
                    <span class="text-amber-300">− {{ won(summary.discount_total) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-400">배송비</span>
                    <span>+ {{ won(summary.shipping_fee) }}</span>
                </div>
                <div class="flex justify-between border-t border-neutral-800 pt-1.5 font-medium">
                    <span>결제 매출</span>
                    <span>{{ won(summary.revenue) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-400">환불</span>
                    <span class="text-rose-300">− {{ won(summary.refunded) }}</span>
                </div>
                <div class="flex justify-between border-t border-neutral-800 pt-1.5 font-semibold">
                    <span>순매출</span>
                    <span class="text-emerald-300">{{ won(summary.net_revenue) }}</span>
                </div>
            </div>
        </div>

        <div :class="[card, 'mt-3']">
            <p class="text-sm text-neutral-300">일별 매출</p>
            <BarSeries class="mt-4" :series="series" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section>
                <h3 class="text-sm font-medium text-neutral-400">
                    카테고리별
                    <span class="ml-1 font-normal text-neutral-600">
                        · 상품 합계 {{ won(summary.items_total) }} 기준
                    </span>
                </h3>
                <div :class="[card, 'mt-3']">
                    <ul v-if="byCategory.length" class="space-y-3">
                        <li v-for="c in byCategory" :key="c.name">
                            <div class="flex items-baseline justify-between text-sm">
                                <span>{{ c.name }}</span>
                                <span class="text-neutral-400">
                                    {{ won(c.amount) }}
                                    <span class="ml-1 text-xs text-neutral-600">{{ num(c.quantity) }}개</span>
                                </span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-neutral-800">
                                <div
                                    class="h-1.5 rounded-full bg-neutral-400"
                                    :style="{ width: `${Math.round((c.amount / categoryMax) * 100)}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-neutral-500">이 기간에 판매가 없습니다.</p>
                </div>
            </section>

            <section>
                <h3 class="text-sm font-medium text-neutral-400">
                    상품별
                    <span class="ml-1 font-normal text-neutral-600">
                        · 상품 합계 {{ won(summary.items_total) }} 기준
                    </span>
                </h3>
                <div class="mt-3 overflow-hidden rounded-xl border border-neutral-800">
                    <table class="w-full text-sm">
                        <thead class="bg-neutral-900/60 text-left text-xs text-neutral-400">
                            <tr>
                                <th class="px-4 py-3">상품</th>
                                <th class="px-4 py-3 text-right">수량</th>
                                <th class="px-4 py-3 text-right">매출</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-800">
                            <tr v-for="p in byProduct" :key="p.product_id ?? p.name">
                                <td class="px-4 py-3">{{ p.name }}</td>
                                <td class="px-4 py-3 text-right text-neutral-400">{{ num(p.quantity) }}</td>
                                <td class="px-4 py-3 text-right">{{ won(p.amount) }}</td>
                            </tr>
                            <tr v-if="byProduct.length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-neutral-500">
                                    이 기간에 판매가 없습니다.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <p class="mt-6 text-xs text-neutral-600">
            상품명은 주문 시점에 저장된 이름이고, 카테고리는 현재 상품 정보 기준이라
            상품을 다른 카테고리로 옮기면 과거 매출도 따라 옮겨갑니다.
        </p>
    </AdminLayout>
</template>
