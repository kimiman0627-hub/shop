<script setup>
import { Link } from '@inertiajs/vue3';
import StarRating from '@/Components/StarRating.vue';

defineProps({
    product: { type: Object, required: true },
});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;
</script>

<template>
    <Link :href="`/products/${encodeURIComponent(product.slug)}`" class="group block">
        <div class="relative aspect-square overflow-hidden rounded-lg bg-neutral-100">
            <img
                v-if="product.thumbnail_url"
                :src="product.thumbnail_url"
                :alt="product.name"
                class="h-full w-full object-cover transition group-hover:scale-105"
            >
            <div v-else class="flex h-full w-full items-center justify-center text-sm text-neutral-400">
                이미지 없음
            </div>

            <div
                v-if="product.sold_out"
                class="absolute inset-0 flex items-center justify-center bg-white/70 text-sm font-medium text-neutral-700"
            >
                품절
            </div>
        </div>

        <p class="mt-3 text-xs text-neutral-500">{{ product.category_name }}</p>
        <p class="mt-0.5 font-medium group-hover:underline">{{ product.name }}</p>

        <p class="mt-1">
            <span class="font-semibold">{{ won(product.display_price) }}</span>
            <span v-if="product.is_discounted" class="ml-2 text-sm text-neutral-400 line-through">
                {{ won(product.base_price) }}
            </span>
        </p>

        <!-- 후기가 없으면 별을 아예 안 그린다. 0개짜리 별은 0점처럼 보인다. -->
        <p v-if="product.review_count > 0" class="mt-1 flex items-center gap-1 text-xs text-neutral-500">
            <StarRating :value="product.rating_average" size="sm" />
            <span>{{ product.rating_average.toFixed(1) }}</span>
            <span>({{ product.review_count }})</span>
        </p>
    </Link>
</template>
