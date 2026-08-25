<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

const props = defineProps({
    cart: { type: Object, required: true },
    shipping_fee: { type: Number, required: true },
});

const errors = computed(() => usePage().props.errors ?? {});

// 구매는 회원 전용이다. 누르면 로그인으로 갔다가 주문서로 돌아오지만, 미리 알려준다.
const isGuest = computed(() => !usePage().props.auth.user);

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const total = computed(() => props.cart.items_total + props.shipping_fee);

const changeQuantity = (item, next) => {
    if (next < 1) {
        return;
    }

    router.put(`/cart/items/${item.id}`, { quantity: next }, { preserveScroll: true });
};

const remove = (item) => router.delete(`/cart/items/${item.id}`, { preserveScroll: true });

const clear = () => {
    if (!confirm('장바구니를 비울까요?')) {
        return;
    }

    router.delete('/cart');
};
</script>

<template>
    <StoreLayout title="장바구니">
        <div class="flex items-baseline justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">장바구니</h1>
            <button
                v-if="cart.items.length"
                type="button"
                class="text-sm text-neutral-500 hover:text-neutral-900"
                @click="clear"
            >
                비우기
            </button>
        </div>

        <p v-if="errors.quantity" class="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">
            {{ errors.quantity }}
        </p>

        <div v-if="cart.items.length === 0" class="mt-10 text-center">
            <p class="text-neutral-500">장바구니가 비어 있습니다.</p>
            <Link href="/products" class="mt-4 inline-block rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white">
                상품 보러 가기
            </Link>
        </div>

        <div v-else class="mt-6 grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div class="divide-y divide-neutral-200 border-y border-neutral-200">
                <div v-for="item in cart.items" :key="item.id" class="flex gap-4 py-4">
                    <Link :href="`/products/${encodeURIComponent(item.product_slug)}`" class="shrink-0">
                        <div class="h-24 w-24 overflow-hidden rounded bg-neutral-100">
                            <img
                                v-if="item.thumbnail_url"
                                :src="item.thumbnail_url"
                                :alt="item.product_name"
                                class="h-full w-full object-cover"
                            >
                        </div>
                    </Link>

                    <div class="min-w-0 flex-1">
                        <Link
                            :href="`/products/${encodeURIComponent(item.product_slug)}`"
                            class="font-medium hover:underline"
                        >
                            {{ item.product_name }}
                        </Link>
                        <p v-if="item.option_label" class="mt-0.5 text-sm text-neutral-500">
                            {{ item.option_label }}
                        </p>

                        <p v-if="!item.purchasable" class="mt-1 text-sm text-red-600">
                            품절되었습니다. 주문하려면 삭제해 주세요.
                        </p>
                        <p v-else-if="item.exceeds_stock" class="mt-1 text-sm text-amber-700">
                            재고가 {{ item.available }}개 남았습니다. 수량을 줄여주세요.
                        </p>

                        <div class="mt-3 flex items-center gap-2">
                            <button
                                type="button"
                                class="h-7 w-7 rounded border border-neutral-300 text-sm disabled:opacity-30"
                                :disabled="item.quantity <= 1"
                                @click="changeQuantity(item, item.quantity - 1)"
                            >
                                −
                            </button>
                            <span class="w-8 text-center text-sm">{{ item.quantity }}</span>
                            <button
                                type="button"
                                class="h-7 w-7 rounded border border-neutral-300 text-sm"
                                @click="changeQuantity(item, item.quantity + 1)"
                            >
                                +
                            </button>

                            <button
                                type="button"
                                class="ml-4 text-sm text-neutral-500 hover:text-red-600"
                                @click="remove(item)"
                            >
                                삭제
                            </button>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="font-medium">{{ won(item.subtotal) }}</p>
                        <p class="mt-0.5 text-xs text-neutral-500">{{ won(item.unit_price) }} / 개</p>
                    </div>
                </div>
            </div>

            <aside class="h-fit rounded-lg border border-neutral-200 p-5">
                <p class="font-medium">결제 예정 금액</p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">상품 합계</dt>
                        <dd>{{ won(cart.items_total) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">배송비</dt>
                        <dd>{{ shipping_fee === 0 ? '무료' : won(shipping_fee) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex justify-between border-t border-neutral-200 pt-4">
                    <span class="font-medium">총 결제금액</span>
                    <span class="text-lg font-semibold">{{ won(total) }}</span>
                </div>

                <Link
                    v-if="!cart.has_issue"
                    href="/orders/checkout"
                    class="mt-5 block w-full rounded-lg bg-neutral-900 px-4 py-3 text-center text-sm font-medium text-white"
                >
                    주문하기
                </Link>
                <button
                    v-else
                    type="button"
                    disabled
                    class="mt-5 w-full cursor-not-allowed rounded-lg bg-neutral-300 px-4 py-3 text-sm font-medium text-white"
                >
                    주문하기
                </button>

                <p v-if="cart.has_issue" class="mt-2 text-center text-xs text-red-600">
                    품절되었거나 재고가 부족한 항목이 있습니다.
                </p>

                <p v-else-if="isGuest" class="mt-2 text-center text-xs text-neutral-500">
                    주문하려면 로그인이 필요합니다. 담아두신 상품은 그대로 유지됩니다.
                </p>
            </aside>
        </div>
    </StoreLayout>
</template>
