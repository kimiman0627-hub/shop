<script setup>
import { computed, ref } from 'vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductRow from '@/Components/ProductRow.vue';
import ProductGallery from '@/Components/Product/ProductGallery.vue';
import ProductPurchasePanel from '@/Components/Product/ProductPurchasePanel.vue';
import ProductDetailTab from '@/Components/Product/ProductDetailTab.vue';
import ProductReviews from '@/Components/Product/ProductReviews.vue';
import ProductQna from '@/Components/Product/ProductQna.vue';

/**
 * 상품 상세.
 *
 * 갤러리·구매패널·탭 세 덩어리를 `Components/Product/` 로 뺐다.
 * 여기 남은 것은 **조립과 어느 탭이 열려 있는지**뿐이다 — 상단 별점을 누르면
 * 후기 탭으로 가야 해서 탭 상태를 공통 부모가 들고 있다.
 *
 * **탭은 `v-show` 다.** `v-if` 로 하면 탭을 옮길 때마다 후기·문의가 다시 그려지고
 * 스크롤 위치와 작성 중이던 글이 날아간다.
 */
const props = defineProps({
    product: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    recentlyViewed: { type: Array, default: () => [] },
    reviews: { type: Object, required: true },
    writableReviews: { type: Array, default: () => [] },
    questions: { type: Object, required: true },
    supportChannel: { type: Object, default: () => ({}) },
});

const tab = ref('DETAIL');

const tabs = computed(() => [
    { key: 'DETAIL', label: '상세정보' },
    { key: 'REVIEW', label: `상품후기 ${props.reviews.summary.count}` },
    { key: 'QNA', label: `상품문의 ${props.questions.total}` },
]);
</script>

<template>
    <StoreLayout :title="product.name">
        <div class="grid gap-10 lg:grid-cols-2">
            <ProductGallery :images="product.images" :alt="product.name" />

            <ProductPurchasePanel :product="product" @show-reviews="tab = 'REVIEW'" />
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

            <ProductDetailTab v-show="tab === 'DETAIL'" :product="product" />

            <ProductReviews
                v-show="tab === 'REVIEW'"
                :reviews="reviews"
                :writable-reviews="writableReviews"
            />

            <ProductQna
                v-show="tab === 'QNA'"
                :questions="questions"
                :product-id="product.id"
            />
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
