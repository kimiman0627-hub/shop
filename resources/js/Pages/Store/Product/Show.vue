<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductRow from '@/Components/ProductRow.vue';
import StarRating from '@/Components/StarRating.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    product: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    recentlyViewed: { type: Array, default: () => [] },
    reviews: { type: Object, required: true },
    writableReviews: { type: Array, default: () => [] },
    questions: { type: Object, required: true },
    supportChannel: { type: Object, default: () => ({}) },
});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const activeImage = ref(props.product.images[0]?.url ?? null);

/** 단계별 선택값. options 와 같은 길이이고, 아직 안 고른 단계는 null 이다. */
const selected = ref(props.product.options.map(() => null));

const quantity = ref(1);

/**
 * 어떤 단계의 후보 값들.
 *
 * 앞 단계에서 고른 값과 **같은 조합에 실제로 존재하는** 값만 남긴다.
 * 없는 조합은 애초에 variant 가 없으므로 후보에서 자동으로 빠진다
 * (docs/schema-draft.md §2.3).
 */
const candidatesFor = (stepIndex) => {
    const prior = selected.value.slice(0, stepIndex).filter((v) => v !== null);

    const reachable = props.product.variants.filter(
        (v) => prior.every((id) => v.option_value_ids.includes(id)),
    );

    return props.product.options[stepIndex].values
        .filter((value) => reachable.some((v) => v.option_value_ids.includes(value.id)))
        .map((value) => ({
            ...value,
            // 이 값을 골랐을 때 살 수 있는 조합이 하나라도 남는가.
            purchasable: reachable.some(
                (v) => v.option_value_ids.includes(value.id) && v.purchasable,
            ),
        }));
};

const optionSteps = computed(
    () => props.product.options.map((option, index) => ({
        ...option,
        index,
        // 앞 단계를 고르기 전에는 다음 단계를 열지 않는다.
        enabled: index === 0 || selected.value[index - 1] !== null,
        candidates: candidatesFor(index),
    })),
);

const pick = (stepIndex, valueId) => {
    selected.value[stepIndex] = valueId;

    // 앞 단계를 바꾸면 뒤 단계 선택은 무효가 된다.
    for (let i = stepIndex + 1; i < selected.value.length; i += 1) {
        selected.value[i] = null;
    }
};

/** 모든 단계를 고르면 조합 하나가 확정된다. */
const chosenVariant = computed(() => {
    if (selected.value.some((v) => v === null)) {
        return null;
    }

    return props.product.variants.find(
        (v) => selected.value.every((id) => v.option_value_ids.includes(id))
            && v.option_value_ids.length === selected.value.length,
    ) ?? null;
});

// 옵션이 없는 상품은 조합이 하나뿐이다.
const singleVariant = computed(
    () => (props.product.options.length === 0 ? props.product.variants[0] ?? null : null),
);

const activeVariant = computed(() => singleVariant.value ?? chosenVariant.value);

const canBuy = computed(
    () => props.product.is_purchasable && (activeVariant.value?.purchasable ?? false),
);

const totalPrice = computed(
    () => (activeVariant.value ? activeVariant.value.price * quantity.value : null),
);

const selectedLabels = computed(
    () => selected.value
        .map((id, i) => props.product.options[i].values.find((v) => v.id === id)?.value)
        .filter(Boolean)
        .join(' / '),
);

const cartForm = useForm({ variant_id: null, quantity: 1 });

const cartError = computed(() => {
    const errors = usePage().props.errors ?? {};

    return errors.quantity ?? errors.variant ?? null;
});

const addToCart = () => {
    if (!activeVariant.value) {
        return;
    }

    cartForm.variant_id = activeVariant.value.id;
    cartForm.quantity = quantity.value;

    cartForm.post('/cart', { preserveScroll: true });
};

/**
 * 바로구매. 장바구니를 거치지 않고 주문서로 간다.
 *
 * 비로그인이면 서버가 로그인 화면으로 보내고, 로그인 후 이 페이지로 돌아온다.
 * 그래서 현재 주소를 같이 넘긴다.
 */
const buyForm = useForm({ variant_id: null, quantity: 1, return_to: '' });

const buyNow = () => {
    if (!activeVariant.value) {
        return;
    }

    buyForm.variant_id = activeVariant.value.id;
    buyForm.quantity = quantity.value;
    buyForm.return_to = window.location.pathname;

    buyForm.post('/orders/direct');
};

/* ---------------------------------------------------------------- 탭 */

const tab = ref('DETAIL');

const tabs = computed(() => [
    { key: 'DETAIL', label: '상세정보' },
    { key: 'REVIEW', label: `상품후기 ${props.reviews.summary.count}` },
    { key: 'QNA', label: `상품문의 ${props.questions.total}` },
]);

/* -------------------------------------------------------------- 후기 */

const reviewForm = useForm({ order_item_id: null, rating: 5, content: '' });

// 이 상품을 사고 아직 후기를 안 쓴 주문이 있으면 작성 폼이 열린다.
const canWriteReview = computed(() => props.writableReviews.length > 0);

const submitReview = () => {
    reviewForm.order_item_id = props.writableReviews[0]?.order_item_id ?? null;

    reviewForm.post('/reviews', {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset('content'),
    });
};

const removeReview = (id) => {
    if (confirm('후기를 삭제할까요?')) {
        router.delete(`/reviews/${id}`, { preserveScroll: true });
    }
};

/* -------------------------------------------------------------- 문의 */

const questionForm = useForm({ content: '', is_secret: false });

const submitQuestion = () => {
    questionForm.post(`/products/${props.product.id}/questions`, {
        preserveScroll: true,
        onSuccess: () => questionForm.reset(),
    });
};

const removeQuestion = (id) => {
    if (confirm('문의를 삭제할까요?')) {
        router.delete(`/questions/${id}`, { preserveScroll: true });
    }
};

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <StoreLayout :title="product.name">
        <div class="grid gap-10 lg:grid-cols-2">
            <!-- 이미지 -->
            <div>
                <div class="aspect-square overflow-hidden rounded-lg bg-neutral-100">
                    <img v-if="activeImage" :src="activeImage" :alt="product.name" class="h-full w-full object-cover">
                    <div v-else class="flex h-full w-full items-center justify-center text-neutral-400">
                        이미지 없음
                    </div>
                </div>

                <div v-if="product.images.length > 1" class="mt-3 flex gap-2">
                    <button
                        v-for="image in product.images"
                        :key="image.id"
                        type="button"
                        class="h-16 w-16 overflow-hidden rounded border"
                        :class="activeImage === image.url ? 'border-neutral-900' : 'border-neutral-200'"
                        @click="activeImage = image.url"
                    >
                        <img :src="image.url" :alt="image.alt ?? ''" class="h-full w-full object-cover">
                    </button>
                </div>
            </div>

            <!-- 정보 · 옵션 -->
            <div>
                <p class="text-sm text-neutral-500">{{ product.category_name }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ product.name }}</h1>
                <p v-if="product.summary" class="mt-2 text-neutral-600">{{ product.summary }}</p>

                <!-- 후기가 없으면 별 0개가 아니라 아무것도 안 보여준다. 0점처럼 보인다. -->
                <button
                    v-if="product.rating.count > 0"
                    type="button"
                    class="mt-3 flex items-center gap-2 text-sm"
                    @click="tab = 'REVIEW'"
                >
                    <StarRating :value="product.rating.average" size="sm" />
                    <span class="font-medium">{{ product.rating.average.toFixed(1) }}</span>
                    <span class="text-neutral-500 underline">후기 {{ product.rating.count }}건</span>
                </button>

                <div class="mt-5 flex items-baseline gap-3">
                    <span class="text-2xl font-semibold">{{ won(product.display_price) }}</span>
                    <span v-if="product.is_discounted" class="text-neutral-400 line-through">
                        {{ won(product.base_price) }}
                    </span>
                </div>

                <p class="mt-2 text-sm text-neutral-500">
                    <template v-if="product.shipping.is_free">무료배송</template>
                    <template v-else>
                        배송비 {{ won(product.shipping.fee) }}
                        <template v-if="product.shipping.free_threshold">
                            · {{ won(product.shipping.free_threshold) }} 이상 무료
                        </template>
                    </template>
                </p>

                <div v-if="!product.is_purchasable" class="mt-6 rounded-lg bg-neutral-100 px-4 py-3 text-sm text-neutral-600">
                    현재 구매할 수 없는 상품입니다.
                </div>

                <!-- 조합형 옵션: 앞 단계를 고르면 뒤 단계 후보가 좁혀진다 -->
                <div v-if="product.options.length" class="mt-6 space-y-4">
                    <div v-for="step in optionSteps" :key="step.id">
                        <p class="text-sm font-medium">{{ step.name }}</p>

                        <div v-if="step.enabled" class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="value in step.candidates"
                                :key="value.id"
                                type="button"
                                :disabled="!value.purchasable"
                                class="rounded-lg border px-3 py-1.5 text-sm transition"
                                :class="[
                                    selected[step.index] === value.id
                                        ? 'border-neutral-900 bg-neutral-900 text-white'
                                        : 'border-neutral-300 hover:border-neutral-900',
                                    !value.purchasable ? 'cursor-not-allowed text-neutral-300 line-through hover:border-neutral-300' : '',
                                ]"
                                @click="pick(step.index, value.id)"
                            >
                                {{ value.value }}
                            </button>
                        </div>

                        <p v-else class="mt-2 text-sm text-neutral-400">
                            {{ product.options[step.index - 1].name }}을(를) 먼저 선택하세요.
                        </p>
                    </div>
                </div>

                <!-- 선택 결과 -->
                <div v-if="activeVariant" class="mt-6 rounded-lg border border-neutral-200 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm">
                            <span v-if="selectedLabels" class="font-medium">{{ selectedLabels }}</span>
                            <span v-else class="font-medium">{{ product.name }}</span>
                        </p>
                        <p class="text-sm text-neutral-600">{{ won(activeVariant.price) }}</p>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <label class="text-sm text-neutral-500">수량</label>
                        <input
                            v-model.number="quantity"
                            type="number"
                            min="1"
                            class="w-20 rounded border border-neutral-300 px-2 py-1 text-sm outline-none focus:border-neutral-900"
                        >
                    </div>

                    <p class="mt-3 text-right text-lg font-semibold">{{ won(totalPrice) }}</p>
                </div>

                <p v-if="cartError" class="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">
                    {{ cartError }}
                </p>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        :disabled="!canBuy || cartForm.processing"
                        class="rounded-lg border border-neutral-900 px-4 py-3 text-sm font-medium text-neutral-900 transition hover:bg-neutral-100 disabled:cursor-not-allowed disabled:border-neutral-300 disabled:text-neutral-400"
                        @click="addToCart"
                    >
                        <template v-if="!activeVariant">옵션을 선택하세요</template>
                        <template v-else-if="!canBuy">품절</template>
                        <template v-else-if="cartForm.processing">담는 중…</template>
                        <template v-else>장바구니 담기</template>
                    </button>

                    <button
                        type="button"
                        :disabled="!canBuy || buyForm.processing"
                        class="rounded-lg bg-neutral-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                        @click="buyNow"
                    >
                        <template v-if="buyForm.processing">이동 중…</template>
                        <template v-else>바로 구매</template>
                    </button>
                </div>
            </div>
        </div>

        <!-- 상세정보 · 후기 · 문의 -->
        <section class="mt-12 border-t border-neutral-200 pt-8">
            <div class="flex gap-1 border-b border-neutral-200">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    class="-mb-px border-b-2 px-4 py-3 text-sm"
                    :class="tab === t.key
                        ? 'border-neutral-900 font-medium text-neutral-900'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800'"
                    @click="tab = t.key"
                >
                    {{ t.label }}
                </button>
            </div>

            <!-- 상세정보 -->
            <div v-show="tab === 'DETAIL'" class="pt-8">
                <p v-if="product.description" class="whitespace-pre-line text-neutral-700">
                    {{ product.description }}
                </p>

                <!--
                    상세 이미지는 원본 비율 그대로 세로로 잇는다.
                    자르거나 고정 높이를 주면 상품 정보가 잘린다.
                -->
                <div v-if="product.detail_images.length" class="mt-8 space-y-2">
                    <img
                        v-for="image in product.detail_images"
                        :key="image.id"
                        :src="image.url"
                        :alt="image.alt ?? product.name"
                        class="mx-auto block w-full max-w-3xl"
                        loading="lazy"
                    >
                </div>

                <p v-if="!product.description && !product.detail_images.length" class="text-neutral-500">
                    등록된 상세 정보가 없습니다.
                </p>
            </div>

            <!-- 상품후기 -->
            <div v-show="tab === 'REVIEW'" class="pt-8">
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
                    v-if="canWriteReview"
                    class="mt-6 rounded-xl border border-neutral-200 p-6"
                    @submit.prevent="submitReview"
                >
                    <p class="text-sm font-medium">후기 작성</p>
                    <p class="mt-1 text-xs text-neutral-500">
                        {{ writableReviews[0].product_name }}
                        <span v-if="writableReviews[0].variant_name">/ {{ writableReviews[0].variant_name }}</span>
                        · {{ writableReviews[0].ordered_at }} 주문
                    </p>

                    <div class="mt-4">
                        <StarRating v-model:value="reviewForm.rating" editable size="lg" />
                    </div>

                    <textarea
                        v-model="reviewForm.content"
                        rows="4"
                        placeholder="상품은 어떠셨나요? 다른 고객에게 도움이 되는 내용을 남겨주세요."
                        class="mt-3 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900"
                    />
                    <p v-if="reviewForm.errors.content" class="mt-1 text-xs text-red-600">
                        {{ reviewForm.errors.content }}
                    </p>
                    <p v-if="reviewForm.errors.order_item_id" class="mt-1 text-xs text-red-600">
                        {{ reviewForm.errors.order_item_id }}
                    </p>

                    <button
                        type="submit"
                        :disabled="reviewForm.processing"
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
                                @click="removeReview(r.id)"
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

            <!-- 상품문의 -->
            <div v-show="tab === 'QNA'" class="pt-8">
                <form v-if="user" class="rounded-xl border border-neutral-200 p-6" @submit.prevent="submitQuestion">
                    <p class="text-sm font-medium">상품 문의하기</p>
                    <textarea
                        v-model="questionForm.content"
                        rows="3"
                        placeholder="상품에 대해 궁금한 점을 남겨주세요. 배송·주문 관련 문의는 1:1문의를 이용해 주세요."
                        class="mt-3 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900"
                    />
                    <p v-if="questionForm.errors.content" class="mt-1 text-xs text-red-600">
                        {{ questionForm.errors.content }}
                    </p>

                    <div class="mt-3 flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-neutral-600">
                            <input v-model="questionForm.is_secret" type="checkbox" class="rounded border-neutral-300">
                            비밀글 (작성자와 판매자만 볼 수 있습니다)
                        </label>
                        <button
                            type="submit"
                            :disabled="questionForm.processing"
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
                                    <span v-if="q.is_secret">🔒</span>
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
                                @click="removeQuestion(q.id)"
                            >
                                삭제
                            </button>
                        </div>
                    </li>
                </ul>

                <p v-else class="mt-6 text-sm text-neutral-500">아직 등록된 문의가 없습니다.</p>

                <Pagination :paginator="questions" />
            </div>
        </section>

        <ProductRow
            title="이 상품과 함께 구매한 상품"
            :items="related"
        />

        <ProductRow
            title="최근 본 상품"
            :items="recentlyViewed"
        />
    </StoreLayout>
</template>
