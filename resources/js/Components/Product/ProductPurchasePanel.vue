<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import StarRating from '@/Components/StarRating.vue';

/**
 * 상품 정보 + 조합형 옵션 선택 + 장바구니/바로구매.
 *
 * **옵션 선택 규칙이 이 화면의 핵심이다** (docs/schema-draft.md §2.3):
 * 앞 단계에서 고른 값과 **같은 조합에 실제로 존재하는** 값만 다음 단계 후보로 남는다.
 * 없는 조합은 애초에 variant 가 없으므로 후보에서 자동으로 빠진다 —
 * 조합 목록을 따로 만들지 않는 이유다.
 */
const props = defineProps({
    product: { type: Object, required: true },
});

const emit = defineEmits(['show-reviews']);

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

/** 단계별 선택값. options 와 같은 길이이고, 아직 안 고른 단계는 null 이다. */
const selected = ref(props.product.options.map(() => null));

const quantity = ref(1);

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
</script>

<template>
    <div>
        <p class="text-sm text-neutral-500">{{ product.category_name }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ product.name }}</h1>
        <p v-if="product.summary" class="mt-2 text-neutral-600">{{ product.summary }}</p>

        <!-- 후기가 없으면 별 0개가 아니라 아무것도 안 보여준다. 0점처럼 보인다. -->
        <button
            v-if="product.rating.count > 0"
            type="button"
            class="mt-3 flex items-center gap-2 text-sm"
            @click="emit('show-reviews')"
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
</template>
