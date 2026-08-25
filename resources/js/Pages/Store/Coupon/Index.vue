<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    coupons: { type: Array, required: true },
});

const errors = computed(() => usePage().props.errors ?? {});

const form = useForm({ code: '' });

const submit = () => form.post('/coupons/redeem', {
    onSuccess: () => form.reset(),
});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;
</script>

<template>
    <StoreLayout title="내 쿠폰">
        <h1 class="text-2xl font-semibold tracking-tight">내 쿠폰</h1>

        <form class="mt-6 flex max-w-md gap-2" @submit.prevent="submit">
            <input
                v-model="form.code"
                type="text"
                placeholder="쿠폰 코드 입력"
                class="flex-1 rounded-lg border border-neutral-300 px-3 py-2 text-sm uppercase outline-none focus:border-neutral-900"
            >
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
                등록
            </button>
        </form>

        <p v-if="errors.code" class="mt-2 text-sm text-red-600">{{ errors.code }}</p>

        <div v-if="coupons.length" class="mt-8 grid gap-3 sm:grid-cols-2">
            <div
                v-for="c in coupons"
                :key="c.id"
                class="rounded-lg border p-4"
                :class="c.usable ? 'border-neutral-300' : 'border-neutral-200 bg-neutral-50'"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium" :class="c.usable ? '' : 'text-neutral-400'">{{ c.name }}</p>
                        <p class="mt-1 text-lg font-semibold" :class="c.usable ? '' : 'text-neutral-400'">
                            {{ c.discount_label }}
                        </p>
                    </div>

                    <span
                        v-if="c.used"
                        class="shrink-0 rounded bg-neutral-200 px-2 py-0.5 text-xs text-neutral-600"
                    >사용완료</span>
                    <span
                        v-else-if="c.expired"
                        class="shrink-0 rounded bg-red-100 px-2 py-0.5 text-xs text-red-700"
                    >기간만료</span>
                </div>

                <p v-if="c.min_order_amount > 0" class="mt-3 text-xs text-neutral-500">
                    {{ won(c.min_order_amount) }} 이상 주문 시 사용 가능
                </p>
                <p class="mt-1 text-xs text-neutral-500">~{{ c.expires_at }} 까지</p>
            </div>
        </div>

        <p v-else class="mt-8 text-neutral-500">보유한 쿠폰이 없습니다.</p>
    </StoreLayout>
</template>
