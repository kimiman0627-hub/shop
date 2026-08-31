<script setup>
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import OrderSummaryCard from '@/Components/OrderSummaryCard.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    orders: { type: Object, required: true },
});

const cancel = (order) => {
    if (!confirm(`주문 ${order.order_no} 을(를) 취소할까요?`)) {
        return;
    }

    router.delete(`/orders/${order.id}`, { preserveScroll: true });
};
</script>

<template>
    <StoreLayout title="주문 내역">
        <h1 class="text-2xl font-semibold tracking-tight">주문 내역</h1>

        <div v-if="orders.data.length" class="mt-6 space-y-5">
            <OrderSummaryCard v-for="o in orders.data" :key="o.id" :order="o">
                <template v-if="o.is_cancelable || o.is_returnable" #actions>
                    <button
                        v-if="o.is_cancelable"
                        type="button"
                        class="text-sm text-red-600 hover:underline"
                        @click="cancel(o)"
                    >
                        주문 취소
                    </button>
                    <!-- 출고 뒤에는 취소가 아니라 반품·교환이다. -->
                    <Link
                        v-else-if="o.is_returnable"
                        :href="`/returns/orders/${o.id}/create`"
                        class="text-sm text-neutral-600 hover:underline"
                    >
                        반품·교환 신청
                    </Link>
                </template>
            </OrderSummaryCard>
        </div>

        <p v-else class="mt-10 text-neutral-500">주문 내역이 없습니다.</p>

        <Pagination :paginator="orders" />
    </StoreLayout>
</template>
