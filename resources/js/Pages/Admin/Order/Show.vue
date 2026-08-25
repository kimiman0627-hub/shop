<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    order: { type: Object, required: true },
});

const errors = computed(() => usePage().props.errors ?? {});
const cancelMemo = ref('');

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const cancel = () => {
    if (!cancelMemo.value.trim()) {
        alert('취소 사유를 입력하세요.');
        return;
    }

    if (!confirm(
        `주문 ${props.order.order_no} 을(를) 취소할까요?\n\n`
        + `재고 예약이 해제되고, 결제완료 건이면 실물 재고가 복구됩니다.\n`
        + `사유: ${cancelMemo.value}`,
    )) {
        return;
    }

    router.put(`/admin/orders/${props.order.id}/cancel`, { memo: cancelMemo.value });
};

const sectionClass = 'rounded-lg border border-neutral-800 p-4';
</script>

<template>
    <AdminLayout :title="`주문 ${order.order_no}`">
        <Link href="/admin/orders" class="text-sm text-neutral-500 hover:text-neutral-300">
            &larr; 주문 목록
        </Link>

        <div class="mt-4 flex items-center gap-3">
            <h2 class="font-mono text-xl font-semibold">{{ order.order_no }}</h2>
            <span class="rounded bg-neutral-800 px-2 py-0.5 text-sm">{{ order.status_label }}</span>
            <span v-if="order.is_guest" class="rounded bg-neutral-800 px-2 py-0.5 text-xs text-neutral-400">
                비회원
            </span>
        </div>

        <p
            v-if="errors.general"
            class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
        >
            {{ errors.general }}
        </p>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <!-- 주문 정보 -->
            <section :class="sectionClass">
                <p class="text-sm font-medium">주문 정보</p>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">주문일시</dt>
                        <dd>{{ order.ordered_at }}</dd>
                    </div>
                    <div v-if="order.payment_due_at" class="flex justify-between">
                        <dt class="text-neutral-500">결제기한</dt>
                        <dd>{{ order.payment_due_at }}</dd>
                    </div>
                    <div v-if="order.paid_at" class="flex justify-between">
                        <dt class="text-neutral-500">결제일시</dt>
                        <dd>{{ order.paid_at }}</dd>
                    </div>
                    <div v-if="order.canceled_at" class="flex justify-between">
                        <dt class="text-neutral-500">취소일시</dt>
                        <dd class="text-red-400">{{ order.canceled_at }}</dd>
                    </div>
                    <div v-if="order.stock_released_at" class="flex justify-between">
                        <dt class="text-neutral-500">예약해제</dt>
                        <dd class="text-neutral-400">{{ order.stock_released_at }}</dd>
                    </div>
                </dl>
            </section>

            <!-- 주문자 · 배송지 -->
            <section :class="sectionClass">
                <p class="text-sm font-medium">주문자 · 배송지</p>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">주문자</dt>
                        <dd>{{ order.orderer_name }} · {{ order.orderer_phone }}</dd>
                    </div>
                    <div v-if="order.orderer_email" class="flex justify-between">
                        <dt class="text-neutral-500">이메일</dt>
                        <dd>{{ order.orderer_email }}</dd>
                    </div>
                    <div v-if="order.customer" class="flex justify-between">
                        <dt class="text-neutral-500">회원</dt>
                        <dd>{{ order.customer.name }} ({{ order.customer.email }})</dd>
                    </div>
                    <div class="flex justify-between border-t border-neutral-800 pt-1.5">
                        <dt class="text-neutral-500">수령인</dt>
                        <dd>{{ order.receiver_name }} · {{ order.receiver_phone }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="shrink-0 text-neutral-500">주소</dt>
                        <dd class="ml-4 text-right">
                            ({{ order.postcode }}) {{ order.address1 }} {{ order.address2 }}
                        </dd>
                    </div>
                    <div v-if="order.delivery_memo" class="flex justify-between">
                        <dt class="text-neutral-500">배송메모</dt>
                        <dd class="text-amber-300">{{ order.delivery_memo }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- 주문 상품 -->
        <section class="mt-4" :class="sectionClass">
            <p class="text-sm font-medium">
                주문 상품
                <span class="ml-1 text-xs text-neutral-600">(주문 시점 스냅샷)</span>
            </p>

            <table class="mt-3 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-1.5 font-medium">상품</th>
                        <th class="py-1.5 font-medium">SKU</th>
                        <th class="py-1.5 text-right font-medium">단가</th>
                        <th class="py-1.5 text-center font-medium">수량</th>
                        <th class="py-1.5 text-right font-medium">합계</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in order.items" :key="item.id" class="border-b border-neutral-900">
                        <td class="py-2">
                            {{ item.product_name }}
                            <span v-if="item.variant_name" class="text-neutral-500">· {{ item.variant_name }}</span>
                        </td>
                        <td class="py-2 font-mono text-xs text-neutral-500">{{ item.sku }}</td>
                        <td class="py-2 text-right">{{ won(item.unit_price) }}</td>
                        <td class="py-2 text-center">{{ item.quantity }}</td>
                        <td class="py-2 text-right">{{ won(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>

            <dl class="mt-4 ml-auto max-w-xs space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <dt class="text-neutral-500">상품 합계</dt>
                    <dd>{{ won(order.items_total) }}</dd>
                </div>
                <div v-if="order.discount_total > 0" class="flex justify-between text-red-400">
                    <dt>쿠폰 할인<span v-if="order.coupon"> ({{ order.coupon.name }})</span></dt>
                    <dd>−{{ won(order.discount_total) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">배송비</dt>
                    <dd>{{ order.shipping_fee === 0 ? '무료' : won(order.shipping_fee) }}</dd>
                </div>
                <div class="flex justify-between border-t border-neutral-800 pt-1.5 font-medium">
                    <dt>총 결제금액</dt>
                    <dd>{{ won(order.total_amount) }}</dd>
                </div>
            </dl>
        </section>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <!-- 결제 이력 -->
            <section :class="sectionClass">
                <p class="text-sm font-medium">결제 이력</p>

                <div v-if="order.payments.length" class="mt-3 space-y-3">
                    <div
                        v-for="p in order.payments"
                        :key="p.id"
                        class="border-b border-neutral-900 pb-3 text-sm last:border-0 last:pb-0"
                    >
                        <div class="flex items-center justify-between">
                            <span>{{ p.method_label }}</span>
                            <span
                                class="rounded px-1.5 py-0.5 text-xs"
                                :class="p.status === 'PAID'
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : p.status === 'READY'
                                        ? 'bg-amber-500/15 text-amber-300'
                                        : 'bg-neutral-700/40 text-neutral-400'"
                            >{{ p.status_label }}</span>
                        </div>
                        <p class="mt-1 text-neutral-400">{{ won(p.amount) }}</p>
                        <p v-if="p.account_label" class="mt-0.5 text-xs text-neutral-500">
                            {{ p.account_label }}
                            <span v-if="p.depositor_name"> · 입금자 {{ p.depositor_name }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-600">
                            요청 {{ p.requested_at }}
                            <span v-if="p.paid_at"> · 완료 {{ p.paid_at }}</span>
                            <span v-if="p.confirmed_by"> · 처리 {{ p.confirmed_by }}</span>
                        </p>
                        <p v-if="p.memo" class="mt-0.5 text-xs text-neutral-500">{{ p.memo }}</p>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-neutral-500">결제 이력이 없습니다.</p>
            </section>

            <!-- 배송 -->
            <section :class="sectionClass">
                <p class="text-sm font-medium">배송</p>

                <div v-if="order.shipment" class="mt-3 text-sm">
                    <p>{{ order.shipment.status_label }}</p>
                    <p class="mt-1 text-neutral-400">
                        {{ order.shipment.carrier_name }}
                        <a
                            v-if="order.shipment.tracking_url"
                            :href="order.shipment.tracking_url"
                            target="_blank"
                            rel="noopener"
                            class="ml-1 font-mono text-sky-300 hover:underline"
                        >{{ order.shipment.tracking_no }}</a>
                        <span v-else-if="order.shipment.tracking_no" class="ml-1 font-mono">
                            {{ order.shipment.tracking_no }}
                        </span>
                    </p>
                    <p class="mt-0.5 text-xs text-neutral-600">
                        <span v-if="order.shipment.shipped_at">출고 {{ order.shipment.shipped_at }}</span>
                        <span v-if="order.shipment.delivered_at"> · 완료 {{ order.shipment.delivered_at }}</span>
                    </p>
                </div>

                <p v-else class="mt-3 text-sm text-neutral-500">아직 출고되지 않았습니다.</p>
            </section>
        </div>

        <!-- 재고 이력 -->
        <section v-if="order.stock_movements.length" class="mt-4" :class="sectionClass">
            <p class="text-sm font-medium">
                재고 변동
                <span class="ml-1 text-xs text-neutral-600">(이 주문으로 인한 변동만)</span>
            </p>

            <table class="mt-3 w-full max-w-2xl text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-1.5 font-medium">구분</th>
                        <th class="py-1.5 font-medium">SKU</th>
                        <th class="py-1.5 text-right font-medium">실물</th>
                        <th class="py-1.5 text-right font-medium">예약</th>
                        <th class="py-1.5 font-medium">시각</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(m, i) in order.stock_movements" :key="i" class="border-b border-neutral-900">
                        <td class="py-1.5">{{ m.type_label }}</td>
                        <td class="py-1.5 font-mono text-xs">{{ m.sku }}</td>
                        <td class="py-1.5 text-right" :class="m.stock_delta < 0 ? 'text-red-400' : m.stock_delta > 0 ? 'text-emerald-400' : 'text-neutral-600'">
                            {{ m.stock_delta > 0 ? '+' : '' }}{{ m.stock_delta }}
                        </td>
                        <td class="py-1.5 text-right" :class="m.reserved_delta < 0 ? 'text-red-400' : m.reserved_delta > 0 ? 'text-amber-400' : 'text-neutral-600'">
                            {{ m.reserved_delta > 0 ? '+' : '' }}{{ m.reserved_delta }}
                        </td>
                        <td class="py-1.5 text-neutral-500">{{ m.created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- 강제 취소 -->
        <section v-if="order.is_cancelable_by_admin" class="mt-4 max-w-xl rounded-lg border border-red-500/30 p-4">
            <p class="text-sm font-medium text-red-300">주문 취소</p>
            <p class="mt-1 text-xs text-neutral-500">
                재고 예약이 해제되고, 결제완료 건이면 실물 재고가 복구됩니다.
                쿠폰을 썼다면 되살아납니다(만료된 쿠폰 제외).
            </p>

            <input
                v-model="cancelMemo"
                type="text"
                placeholder="취소 사유 (필수)"
                class="mt-3 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400"
            >
            <p v-if="errors.memo" class="mt-1 text-xs text-red-400">{{ errors.memo }}</p>

            <button
                type="button"
                class="mt-3 rounded-lg border border-red-500/40 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10"
                @click="cancel"
            >
                주문 취소
            </button>
        </section>

        <p
            v-else-if="order.status === 'CANCELED' || order.status === 'REFUNDED'"
            class="mt-4 max-w-xl rounded-lg border border-neutral-800 px-4 py-3 text-sm text-neutral-500"
        >
            이미 {{ order.status_label }}된 주문입니다.
        </p>

        <p v-else class="mt-4 max-w-xl rounded-lg border border-neutral-800 px-4 py-3 text-sm text-neutral-500">
            {{ order.status_label }} 상태입니다. 출고 이후의 주문은 취소가 아니라 반품·환불로 처리해야 합니다.
        </p>
    </AdminLayout>
</template>
