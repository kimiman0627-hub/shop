<script setup>
import { useForm } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

const form = useForm({ order_no: '', password: '' });

const submit = () => form.post('/orders/lookup', {
    onFinish: () => form.reset('password'),
});

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900';
</script>

<template>
    <StoreLayout title="비회원 주문조회">
        <div class="mx-auto max-w-sm">
            <h1 class="text-2xl font-semibold tracking-tight">비회원 주문조회</h1>
            <p class="mt-2 text-sm text-neutral-600">
                주문 시 입력한 주문번호와 비밀번호로 조회합니다.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm text-neutral-600">주문번호</label>
                    <input v-model="form.order_no" type="text" placeholder="ORD20260820XXXXXX" :class="inputClass">
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">비밀번호</label>
                    <input v-model="form.password" type="password" :class="inputClass">
                </div>

                <p v-if="form.errors.order_no" class="text-sm text-red-600">{{ form.errors.order_no }}</p>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white disabled:opacity-50"
                >
                    조회
                </button>
            </form>
        </div>
    </StoreLayout>
</template>
