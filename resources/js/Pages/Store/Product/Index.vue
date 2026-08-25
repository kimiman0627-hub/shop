<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const keyword = ref(props.filters.keyword ?? '');

const search = () => router.get('/products', {
    keyword: keyword.value || undefined,
    category: props.filters.category || undefined,
}, { preserveState: true });
</script>

<template>
    <StoreLayout title="상품">
        <div class="flex flex-wrap items-baseline justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">상품</h1>

            <form class="flex gap-2" @submit.prevent="search">
                <input
                    v-model="keyword"
                    type="text"
                    placeholder="상품명 검색"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900"
                >
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm text-white">
                    검색
                </button>
            </form>
        </div>

        <div v-if="products.data.length" class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
            <ProductCard v-for="p in products.data" :key="p.id" :product="p" />
        </div>

        <p v-else class="mt-10 text-neutral-500">
            조건에 맞는 상품이 없습니다.
        </p>

        <div v-if="products.last_page > 1" class="mt-10 flex justify-center gap-1">
            <Link
                v-for="link in products.links"
                :key="link.label"
                :href="link.url ?? '#'"
                class="rounded px-3 py-1 text-sm"
                :class="link.active
                    ? 'bg-neutral-900 text-white'
                    : link.url ? 'text-neutral-600 hover:bg-neutral-100' : 'text-neutral-300'"
                v-html="link.label"
            />
        </div>
    </StoreLayout>
</template>
