<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { storeField } from '@/ui';

defineProps({
    inquiries: { type: Array, required: true },
    orderOptions: { type: Array, required: true },
    categoryOptions: { type: Array, required: true },
});

const showForm = ref(false);

const form = useForm({
    category: 'ORDER',
    title: '',
    content: '',
    order_id: null,
});

const submit = () => form.post('/inquiries', {
    onSuccess: () => {
        form.reset();
        showForm.value = false;
    },
});

const inputClass = storeField;
</script>

<template>
    <StoreLayout title="1:1 문의">
        <div class="flex items-baseline justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">1:1 문의</h1>
            <button
                type="button"
                class="rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white"
                @click="showForm = !showForm"
            >
                {{ showForm ? '취소' : '문의하기' }}
            </button>
        </div>

        <form v-if="showForm" class="mt-6 space-y-4 rounded-lg border border-neutral-200 p-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm text-neutral-600">문의 유형</label>
                    <select v-model="form.category" :class="inputClass">
                        <option v-for="c in categoryOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">관련 주문 (선택)</label>
                    <select v-model="form.order_id" :class="inputClass">
                        <option :value="null">선택 안 함</option>
                        <option v-for="o in orderOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
                    </select>
                    <p v-if="form.errors.order_id" class="mt-1 text-xs text-red-600">{{ form.errors.order_id }}</p>
                </div>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">제목</label>
                <input v-model="form.title" type="text" :class="inputClass">
                <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">내용</label>
                <textarea v-model="form.content" rows="5" :class="inputClass" />
                <p v-if="form.errors.content" class="mt-1 text-xs text-red-600">{{ form.errors.content }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white disabled:opacity-50"
            >
                접수
            </button>
        </form>

        <div v-if="inquiries.length" class="mt-8 space-y-4">
            <div v-for="q in inquiries" :key="q.id" class="rounded-lg border border-neutral-200 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium">{{ q.title }}</p>
                        <p class="mt-0.5 text-sm text-neutral-500">
                            {{ q.category_label }}
                            <span v-if="q.order_no"> · {{ q.order_no }}</span>
                            · {{ q.created_at }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded px-2 py-0.5 text-xs"
                        :class="q.status === 'PENDING'
                            ? 'bg-amber-100 text-amber-800'
                            : 'bg-emerald-100 text-emerald-800'"
                    >{{ q.status_label }}</span>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm text-neutral-700">{{ q.content }}</p>

                <div v-if="q.answer" class="mt-4 rounded-lg bg-neutral-50 p-4">
                    <p class="text-xs text-neutral-500">답변 · {{ q.answered_at }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ q.answer }}</p>
                </div>
            </div>
        </div>

        <p v-else class="mt-10 text-neutral-500">문의 내역이 없습니다.</p>
    </StoreLayout>
</template>
