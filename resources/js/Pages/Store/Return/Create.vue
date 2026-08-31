<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { storeInput } from '@/ui';

const props = defineProps({
    order: { type: Object, required: true },
    items: { type: Array, required: true },
    typeOptions: { type: Array, required: true },
    reasonOptions: { type: Array, required: true },
    returnDays: { type: Number, required: true },
    returnShippingFee: { type: Number, required: true },
    existing: { type: Array, required: true },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

// 아직 신청할 수량이 남은 항목만 고를 수 있다.
const available = computed(() => props.items.filter((i) => i.remaining_quantity > 0));

/* 항목별 선택 수량과 교환 옵션. 서버로는 수량 > 0 인 것만 보낸다. */
const lines = reactive(
    Object.fromEntries(
        props.items.map((i) => [i.order_item_id, { quantity: 0, exchange_variant_id: '' }]),
    ),
);

const type = ref('RETURN');

const form = useForm({
    type: 'RETURN',
    reason: 'CHANGE_OF_MIND',
    reason_detail: '',
    items: [],
});

const isCustomerFault = computed(
    () => ['CHANGE_OF_MIND', 'SIZE_OR_COLOR', 'OTHER'].includes(form.reason),
);

const selectedTotal = computed(() =>
    props.items.reduce(
        (sum, i) => sum + i.unit_price * (lines[i.order_item_id]?.quantity ?? 0),
        0,
    ),
);

const hasSelection = computed(() => selectedTotal.value > 0 || props.items.some(
    (i) => (lines[i.order_item_id]?.quantity ?? 0) > 0,
));

const submit = () => {
    form.type = type.value;
    form.items = props.items
        .filter((i) => (lines[i.order_item_id]?.quantity ?? 0) > 0)
        .map((i) => ({
            order_item_id: i.order_item_id,
            quantity: Number(lines[i.order_item_id].quantity),
            exchange_variant_id: type.value === 'EXCHANGE'
                ? (lines[i.order_item_id].exchange_variant_id || null)
                : null,
        }));

    form.post(`/returns/orders/${props.order.id}`);
};

const inputClass = storeInput;
</script>

<template>
    <StoreLayout title="반품·교환 신청">
        <div class="flex items-baseline justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">반품·교환 신청</h1>
            <Link href="/orders" class="text-sm text-neutral-500 hover:underline">주문 내역으로</Link>
        </div>

        <p class="mt-2 text-sm text-neutral-500">
            주문번호 <span class="font-mono">{{ order.order_no }}</span> · {{ order.status_label }}
            <span v-if="order.delivered_at"> · 배송완료 {{ order.delivered_at }}</span>
        </p>

        <p
            v-if="errors.return"
            class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
        >
            {{ errors.return }}
        </p>

        <!-- 이미 걸려 있는 신청 -->
        <div v-if="existing.length" class="mt-5 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm">
            <p class="font-medium">이 주문의 진행 중인 신청</p>
            <ul class="mt-1 space-y-0.5 text-neutral-600">
                <li v-for="r in existing" :key="r.id">
                    {{ r.type_label }} · {{ r.status_label }} · {{ r.items.map((i) => i.product_name).join(', ') }}
                </li>
            </ul>
        </div>

        <p v-if="available.length === 0" class="mt-10 text-neutral-500">
            신청할 수 있는 상품이 없습니다. 모든 상품이 이미 접수되었습니다.
        </p>

        <form v-else class="mt-6 space-y-6" @submit.prevent="submit">
            <!-- 유형 -->
            <div>
                <p class="text-sm font-medium">유형</p>
                <div class="mt-2 flex gap-2">
                    <button
                        v-for="t in typeOptions"
                        :key="t.value"
                        type="button"
                        class="rounded-lg border px-4 py-2 text-sm"
                        :class="type === t.value
                            ? 'border-neutral-900 bg-neutral-900 text-white'
                            : 'border-neutral-300 text-neutral-600'"
                        @click="type = t.value"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <p v-if="type === 'EXCHANGE'" class="mt-2 text-xs text-neutral-500">
                    같은 상품의 다른 옵션으로만 교환할 수 있습니다. 다른 상품을 원하시면 반품 후 다시 주문해 주세요.
                </p>
            </div>

            <!-- 사유 -->
            <div>
                <label class="text-sm font-medium">사유</label>
                <select v-model="form.reason" :class="[inputClass, 'mt-2 block w-full max-w-xs']">
                    <option v-for="r in reasonOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
                <p class="mt-2 text-xs" :class="isCustomerFault ? 'text-amber-700' : 'text-emerald-700'">
                    <template v-if="isCustomerFault">
                        고객 사유는 왕복 배송비 {{ won(returnShippingFee) }}이 환불액에서 차감됩니다.
                    </template>
                    <template v-else>
                        상품 문제로 확인되면 배송비를 부담하지 않으셔도 됩니다. (관리자 확인 후 확정)
                    </template>
                </p>
                <textarea
                    v-model="form.reason_detail"
                    rows="3"
                    placeholder="상세 내용을 적어주시면 처리가 빨라집니다."
                    :class="[inputClass, 'mt-3 block w-full']"
                />
            </div>

            <!-- 상품 선택 -->
            <div>
                <p class="text-sm font-medium">상품 선택</p>
                <div class="mt-2 divide-y divide-neutral-200 rounded-lg border border-neutral-200">
                    <div v-for="item in items" :key="item.order_item_id" class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm">
                                    {{ item.product_name }}
                                    <span v-if="item.variant_name" class="text-neutral-500">
                                        / {{ item.variant_name }}
                                    </span>
                                </p>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ won(item.unit_price) }} · 주문 {{ item.ordered_quantity }}개 ·
                                    신청 가능 {{ item.remaining_quantity }}개
                                </p>
                            </div>

                            <input
                                v-model.number="lines[item.order_item_id].quantity"
                                type="number"
                                min="0"
                                :max="item.remaining_quantity"
                                :disabled="item.remaining_quantity === 0"
                                :class="[inputClass, 'w-20 text-right disabled:bg-neutral-100']"
                            >
                        </div>

                        <div
                            v-if="type === 'EXCHANGE' && lines[item.order_item_id].quantity > 0"
                            class="mt-3"
                        >
                            <label class="text-xs text-neutral-500">교환받을 옵션</label>
                            <select
                                v-model="lines[item.order_item_id].exchange_variant_id"
                                :class="[inputClass, 'mt-1 block w-full max-w-sm']"
                            >
                                <option value="">선택하세요</option>
                                <option
                                    v-for="o in item.exchange_options"
                                    :key="o.id"
                                    :value="o.id"
                                    :disabled="o.available === 0"
                                >
                                    {{ o.name }}{{ o.available === 0 ? ' (품절)' : ` (재고 ${o.available})` }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 합계 -->
            <div class="rounded-lg border border-neutral-200 p-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-neutral-500">선택 상품 금액</span>
                    <span>{{ won(selectedTotal) }}</span>
                </div>
                <p class="mt-2 text-xs text-neutral-500">
                    실제 환불액은 쿠폰 할인 안분과 배송비를 반영해 관리자 승인 시 확정됩니다.
                    신청 기한은 배송완료일로부터 {{ returnDays }}일입니다.
                </p>
            </div>

            <button
                type="submit"
                class="rounded-lg bg-neutral-900 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-40"
                :disabled="form.processing || !hasSelection"
            >
                신청하기
            </button>
        </form>
    </StoreLayout>
</template>
