<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BarSeries from '@/Components/BarSeries.vue';

const props = defineProps({
    data: { type: Object, required: true },
});

const page = usePage();
const admin = computed(() => page.props.auth.admin);

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;
const num = (n) => Number(n ?? 0).toLocaleString('ko-KR');

// 권한이 없는 카드는 서버가 null 로 내린다. 그리지 않는다.
const sales = computed(() => props.data.sales);
const aggregatedAt = computed(() => props.data.aggregated_at);
const todo = computed(() => props.data.todo ?? []);
const series = computed(() => props.data.series ?? []);
const topProducts = computed(() => props.data.top_products);
const lowStock = computed(() => props.data.low_stock);
const recentOrders = computed(() => props.data.recent_orders);
const members = computed(() => props.data.members);

// 처리할 일이 하나도 없으면 목록 대신 한 줄로 끝낸다.
const pending = computed(() => todo.value.filter((t) => t.count > 0));

const statusTone = {
    PENDING: 'bg-amber-500/15 text-amber-300',
    PAID: 'bg-sky-500/15 text-sky-300',
    PREPARING: 'bg-indigo-500/15 text-indigo-300',
    SHIPPING: 'bg-violet-500/15 text-violet-300',
    DELIVERED: 'bg-emerald-500/15 text-emerald-300',
    CANCELED: 'bg-neutral-500/15 text-neutral-400',
    REFUNDED: 'bg-rose-500/15 text-rose-300',
};

const card = 'rounded-xl border border-neutral-800 bg-neutral-900/30 p-5';
</script>

<template>
    <AdminLayout title="대시보드">
        <h2 class="text-2xl font-semibold tracking-tight">
            {{ admin?.name }}님, 반갑습니다
        </h2>

        <!-- 처리 대기: 화면에서 제일 위에 온다. 오늘 손댈 일이기 때문이다. -->
        <section class="mt-8">
            <h3 class="text-sm font-medium text-neutral-400">처리 대기</h3>

            <div v-if="pending.length" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="t in pending"
                    :key="t.label"
                    :href="t.href"
                    :class="[card, 'block transition hover:border-neutral-600']"
                >
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm text-neutral-300">{{ t.label }}</span>
                        <span class="text-2xl font-semibold text-amber-300">{{ num(t.count) }}</span>
                    </div>
                    <p class="mt-2 text-xs text-neutral-500">{{ t.hint }}</p>
                </Link>
            </div>

            <p v-else class="mt-3 rounded-xl border border-neutral-800 px-5 py-4 text-sm text-neutral-500">
                지금 처리할 일이 없습니다.
            </p>
        </section>

        <!-- 매출 -->
        <section v-if="sales" class="mt-10">
            <div class="flex items-baseline gap-2">
                <h3 class="text-sm font-medium text-neutral-400">매출</h3>
                <span v-if="aggregatedAt" class="text-xs text-neutral-600">집계 기준 {{ aggregatedAt }}</span>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div :class="card">
                    <p class="text-xs text-neutral-500">오늘 매출</p>
                    <p class="mt-1 text-2xl font-semibold">{{ won(sales.today.revenue) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">주문 {{ num(sales.today.order_count) }}건</p>
                </div>
                <div :class="card">
                    <p class="text-xs text-neutral-500">이번 달 매출</p>
                    <p class="mt-1 text-2xl font-semibold">{{ won(sales.month.revenue) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">주문 {{ num(sales.month.order_count) }}건</p>
                </div>
                <div :class="card">
                    <p class="text-xs text-neutral-500">이번 달 객단가</p>
                    <p class="mt-1 text-2xl font-semibold">{{ won(sales.month.average_order_value) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">쿠폰 할인 {{ won(sales.month.discount_total) }}</p>
                </div>
                <div :class="card">
                    <p class="text-xs text-neutral-500">이번 달 환불</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-300">{{ won(sales.month.refunded) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">순매출 {{ won(sales.month.net_revenue) }}</p>
                </div>
            </div>

            <div :class="[card, 'mt-3']">
                <div class="flex items-baseline justify-between">
                    <p class="text-sm text-neutral-300">최근 14일 매출</p>
                    <Link href="/admin/stats/sales" class="text-xs text-neutral-500 hover:text-neutral-200">
                        매출통계 →
                    </Link>
                </div>
                <BarSeries class="mt-4" :series="series" />
            </div>
        </section>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <!-- 인기 상품 -->
            <section v-if="topProducts">
                <h3 class="text-sm font-medium text-neutral-400">최근 30일 많이 팔린 상품</h3>
                <div :class="[card, 'mt-3']">
                    <ol v-if="topProducts.length" class="space-y-3">
                        <li
                            v-for="(p, i) in topProducts"
                            :key="p.product_id ?? p.name"
                            class="flex items-center gap-3 text-sm"
                        >
                            <span class="w-5 shrink-0 text-neutral-600">{{ i + 1 }}</span>
                            <span class="flex-1 truncate">{{ p.name }}</span>
                            <span class="shrink-0 text-neutral-500">{{ num(p.quantity) }}개</span>
                            <span class="w-24 shrink-0 text-right">{{ won(p.amount) }}</span>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-neutral-500">아직 판매 이력이 없습니다.</p>
                </div>
            </section>

            <!-- 재고 경고 -->
            <section v-if="lowStock">
                <h3 class="text-sm font-medium text-neutral-400">재고 부족</h3>
                <div :class="[card, 'mt-3']">
                    <ul v-if="lowStock.length" class="space-y-3">
                        <li v-for="v in lowStock" :key="v.sku" class="flex items-center gap-3 text-sm">
                            <Link
                                :href="`/admin/products/${v.product_id}/edit`"
                                class="flex-1 truncate hover:underline"
                            >
                                {{ v.product_name }}
                                <span class="text-neutral-500">/ {{ v.option_label }}</span>
                            </Link>
                            <span
                                class="shrink-0 rounded px-2 py-0.5 text-xs"
                                :class="v.available <= 0
                                    ? 'bg-rose-500/15 text-rose-300'
                                    : 'bg-amber-500/15 text-amber-300'"
                            >
                                {{ v.available <= 0 ? '품절' : `${v.available}개 남음` }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-neutral-500">재고가 부족한 조합이 없습니다.</p>
                    <p v-if="lowStock.length" class="mt-3 border-t border-neutral-800 pt-3 text-xs text-neutral-500">
                        판매가능 = 실물 − 결제중 예약. 예약분은 결제가 끝나거나 만료되면 풀립니다.
                    </p>
                </div>
            </section>
        </div>

        <!-- 최근 주문 -->
        <section v-if="recentOrders" class="mt-10">
            <div class="flex items-baseline justify-between">
                <h3 class="text-sm font-medium text-neutral-400">최근 주문</h3>
                <Link href="/admin/orders" class="text-xs text-neutral-500 hover:text-neutral-200">
                    주문목록 →
                </Link>
            </div>

            <div class="mt-3 overflow-hidden rounded-xl border border-neutral-800">
                <table class="w-full text-sm">
                    <thead class="bg-neutral-900/60 text-left text-xs text-neutral-400">
                        <tr>
                            <th class="px-4 py-3">주문번호</th>
                            <th class="px-4 py-3">주문자</th>
                            <th class="px-4 py-3">상품</th>
                            <th class="px-4 py-3 text-right">결제금액</th>
                            <th class="px-4 py-3">상태</th>
                            <th class="px-4 py-3">주문일시</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-800">
                        <tr v-for="o in recentOrders" :key="o.id" class="hover:bg-neutral-900/40">
                            <td class="px-4 py-3">
                                <Link :href="`/admin/orders/${o.id}`" class="font-mono text-xs hover:underline">
                                    {{ o.order_no }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">{{ o.orderer_name }}</td>
                            <td class="px-4 py-3 text-neutral-300">{{ o.summary }}</td>
                            <td class="px-4 py-3 text-right">{{ won(o.total_amount) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs" :class="statusTone[o.status]">
                                    {{ o.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-neutral-500">{{ o.ordered_at }}</td>
                        </tr>
                        <tr v-if="recentOrders.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">주문이 없습니다.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 회원 -->
        <section v-if="members" class="mt-10">
            <h3 class="text-sm font-medium text-neutral-400">회원</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div :class="card">
                    <p class="text-xs text-neutral-500">전체 회원</p>
                    <p class="mt-1 text-2xl font-semibold">{{ num(members.total) }}</p>
                </div>
                <div :class="card">
                    <p class="text-xs text-neutral-500">최근 7일 신규</p>
                    <p class="mt-1 text-2xl font-semibold">{{ num(members.new_this_week) }}</p>
                </div>
                <div :class="card">
                    <p class="text-xs text-neutral-500">이메일 미인증</p>
                    <p class="mt-1 text-2xl font-semibold">{{ num(members.unverified) }}</p>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
