<script setup>
import { computed, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    returns: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
    statusOptions: { type: Array, required: true },
    typeOptions: { type: Array, required: true },
    responsibilityOptions: { type: Array, required: true },
    selected: { type: Object, default: null },
});

const search = reactive({
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    keyword: props.filters.keyword ?? '',
});

const query = () => ({ ...search });

const apply = () => router.get('/admin/returns', query(), { preserveState: true, replace: true });

// 상세는 URL 쿼리로 연다. 새로고침·뒤로가기·링크 공유가 동작한다.
const open = (row) => router.get('/admin/returns', { ...query(), selected: row.id }, {
    preserveState: true,
    preserveScroll: true,
});

const close = () => router.get('/admin/returns', query(), { preserveState: true, preserveScroll: true });

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const detail = computed(() => props.selected?.return ?? null);
const order = computed(() => props.selected?.order ?? null);
const estimates = computed(() => props.selected?.estimates ?? null);
const carriers = computed(() => props.selected?.carriers ?? []);

const statusTone = {
    REQUESTED: 'bg-amber-500/15 text-amber-300',
    APPROVED: 'bg-sky-500/15 text-sky-300',
    PICKING: 'bg-indigo-500/15 text-indigo-300',
    RECEIVED: 'bg-violet-500/15 text-violet-300',
    COMPLETED: 'bg-emerald-500/15 text-emerald-300',
    REJECTED: 'bg-neutral-500/15 text-neutral-400',
};

/* 처리 폼. 상세가 바뀌면 현재 값으로 다시 채운다. */
const form = useForm({
    responsibility: 'CUSTOMER',
    restock: true,
    admin_memo: '',
    reject_reason: '',
    pickup_carrier: 'CJ',
    pickup_tracking_no: '',
    exchange_carrier: 'CJ',
    exchange_tracking_no: '',
});

watch(detail, (value) => {
    if (!value) return;
    form.responsibility = value.responsibility;
    form.restock = value.restock;
    form.admin_memo = value.admin_memo ?? '';
    form.reject_reason = '';
    form.pickup_carrier = value.pickup?.carrier ?? 'CJ';
    form.pickup_tracking_no = value.pickup?.tracking_no ?? '';
    form.exchange_carrier = value.exchange?.carrier ?? 'CJ';
    form.exchange_tracking_no = value.exchange?.tracking_no ?? '';
    form.clearErrors();
}, { immediate: true });

const submit = (action) => form.put(`/admin/returns/${detail.value.id}/${action}`, {
    preserveScroll: true,
});

/*
 * 승인 전 예상 환불액. 귀책을 바꾸면 배송비 부담이 뒤집히므로
 * 서버가 두 경우를 모두 계산해 보내고 화면은 고르기만 한다 —
 * 여기서 다시 계산하면 서버 규칙과 어긋난다.
 */
const preview = computed(() => estimates.value?.[form.responsibility] ?? null);

// 승인 전에는 예상액을, 승인 후에는 확정 스냅샷을 보여준다.
const amounts = computed(() => preview.value ?? detail.value);

const inputClass = 'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
const btn = 'rounded-lg px-3 py-2 text-sm font-medium disabled:opacity-40';
</script>

<template>
    <AdminLayout title="반품·교환">
        <h2 class="text-xl font-semibold tracking-tight">반품·교환</h2>
        <p class="mt-1 text-sm text-neutral-500">
            출고된 뒤의 되돌림은 여기서 처리합니다. 재고와 환불은 <strong>처리완료</strong> 시점에 반영됩니다.
        </p>

        <div class="mt-5 flex flex-wrap gap-2">
            <button
                v-for="s in statusOptions"
                :key="s.value"
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :class="search.status === s.value
                    ? 'border-neutral-300 bg-neutral-100 text-neutral-900'
                    : 'border-neutral-700 text-neutral-400 hover:text-neutral-200'"
                @click="search.status = search.status === s.value ? '' : s.value; apply()"
            >
                {{ s.label }}
                <span class="ml-1 text-neutral-500">{{ counts[s.value] ?? 0 }}</span>
            </button>
        </div>

        <form class="mt-4 flex flex-wrap gap-2" @submit.prevent="apply">
            <select v-model="search.type" :class="inputClass">
                <option value="">유형 전체</option>
                <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <input v-model="search.keyword" type="text" placeholder="주문번호 · 주문자 · 연락처" :class="inputClass">
            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-neutral-800">
            <div class="overflow-x-auto">
                <table class="min-w-[44rem] w-full text-sm">
                    <thead class="bg-neutral-900/60 text-left text-xs text-neutral-400">
                        <tr>
                            <th class="px-4 py-3">주문번호</th>
                            <th class="px-4 py-3">상품</th>
                            <th class="px-4 py-3">유형</th>
                            <th class="px-4 py-3">사유 · 귀책</th>
                            <th class="px-4 py-3 text-right">환불액</th>
                            <th class="px-4 py-3">상태</th>
                            <th class="px-4 py-3">접수일시</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-800">
                        <tr
                            v-for="row in returns.data"
                            :key="row.id"
                            class="cursor-pointer hover:bg-neutral-900/40"
                            @click="open(row)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ row.order_no }}</td>
                            <td class="px-4 py-3">
                                <p>{{ row.summary }}</p>
                                <p class="text-xs text-neutral-500">{{ row.orderer_name }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.type_label }}</td>
                            <td class="px-4 py-3 text-xs text-neutral-400">
                                {{ row.reason_label }} · {{ row.responsibility_label }}
                            </td>
                            <td class="px-4 py-3 text-right">{{ won(row.refund_amount) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs" :class="statusTone[row.status]">
                                    {{ row.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-neutral-500">{{ row.requested_at }}</td>
                        </tr>
                        <tr v-if="returns.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">
                                해당하는 반품·교환 신청이 없습니다.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 상세 · 처리 -->
        <div
            v-if="detail"
            class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/60 p-6"
            @click.self="close"
        >
            <div class="w-full max-w-3xl rounded-2xl border border-neutral-800 bg-neutral-950 p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold">
                            {{ detail.type_label }} #{{ detail.id }}
                            <span class="ml-2 rounded px-2 py-0.5 text-xs" :class="statusTone[detail.status]">
                                {{ detail.status_label }}
                            </span>
                        </h3>
                        <p class="mt-1 font-mono text-xs text-neutral-500">
                            {{ order.order_no }} · {{ order.orderer_name }} · {{ order.orderer_phone }}
                            <span class="ml-1">({{ order.status_label }})</span>
                        </p>
                    </div>
                    <button type="button" class="text-neutral-500 hover:text-neutral-200" @click="close">닫기</button>
                </div>

                <p v-if="form.errors.return" class="mt-4 rounded-lg bg-rose-500/10 px-3 py-2 text-sm text-rose-300">
                    {{ form.errors.return }}
                </p>

                <!-- 대상 상품 -->
                <div class="mt-5 rounded-xl border border-neutral-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-[44rem] w-full text-sm">
                            <thead class="bg-neutral-900/60 text-left text-xs text-neutral-400">
                                <tr>
                                    <th class="px-3 py-2">상품</th>
                                    <th class="px-3 py-2 text-right">단가</th>
                                    <th class="px-3 py-2 text-right">수량</th>
                                    <th v-if="detail.type === 'EXCHANGE'" class="px-3 py-2">교환 옵션</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-800">
                                <tr v-for="line in detail.items" :key="line.id">
                                    <td class="px-3 py-2">
                                        {{ line.product_name }}
                                        <span v-if="line.variant_name" class="text-neutral-500">/ {{ line.variant_name }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ won(line.unit_price) }}</td>
                                    <td class="px-3 py-2 text-right">{{ line.quantity }}</td>
                                    <td v-if="detail.type === 'EXCHANGE'" class="px-3 py-2 text-neutral-300">
                                        {{ line.exchange_variant_name ?? '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-neutral-500">사유</dt>
                        <dd>{{ detail.reason_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-500">귀책</dt>
                        <dd>{{ detail.responsibility_label }}</dd>
                    </div>
                    <div v-if="detail.reason_detail" class="col-span-2">
                        <dt class="text-xs text-neutral-500">고객 설명</dt>
                        <dd class="whitespace-pre-line text-neutral-300">{{ detail.reason_detail }}</dd>
                    </div>
                </dl>

                <!-- 금액 -->
                <div class="mt-4 rounded-xl border border-neutral-800 p-4 text-sm">
                    <p class="text-xs text-neutral-500">
                        {{ detail.status === 'REQUESTED' ? '예상 정산 (승인 시 확정)' : '정산 내역 (승인 시점 스냅샷)' }}
                    </p>
                    <div class="mt-2 space-y-1">
                        <div class="flex justify-between">
                            <span class="text-neutral-400">상품 금액</span>
                            <span>{{ won(amounts.items_refund) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-400">쿠폰 안분 차감</span>
                            <span>- {{ won(amounts.coupon_deduction) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-400">
                                반품 배송비 {{ detail.type === 'EXCHANGE' ? '(고객 부담 · 별도 수납)' : '차감' }}
                            </span>
                            <span>- {{ won(amounts.shipping_deduction) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-400">최초 배송비 환불</span>
                            <span>+ {{ won(amounts.shipping_refund) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-neutral-800 pt-2 font-semibold">
                            <span>환불액</span>
                            <span>{{ won(amounts.refund_amount) }}</span>
                        </div>
                    </div>
                    <p v-if="detail.type === 'EXCHANGE'" class="mt-2 text-xs text-neutral-500">
                        교환은 금액 정산이 없습니다. 배송비는 시스템 밖에서 수납하세요.
                    </p>
                </div>

                <!-- 송장 -->
                <div v-if="detail.pickup || detail.exchange" class="mt-4 space-y-1 text-sm">
                    <p v-if="detail.pickup" class="text-neutral-400">
                        회수: {{ detail.pickup.carrier_name }}
                        <a
                            v-if="detail.pickup.tracking_url"
                            :href="detail.pickup.tracking_url"
                            target="_blank"
                            class="ml-1 font-mono text-xs text-sky-400 underline"
                        >{{ detail.pickup.tracking_no }}</a>
                        <span v-else class="ml-1 font-mono text-xs">{{ detail.pickup.tracking_no ?? '송장없음' }}</span>
                    </p>
                    <p v-if="detail.exchange" class="text-neutral-400">
                        교환 발송: {{ detail.exchange.carrier_name }}
                        <a
                            v-if="detail.exchange.tracking_url"
                            :href="detail.exchange.tracking_url"
                            target="_blank"
                            class="ml-1 font-mono text-xs text-sky-400 underline"
                        >{{ detail.exchange.tracking_no }}</a>
                        <span v-else class="ml-1 font-mono text-xs">{{ detail.exchange.tracking_no ?? '송장없음' }}</span>
                    </p>
                </div>

                <!-- 단계별 처리 -->
                <div class="mt-5 border-t border-neutral-800 pt-5">
                    <!-- 접수 → 승인 / 반려 -->
                    <div v-if="detail.status === 'REQUESTED'" class="space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <select v-model="form.responsibility" :class="inputClass">
                                <option v-for="r in responsibilityOptions" :key="r.value" :value="r.value">
                                    {{ r.label }}
                                </option>
                            </select>
                            <label class="flex items-center gap-2 text-sm text-neutral-300">
                                <input v-model="form.restock" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                                재판매 가능 (입고 시 재고 복구)
                            </label>
                        </div>
                        <textarea
                            v-model="form.admin_memo"
                            rows="2"
                            placeholder="관리자 메모"
                            :class="[inputClass, 'w-full']"
                        />
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :class="[btn, 'bg-emerald-500/90 text-neutral-950']"
                                :disabled="form.processing"
                                @click="submit('approve')"
                            >
                                승인
                            </button>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <input v-model="form.reject_reason" type="text" placeholder="반려 사유" :class="[inputClass, 'flex-1']">
                            <button
                                type="button"
                                :class="[btn, 'border border-neutral-700 text-neutral-300']"
                                :disabled="form.processing || !form.reject_reason"
                                @click="submit('reject')"
                            >
                                반려
                            </button>
                        </div>
                    </div>

                    <!-- 승인 → 회수 등록 / 바로 입고 -->
                    <div v-else-if="detail.status === 'APPROVED'" class="space-y-3">
                        <p class="text-xs text-neutral-500">
                            회수 송장을 등록하거나, 고객이 직접 보낸 경우 바로 입고 처리하세요.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <select v-model="form.pickup_carrier" :class="inputClass">
                                <option v-for="c in carriers" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                            <input v-model="form.pickup_tracking_no" type="text" placeholder="회수 송장번호" :class="inputClass">
                            <button
                                type="button"
                                :class="[btn, 'bg-neutral-100 text-neutral-900']"
                                :disabled="form.processing"
                                @click="submit('pickup')"
                            >
                                회수 등록
                            </button>
                            <button
                                type="button"
                                :class="[btn, 'border border-neutral-700 text-neutral-300']"
                                :disabled="form.processing"
                                @click="submit('receive')"
                            >
                                바로 입고
                            </button>
                        </div>
                    </div>

                    <!-- 수거중 → 입고 -->
                    <div v-else-if="detail.status === 'PICKING'" class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-neutral-300">
                            <input v-model="form.restock" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                            재판매 가능 (물건을 확인하고 정하세요)
                        </label>
                        <textarea v-model="form.admin_memo" rows="2" placeholder="상태 메모" :class="[inputClass, 'w-full']" />
                        <button
                            type="button"
                            :class="[btn, 'bg-neutral-100 text-neutral-900']"
                            :disabled="form.processing"
                            @click="submit('receive')"
                        >
                            입고 확인
                        </button>
                    </div>

                    <!-- 입고 → 완료 -->
                    <div v-else-if="detail.status === 'RECEIVED'" class="space-y-3">
                        <p class="rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-300">
                            처리완료를 누르면
                            {{ detail.restock ? '재고가 복구되고' : '재고는 복구되지 않고' }}
                            <template v-if="detail.type === 'RETURN'">환불이 확정됩니다.</template>
                            <template v-else>교환품이 출고 처리됩니다.</template>
                            되돌릴 수 없습니다.
                        </p>
                        <div v-if="detail.type === 'EXCHANGE'" class="flex flex-wrap gap-2">
                            <select v-model="form.exchange_carrier" :class="inputClass">
                                <option v-for="c in carriers" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                            <input
                                v-model="form.exchange_tracking_no"
                                type="text"
                                placeholder="교환 발송 송장번호"
                                :class="inputClass"
                            >
                        </div>
                        <textarea v-model="form.admin_memo" rows="2" placeholder="처리 메모" :class="[inputClass, 'w-full']" />
                        <button
                            type="button"
                            :class="[btn, 'bg-emerald-500/90 text-neutral-950']"
                            :disabled="form.processing"
                            @click="submit('complete')"
                        >
                            처리완료
                        </button>
                    </div>

                    <div v-else class="text-sm text-neutral-500">
                        <p v-if="detail.status === 'REJECTED'">반려: {{ detail.reject_reason }}</p>
                        <p v-else>{{ detail.completed_at }} 처리 완료</p>
                        <p v-if="selected.handled_by" class="mt-1 text-xs">처리자: {{ selected.handled_by }}</p>
                        <p v-if="detail.admin_memo" class="mt-1 text-xs">메모: {{ detail.admin_memo }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
