<script setup>
import { reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StarRating from '@/Components/StarRating.vue';
import { adminInput } from '@/ui';

const props = defineProps({
    reviews: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
    statusOptions: { type: Array, required: true },
});

const search = reactive({
    status: props.filters.status ?? '',
    rating: props.filters.rating ?? '',
    keyword: props.filters.keyword ?? '',
});

const apply = () => router.get('/admin/reviews', { ...search }, { preserveState: true, replace: true });

// 답글 폼은 펼친 행에만 뜬다. 목록에 textarea 를 스무 개 깔면 화면이 안 읽힌다.
const replyingId = ref(null);
const replyForm = useForm({ admin_reply: '' });

const openReply = (row) => {
    replyingId.value = replyingId.value === row.id ? null : row.id;
    replyForm.admin_reply = row.admin_reply ?? '';
    replyForm.clearErrors();
};

const submitReply = (row) => replyForm.put(`/admin/reviews/${row.id}/reply`, {
    preserveScroll: true,
    onSuccess: () => { replyingId.value = null; },
});

const toggleStatus = (row) => {
    router.put(`/admin/reviews/${row.id}/status`, {
        status: row.status === 'PUBLISHED' ? 'HIDDEN' : 'PUBLISHED',
    }, { preserveScroll: true });
};

const inputClass = adminInput;
</script>

<template>
    <AdminLayout title="상품후기">
        <h2 class="text-xl font-semibold tracking-tight">상품후기</h2>
        <p class="mt-1 text-sm text-neutral-500">
            후기는 삭제하지 않고 <strong>숨김</strong>으로 내립니다. 숨기면 평점 집계에서도 함께 빠집니다.
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
            <select v-model="search.rating" :class="inputClass">
                <option value="">별점 전체</option>
                <option v-for="n in 5" :key="n" :value="n">{{ n }}점</option>
            </select>
            <input v-model="search.keyword" type="text" placeholder="상품명 · 후기 내용" :class="inputClass">
            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
        </form>

        <div class="mt-6 space-y-3">
            <article
                v-for="row in reviews.data"
                :key="row.id"
                class="rounded-xl border border-neutral-800 p-5"
                :class="row.status === 'HIDDEN' ? 'opacity-60' : ''"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium">{{ row.product_name }}</p>
                        <p class="mt-1 flex items-center gap-2 text-xs text-neutral-500">
                            <StarRating :value="row.rating" size="sm" />
                            {{ row.author }} · {{ row.created_at }}
                            <span v-if="row.variant_name">· {{ row.variant_name }}</span>
                            <span
                                class="rounded px-2 py-0.5"
                                :class="row.status === 'PUBLISHED'
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : 'bg-neutral-500/15 text-neutral-400'"
                            >{{ row.status_label }}</span>
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-700 px-3 py-1.5 text-xs text-neutral-300"
                            @click="openReply(row)"
                        >
                            {{ row.admin_reply ? '답글 수정' : '답글' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-700 px-3 py-1.5 text-xs text-neutral-300"
                            @click="toggleStatus(row)"
                        >
                            {{ row.status === 'PUBLISHED' ? '숨기기' : '노출하기' }}
                        </button>
                    </div>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm text-neutral-200">{{ row.content }}</p>

                <div v-if="row.admin_reply && replyingId !== row.id" class="mt-3 rounded-lg bg-neutral-900/60 px-4 py-3">
                    <p class="text-xs text-neutral-500">
                        판매자 답글<span v-if="row.replied_by"> · {{ row.replied_by }}</span>
                    </p>
                    <p class="mt-1 whitespace-pre-line text-sm text-neutral-300">{{ row.admin_reply }}</p>
                </div>

                <form v-if="replyingId === row.id" class="mt-3" @submit.prevent="submitReply(row)">
                    <textarea
                        v-model="replyForm.admin_reply"
                        rows="3"
                        placeholder="답글을 입력하세요. 고객 화면에 그대로 노출됩니다."
                        :class="[inputClass, 'w-full']"
                    />
                    <p v-if="replyForm.errors.admin_reply" class="mt-1 text-xs text-rose-400">
                        {{ replyForm.errors.admin_reply }}
                    </p>
                    <div class="mt-2 flex gap-2">
                        <button
                            type="submit"
                            :disabled="replyForm.processing"
                            class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-40"
                        >
                            등록
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-neutral-700 px-3 py-2 text-sm text-neutral-400"
                            @click="replyingId = null"
                        >
                            취소
                        </button>
                    </div>
                </form>
            </article>

            <p v-if="reviews.data.length === 0" class="rounded-xl border border-neutral-800 px-5 py-10 text-center text-neutral-500">
                해당하는 후기가 없습니다.
            </p>
        </div>
    </AdminLayout>
</template>
