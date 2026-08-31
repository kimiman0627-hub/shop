<script setup>
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';

/**
 * 상품문의(Q&A) 탭 — 작성 폼 + 목록.
 *
 * **비밀글은 서버가 내용을 지워서 내려준다.** 볼 권한이 없으면 `content` 가 없다 —
 * 화면에서 가리는 방식이면 응답만 열어봐도 내용이 보인다(밟으면 아픈 곳 §20).
 * 여기서는 내용이 있으면 찍고 없으면 '비밀글입니다' 로 둘 뿐이다.
 *
 * 삭제 가능 여부(`is_deletable`)도 서버가 판단해서 내려준다.
 */
const props = defineProps({
    questions: { type: Object, required: true },
    productId: { type: Number, required: true },
});

const user = computed(() => usePage().props.auth.user);

const form = useForm({ content: '', is_secret: false });

const submit = () => {
    form.post(`/products/${props.productId}/questions`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const remove = (id) => {
    if (confirm('문의를 삭제할까요?')) {
        router.delete(`/questions/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="pt-8">
        <form v-if="user" class="rounded-xl border border-neutral-200 p-6" @submit.prevent="submit">
            <p class="text-sm font-medium">상품 문의하기</p>
            <textarea
                v-model="form.content"
                rows="3"
                placeholder="상품에 대해 궁금한 점을 남겨주세요. 배송·주문 관련 문의는 1:1문의를 이용해 주세요."
                class="mt-3 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900"
            />
            <p v-if="form.errors.content" class="mt-1 text-xs text-red-600">
                {{ form.errors.content }}
            </p>

            <div class="mt-3 flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-neutral-600">
                    <input v-model="form.is_secret" type="checkbox" class="rounded border-neutral-300">
                    비밀글 (작성자와 판매자만 볼 수 있습니다)
                </label>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                    등록
                </button>
            </div>
        </form>

        <p v-else class="rounded-lg bg-neutral-50 px-4 py-3 text-sm text-neutral-500">
            문의를 남기시려면 로그인이 필요합니다.
            <Link href="/login" class="ml-1 text-neutral-900 underline">로그인</Link>
        </p>

        <ul v-if="questions.data.length" class="mt-6 divide-y divide-neutral-200">
            <li v-for="q in questions.data" :key="q.id" class="py-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-xs text-neutral-500">
                            <span
                                class="rounded px-2 py-0.5"
                                :class="q.status === 'ANSWERED'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-neutral-200 text-neutral-600'"
                            >{{ q.status_label }}</span>
                            <Icon v-if="q.is_secret" name="lock" class="size-3.5 text-neutral-400" />
                            {{ q.author }} · {{ q.created_at }}
                        </p>

                        <!-- 볼 수 없는 비밀글은 서버가 내용을 안 내려준다 -->
                        <p v-if="q.content" class="mt-2 whitespace-pre-line text-sm text-neutral-800">
                            {{ q.content }}
                        </p>
                        <p v-else class="mt-2 text-sm text-neutral-400">비밀글입니다.</p>

                        <div v-if="q.answer" class="mt-3 rounded-lg bg-neutral-50 px-4 py-3">
                            <p class="text-xs font-medium text-neutral-700">판매자 답변</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-neutral-600">{{ q.answer }}</p>
                        </div>
                    </div>

                    <button
                        v-if="q.is_deletable"
                        type="button"
                        class="shrink-0 text-xs text-red-600 hover:underline"
                        @click="remove(q.id)"
                    >
                        삭제
                    </button>
                </div>
            </li>
        </ul>

        <p v-else class="mt-6 text-sm text-neutral-500">아직 등록된 문의가 없습니다.</p>

        <Pagination :paginator="questions" />
    </div>
</template>
