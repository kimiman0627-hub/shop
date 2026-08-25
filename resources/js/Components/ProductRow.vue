<script setup>
import { Link } from '@inertiajs/vue3';
import ProductCard from '@/Components/ProductCard.vue';

/**
 * 제목 + 상품 카드 줄. 홈·상품상세의 추천 블록이 전부 이 모양이다.
 *
 * items 가 비면 **아무것도 그리지 않는다.** 빈 추천 영역이 남으면
 * 화면에 구멍이 생긴 것처럼 보인다.
 */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    items: { type: Array, required: true },
    moreHref: { type: String, default: null },
});
</script>

<template>
    <section v-if="items.length" class="mt-12">
        <div class="flex items-baseline justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight">{{ title }}</h2>
                <p v-if="subtitle" class="mt-1 text-sm text-neutral-500">{{ subtitle }}</p>
            </div>
            <Link
                v-if="moreHref"
                :href="moreHref"
                class="shrink-0 text-sm text-neutral-500 hover:text-neutral-900"
            >
                전체 보기 →
            </Link>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
            <ProductCard v-for="p in items" :key="p.id" :product="p" />
        </div>
    </section>
</template>
