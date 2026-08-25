<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    returns: { type: Array, required: true },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const cancel = (item) => {
    if (!confirm(`${item.type_label} 신청을 취소할까요?`)) {
        return;
    }

    router.delete(`/returns/${item.id}`, { preserveScroll: true });
};

// 고객에게는 진행 단계만 보여준다. 재고·귀책 같은 내부 판단은 노출하지 않는다.
const steps = ['REQUESTED', 'APPROVED', 'PICKING', 'RECEIVED', 'COMPLETED'];

const stepIndex = (status) => steps.indexOf(status);

const tone = {
    REQUESTED: 'bg-amber-100 text-amber-800',
    APPROVED: 'bg-sky-100 text-sky-800',
    PICKING: 'bg-indigo-100 text-indigo-800',
    RECEIVED: 'bg-violet-100 text-violet-800',
    COMPLETED: 'bg-emerald-100 text-emerald-800',
    REJECTED: 'bg-neutral-200 text-neutral-700',
};
</script>

<template>
    <StoreLayout title="반품·교환 내역">
        <h1 class="text-2xl font-semibold tracking-tight">반품·교환 내역</h1>

        <p
            v-if="errors.return"
            class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
        >
            {{ errors.return }}
        </p>

        <div v-if="returns.length" class="mt-6 space-y-4">
            <article v-for="r in returns" :key="r.id" class="rounded-xl border border-neutral-200 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium">
                            {{ r.type_label }}
                            <span class="ml-2 rounded px-2 py-0.5 text-xs" :class="tone[r.status]">
                                {{ r.status_label }}
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-neutral-500">
                            주문 <span class="font-mono">{{ r.order_no }}</span> · {{ r.requested_at }} 접수
                        </p>
                    </div>

                    <button
                        v-if="r.is_cancelable_by_customer"
                        type="button"
                        class="text-sm text-red-600 hover:underline"
                        @click="cancel(r)"
                    >
                        신청 취소
                    </button>
                </div>

                <ul class="mt-3 space-y-1 text-sm text-neutral-700">
                    <li v-for="line in r.items" :key="line.id">
                        {{ line.product_name }}
                        <span v-if="line.variant_name" class="text-neutral-500">/ {{ line.variant_name }}</span>
                        × {{ line.quantity }}
                        <span v-if="line.exchange_variant_name" class="text-neutral-500">
                            → {{ line.exchange_variant_name }}
                        </span>
                    </li>
                </ul>

                <p class="mt-2 text-xs text-neutral-500">사유: {{ r.reason_label }}</p>

                <!-- 진행 단계 -->
                <div v-if="r.status !== 'REJECTED'" class="mt-4 flex items-center gap-1">
                    <template v-for="(s, idx) in steps" :key="s">
                        <span
                            class="h-1.5 flex-1 rounded-full"
                            :class="idx <= stepIndex(r.status) ? 'bg-neutral-900' : 'bg-neutral-200'"
                        />
                    </template>
                </div>

                <p v-if="r.status === 'REJECTED'" class="mt-3 rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-700">
                    {{ r.reject_reason }}
                </p>

                <!-- 반품은 금액이, 교환은 송장이 관심사다 -->
                <div v-else-if="r.type === 'RETURN' && r.status !== 'REQUESTED'" class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between text-neutral-500">
                        <span>상품 금액</span><span>{{ won(r.items_refund) }}</span>
                    </div>
                    <div v-if="r.coupon_deduction > 0" class="flex justify-between text-neutral-500">
                        <span>쿠폰 할인 차감</span><span>- {{ won(r.coupon_deduction) }}</span>
                    </div>
                    <div v-if="r.shipping_deduction > 0" class="flex justify-between text-neutral-500">
                        <span>반품 배송비</span><span>- {{ won(r.shipping_deduction) }}</span>
                    </div>
                    <div v-if="r.shipping_refund > 0" class="flex justify-between text-neutral-500">
                        <span>배송비 환불</span><span>+ {{ won(r.shipping_refund) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-neutral-200 pt-1 font-medium">
                        <span>{{ r.status === 'COMPLETED' ? '환불 완료' : '환불 예정' }}</span>
                        <span>{{ won(r.refund_amount) }}</span>
                    </div>
                </div>

                <div v-if="r.pickup || r.exchange" class="mt-3 space-y-1 text-xs text-neutral-500">
                    <p v-if="r.pickup">
                        회수: {{ r.pickup.carrier_name }}
                        <a
                            v-if="r.pickup.tracking_url"
                            :href="r.pickup.tracking_url"
                            target="_blank"
                            class="font-mono underline"
                        >{{ r.pickup.tracking_no }}</a>
                        <span v-else class="font-mono">{{ r.pickup.tracking_no ?? '' }}</span>
                    </p>
                    <p v-if="r.exchange">
                        교환 발송: {{ r.exchange.carrier_name }}
                        <a
                            v-if="r.exchange.tracking_url"
                            :href="r.exchange.tracking_url"
                            target="_blank"
                            class="font-mono underline"
                        >{{ r.exchange.tracking_no }}</a>
                        <span v-else class="font-mono">{{ r.exchange.tracking_no ?? '' }}</span>
                    </p>
                </div>
            </article>
        </div>

        <div v-else class="mt-10 text-neutral-500">
            <p>반품·교환 신청 내역이 없습니다.</p>
            <Link href="/orders" class="mt-2 inline-block text-sm text-neutral-900 underline">주문 내역 보기</Link>
        </div>
    </StoreLayout>
</template>
