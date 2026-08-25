<script setup>
import { Link } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ProductRow from '@/Components/ProductRow.vue';

defineProps({
    products: { type: Array, required: true },
    recommend: { type: Object, required: true },
    recentlyViewed: { type: Array, default: () => [] },
    reorder: { type: Array, default: () => [] },
});
</script>

<template>
    <StoreLayout title="홈">
        <div class="flex items-baseline justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">신상품</h1>
            <Link href="/products" class="text-sm text-neutral-500 hover:text-neutral-900">
                전체 보기 →
            </Link>
        </div>

        <div v-if="products.length" class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
            <ProductCard v-for="p in products" :key="p.id" :product="p" />
        </div>

        <p v-else class="mt-10 text-neutral-500">
            아직 판매중인 상품이 없습니다.
        </p>

        <!-- 제목·문구는 서버가 정한다 (로그인·구매이력에 따라 달라짐) -->
        <ProductRow
            :title="recommend.title"
            :subtitle="recommend.subtitle"
            :items="recommend.items"
        />

        <ProductRow
            title="최근 본 상품"
            subtitle="이 브라우저에서 최근에 보신 상품이에요."
            :items="recentlyViewed"
        />

        <ProductRow
            title="다시 구매하기"
            subtitle="이전에 주문하셨던 상품이에요."
            :items="reorder"
        />
    </StoreLayout>
</template>
