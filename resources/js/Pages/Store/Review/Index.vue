<script setup>
import { computed, reactive } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import StarRating from '@/Components/StarRating.vue';

const props = defineProps({
    writable: { type: Array, required: true },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

/* 항목마다 별점·내용을 따로 갖는다. 한 폼을 돌려쓰면 다른 칸에 쓴 내용이 사라진다. */
const drafts = reactive(
    Object.fromEntries(props.writable.map((w) => [w.order_item_id, { rating: 5, content: '' }])),
);

const form = useForm({ order_item_id: null, rating: 5, content: '' });

const submit = (item) => {
    const draft = drafts[item.order_item_id];

    form.order_item_id = item.order_item_id;
    form.rating = draft.rating;
    form.content = draft.content;

    form.post('/reviews', { preserveScroll: true });
};
</script>

<template>
    <StoreLayout title="후기 쓰기">
        <h1 class="text-2xl font-semibold tracking-tight">후기 쓰기</h1>
        <p class="mt-2 text-sm text-neutral-500">
            배송완료된 주문에 한해 후기를 쓰실 수 있습니다. 주문 항목당 한 번입니다.
        </p>

        <p
            v-if="errors.review || errors.content || errors.order_item_id"
            class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
        >
            {{ errors.review ?? errors.content ?? errors.order_item_id }}
        </p>

        <div v-if="writable.length" class="mt-6 space-y-4">
            <form
                v-for="item in writable"
                :key="item.order_item_id"
                class="rounded-xl border border-neutral-200 p-5"
                @submit.prevent="submit(item)"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium">
                        {{ item.product_name }}
                        <span v-if="item.variant_name" class="text-neutral-500">/ {{ item.variant_name }}</span>
                    </p>
                    <p class="font-mono text-xs text-neutral-500">
                        {{ item.order_no }} · {{ item.ordered_at }}
                    </p>
                </div>

                <div class="mt-3">
                    <StarRating v-model:value="drafts[item.order_item_id].rating" editable size="lg" />
                </div>

                <textarea
                    v-model="drafts[item.order_item_id].content"
                    rows="3"
                    placeholder="상품은 어떠셨나요?"
                    class="mt-3 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900"
                />

                <button
                    type="submit"
                    :disabled="form.processing || drafts[item.order_item_id].content.length < 5"
                    class="mt-3 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                    후기 등록
                </button>
            </form>
        </div>

        <div v-else class="mt-10 text-neutral-500">
            <p>지금 쓰실 수 있는 후기가 없습니다.</p>
            <Link href="/orders" class="mt-2 inline-block text-sm text-neutral-900 underline">주문 내역 보기</Link>
        </div>
    </StoreLayout>
</template>
