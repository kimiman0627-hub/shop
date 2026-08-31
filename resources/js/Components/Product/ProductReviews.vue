<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import StarRating from '@/Components/StarRating.vue';
import Pagination from '@/Components/Pagination.vue';

/**
 * 상품후기 탭 — 평점 요약 + 작성 폼 + 목록.
 *
 * **작성 자격은 서버가 정한다.** `writableReviews` 가 비어 있으면 폼을 안 그린다 —
 * 구매하지 않았거나 이미 썼다는 뜻이다. 화면에서 막는 게 아니라 서버가 안 내려주는
 * 것이고, 등록할 때 서버가 자격을 다시 검사한다.
 *
 * 폼과 삭제를 부모로 올리지 않고 여기서 처리한다 — 후기에 관한 것이 한 곳에 있어야
 * 나중에 포토후기 같은 것을 붙일 때 여기만 보면 된다.
 */
const props = defineProps({
    reviews: { type: Object, required: true },
    writableReviews: { type: Array, default: () => [] },
});

const user = computed(() => usePage().props.auth.user);

const canWrite = computed(() => props.writableReviews.length > 0);

const form = useForm({ order_item_id: null, rating: 5, content: '' });

const submit = () => {
    form.order_item_id = props.writableReviews[0]?.order_item_id ?? null;

    form.post('/reviews', {
        preserveScroll: true,
        onSuccess: () => form.reset('content'),
    });
};

const remove = (id) => {
    if (confirm('후기를 삭제할까요?')) {
        router.delete(`/reviews/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="pt-8">
        <div
            v-if="reviews.summary.count > 0"
            class="flex flex-wrap items-center gap-8 rounded-xl border border-neutral-200 p-6"
        >
            <div class="text-center">
                <p class="text-4xl font-semibold">{{ reviews.summary.average.toFixed(1) }}</p>
                <StarRating :value="reviews.summary.average" class="mt-1" />
                <p class="mt-1 text-xs text-neutral-500">{{ reviews.summary.count }}개의 후기</p>
            </div>

            <div class="min-w-56 flex-1 space-y-1">
                <div
                    v-for="d in reviews.summary.distribution"
                    :key="d.rating"
                    class="flex items-center gap-2 text-xs"
                >
                    <span class="w-8 text-neutral-500">{{ d.rating }}점</span>
                    <div class="h-1.5 flex-1 rounded-full bg-neutral-100">
                        <div class="h-1.5 rounded-full bg-amber-400" :style="{ width: `${d.percent}%` }" />
                    </div>
                    <span class="w-8 text-right text-neutral-500">{{ d.count }}</span>
                </div>
            </div>
        </div>

        <!-- 작성 폼: 구매하고 아직 안 쓴 주문이 있을 때만 -->
        <form
            v-if="canWrite"
            class="mt-6 rounded-xl border border-neutral-200 p-6"
            @submit.prevent="submit"
        >
            <p class="text-sm font-medium">후기 작성</p>
            <p class="mt-1 text-xs text-neutral-500">
                {{ writableReviews[0].product_name }}
                <span v-if="writableReviews[0].variant_name">/ {{ writableReviews[0].variant_name }}</span>
                · {{ writableReviews[0].ordered_at }} 주문
            </p>

            <div class="mt-4">
                <StarRating v-model:value="form.rating" editable size="lg" />
            </div>

            <textarea
                v-model="form.content"
                rows="4"
                placeholder="상품은 어떠셨나요? 다른 고객에게 도움이 되는 내용을 남겨주세요."
                class="mt-3 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900"
            />
            <p v-if="form.errors.content" class="mt-1 text-xs text-red-600">
                {{ form.errors.content }}
            </p>
            <p v-if="form.errors.order_item_id" class="mt-1 text-xs text-red-600">
                {{ form.errors.order_item_id }}
            </p>

            <button
                type="submit"
                :disabled="form.processing"
                class="mt-3 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
            >
                등록
            </button>
        </form>

        <p v-else-if="user" class="mt-6 rounded-lg bg-neutral-50 px-4 py-3 text-sm text-neutral-500">
            배송완료된 주문에 한해 후기를 쓰실 수 있습니다. 주문 항목당 한 번입니다.
        </p>

        <!-- 후기 목록 -->
        <ul v-if="reviews.list.data.length" class="mt-6 divide-y divide-neutral-200">
            <li v-for="r in reviews.list.data" :key="r.id" class="py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <StarRating :value="r.rating" size="sm" />
                        <p class="mt-1 text-xs text-neutral-500">
                            {{ r.author }} · {{ r.created_at }}
                            <span v-if="r.variant_name">· {{ r.variant_name }}</span>
                        </p>
                    </div>
                    <button
                        v-if="user && user.id === r.user_id"
                        type="button"
                        class="shrink-0 text-xs text-red-600 hover:underline"
                        @click="remove(r.id)"
                    >
                        삭제
                    </button>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm text-neutral-700">{{ r.content }}</p>

                <div v-if="r.admin_reply" class="mt-3 rounded-lg bg-neutral-50 px-4 py-3">
                    <p class="text-xs font-medium text-neutral-700">판매자 답글</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-neutral-600">{{ r.admin_reply }}</p>
                </div>
            </li>
        </ul>

        <p v-else class="mt-6 text-sm text-neutral-500">아직 등록된 후기가 없습니다.</p>

        <Pagination :paginator="reviews.list" preserve-scroll center />
    </div>
</template>
