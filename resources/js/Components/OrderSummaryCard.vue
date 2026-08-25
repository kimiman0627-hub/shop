<script setup>
defineProps({
    order: { type: Object, required: true },
});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;
</script>

<template>
    <div class="rounded-lg border border-neutral-200">
        <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-3">
            <div>
                <p class="font-mono text-sm">{{ order.order_no }}</p>
                <p class="mt-0.5 text-xs text-neutral-500">{{ order.ordered_at }}</p>
            </div>
            <span class="rounded bg-neutral-100 px-2 py-1 text-xs">{{ order.status_label }}</span>
        </div>

        <div class="divide-y divide-neutral-100 px-5">
            <div v-for="item in order.items" :key="item.id" class="flex justify-between py-3 text-sm">
                <div>
                    <p>{{ item.product_name }}</p>
                    <p class="text-neutral-500">
                        <span v-if="item.variant_name">{{ item.variant_name }} · </span>{{ item.quantity }}개
                    </p>
                </div>
                <p>{{ won(item.subtotal) }}</p>
            </div>
        </div>

        <dl class="space-y-1 border-t border-neutral-200 px-5 py-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-neutral-500">상품 합계</dt>
                <dd>{{ won(order.items_total) }}</dd>
            </div>
            <div v-if="order.discount_total > 0" class="flex justify-between text-red-600">
                <dt>쿠폰 할인</dt>
                <dd>−{{ won(order.discount_total) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-neutral-500">배송비</dt>
                <dd>{{ order.shipping_fee === 0 ? '무료' : won(order.shipping_fee) }}</dd>
            </div>
            <div class="flex justify-between border-t border-neutral-200 pt-2 font-medium">
                <dt>총 결제금액</dt>
                <dd>{{ won(order.total_amount) }}</dd>
            </div>
        </dl>

        <div v-if="order.shipment" class="border-t border-neutral-200 bg-sky-50/50 px-5 py-3 text-sm">
            <div class="flex items-center justify-between">
                <p class="font-medium">{{ order.shipment.status_label }}</p>
                <a
                    v-if="order.shipment.tracking_url"
                    :href="order.shipment.tracking_url"
                    target="_blank"
                    rel="noopener"
                    class="rounded border border-neutral-300 bg-white px-3 py-1 text-xs hover:border-neutral-900"
                >
                    배송조회
                </a>
            </div>

            <p class="mt-1 text-neutral-600">
                {{ order.shipment.carrier_name }}
                <span v-if="order.shipment.tracking_no" class="ml-1 font-mono">
                    {{ order.shipment.tracking_no }}
                </span>
            </p>
            <p class="mt-0.5 text-xs text-neutral-500">
                <span v-if="order.shipment.delivered_at">
                    {{ order.shipment.delivered_at }} 배송완료
                </span>
                <span v-else-if="order.shipment.shipped_at">
                    {{ order.shipment.shipped_at }} 출고
                </span>
            </p>
        </div>

        <div class="border-t border-neutral-200 px-5 py-3 text-sm">
            <p class="text-neutral-500">배송지</p>
            <p class="mt-1">{{ order.receiver_name }} · {{ order.receiver_phone }}</p>
            <p class="text-neutral-600">
                ({{ order.postcode }}) {{ order.address1 }} {{ order.address2 }}
            </p>
            <p v-if="order.delivery_memo" class="mt-1 text-neutral-500">
                메모: {{ order.delivery_memo }}
            </p>
        </div>

        <div v-if="$slots.actions" class="border-t border-neutral-200 px-5 py-3">
            <slot name="actions" />
        </div>
    </div>
</template>
