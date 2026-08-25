<script setup>
import { Link } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import OrderSummaryCard from '@/Components/OrderSummaryCard.vue';

defineProps({
    order: { type: Object, required: true },
    deposit: { type: Object, default: null },
});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const copy = (text) => navigator.clipboard?.writeText(text);
</script>

<template>
    <StoreLayout title="주문 완료">
        <div class="mx-auto max-w-2xl">
            <div class="text-center">
                <h1 class="text-2xl font-semibold tracking-tight">주문이 접수되었습니다</h1>
                <p class="mt-2 text-neutral-600">
                    주문번호 <span class="font-mono font-medium">{{ order.order_no }}</span>
                </p>
                <p v-if="order.is_guest" class="mt-1 text-sm text-neutral-500">
                    비회원 주문입니다. 주문번호와 조회 비밀번호를 보관하세요.
                </p>
            </div>

            <div v-if="deposit" class="mt-8 rounded-lg border-2 border-neutral-900 p-5">
                <p class="font-medium">입금 계좌</p>
                <p class="mt-1 text-sm text-neutral-600">
                    아래 계좌로 입금해 주세요. 입금이 확인되면 상품을 준비합니다.
                </p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">은행</dt>
                        <dd class="font-medium">{{ deposit.bank_name }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-neutral-500">계좌번호</dt>
                        <dd class="flex items-center gap-2">
                            <span class="font-mono font-medium">{{ deposit.account_number }}</span>
                            <button
                                type="button"
                                class="rounded border border-neutral-300 px-2 py-0.5 text-xs text-neutral-600"
                                @click="copy(deposit.account_number)"
                            >
                                복사
                            </button>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">예금주</dt>
                        <dd class="font-medium">{{ deposit.holder_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">입금자명</dt>
                        <dd class="font-medium">{{ deposit.depositor_name }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-neutral-200 pt-2">
                        <dt class="text-neutral-500">입금액</dt>
                        <dd class="text-lg font-semibold">{{ won(deposit.amount) }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs text-neutral-500">
                    입금자명이 다르면 확인이 늦어질 수 있습니다.
                    안내 문자를 받지 못하셨다면 이 화면의 계좌로 입금하시면 됩니다.
                </p>
            </div>

            <div class="mt-8">
                <OrderSummaryCard :order="order" />
            </div>

            <div class="mt-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                입금 전까지 재고가 예약된 상태입니다.
                기한 내 입금하지 않으면 주문이 자동 취소되고 예약이 해제됩니다.
            </div>

            <div class="mt-6 flex justify-center gap-3">
                <Link href="/products" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm">
                    쇼핑 계속하기
                </Link>
                <Link
                    v-if="!order.is_guest"
                    href="/orders"
                    class="rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white"
                >
                    주문 내역
                </Link>
            </div>
        </div>
    </StoreLayout>
</template>
