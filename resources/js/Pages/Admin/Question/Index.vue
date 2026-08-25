<script setup>
import { reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    questions: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
    statusOptions: { type: Array, required: true },
});

const search = reactive({
    status: props.filters.status ?? '',
    keyword: props.filters.keyword ?? '',
});

const apply = () => router.get('/admin/questions', { ...search }, { preserveState: true, replace: true });

const answeringId = ref(null);
const answerForm = useForm({ answer: '' });

const openAnswer = (row) => {
    answeringId.value = answeringId.value === row.id ? null : row.id;
    answerForm.answer = row.answer ?? '';
    answerForm.clearErrors();
};

const submitAnswer = (row) => answerForm.put(`/admin/questions/${row.id}/answer`, {
    preserveScroll: true,
    onSuccess: () => { answeringId.value = null; },
});

const inputClass = 'rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="상품문의">
        <h2 class="text-xl font-semibold tracking-tight">상품문의</h2>
        <p class="mt-1 text-sm text-neutral-500">
            상품 페이지에 공개되는 Q&amp;A입니다. 주문·배송 관련 개별 문의는 <strong>회원관리 &gt; 1:1문의</strong>에서 처리합니다.
        </p>

        <div class="mt-5 flex flex-wrap gap-2">
            <button
                v-for="s in statusOptions"
                :key="s.value"
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :class="search.status === s.value
                    ? 'border-neutral-300 bg-neutral-100 text-neutral-900'
                    : 'border-neutral-700 text-neutral-400 hover:text-neutral-200'"
                @click="search.status = search.status === s.value ? '' : s.value; apply()"
            >
                {{ s.label }}
                <span class="ml-1 text-neutral-500">{{ counts[s.value] ?? 0 }}</span>
            </button>
        </div>

        <form class="mt-4 flex flex-wrap gap-2" @submit.prevent="apply">
            <input v-model="search.keyword" type="text" placeholder="상품명 · 문의 내용" :class="inputClass">
            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
        </form>

        <div class="mt-6 space-y-3">
            <article v-for="row in questions.data" :key="row.id" class="rounded-xl border border-neutral-800 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium">{{ row.product_name }}</p>
                        <p class="mt-1 flex items-center gap-2 text-xs text-neutral-500">
                            <span
                                class="rounded px-2 py-0.5"
                                :class="row.status === 'ANSWERED'
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : 'bg-amber-500/15 text-amber-300'"
                            >{{ row.status_label }}</span>
                            <span v-if="row.is_secret" title="비밀글">🔒</span>
                            {{ row.author }} · {{ row.created_at }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-lg border border-neutral-700 px-3 py-1.5 text-xs text-neutral-300"
                        @click="openAnswer(row)"
                    >
                        {{ row.answer ? '답변 수정' : '답변' }}
                    </button>
                </div>

                <!-- 관리자는 비밀글도 본다. 내용을 봐야 답변할 수 있다. -->
                <p class="mt-3 whitespace-pre-line text-sm text-neutral-200">{{ row.content }}</p>

                <div v-if="row.answer && answeringId !== row.id" class="mt-3 rounded-lg bg-neutral-900/60 px-4 py-3">
                    <p class="text-xs text-neutral-500">
                        답변<span v-if="row.answered_by"> · {{ row.answered_by }}</span>
                        <span v-if="row.answered_at"> · {{ row.answered_at }}</span>
                    </p>
                    <p class="mt-1 whitespace-pre-line text-sm text-neutral-300">{{ row.answer }}</p>
                </div>

                <form v-if="answeringId === row.id" class="mt-3" @submit.prevent="submitAnswer(row)">
                    <textarea
                        v-model="answerForm.answer"
                        rows="3"
                        placeholder="답변을 입력하세요. 비밀글이 아니면 상품 페이지에 공개됩니다."
                        :class="[inputClass, 'w-full']"
                    />
                    <p v-if="answerForm.errors.answer" class="mt-1 text-xs text-rose-400">
                        {{ answerForm.errors.answer }}
                    </p>
                    <div class="mt-2 flex gap-2">
                        <button
                            type="submit"
                            :disabled="answerForm.processing"
                            class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-40"
                        >
                            등록
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-700 px-3 py-2 text-sm text-neutral-400"
                            @click="answeringId = null"
                        >
                            취소
                        </button>
                    </div>
                </form>
            </article>

            <p v-if="questions.data.length === 0" class="rounded-xl border border-neutral-800 px-5 py-10 text-center text-neutral-500">
                해당하는 문의가 없습니다.
            </p>
        </div>
    </AdminLayout>
</template>
