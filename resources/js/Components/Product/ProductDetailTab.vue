<script setup>
/**
 * 상품 상세정보 탭 — 설명 글 + 상세 이미지.
 */
defineProps({
    product: { type: Object, required: true },
});
</script>

<template>
    <div class="pt-8">
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
</template>
