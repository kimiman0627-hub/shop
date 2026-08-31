<script setup>
import { ref } from 'vue';

/**
 * 상품 상단 갤러리. 큰 이미지 하나 + 아래 썸네일.
 *
 * **상세 설명 이미지는 여기 오지 않는다.** 서버가 `GALLERY` / `DETAIL` 을 갈라서
 * 내려주므로(`product_images.type`) 이 컴포넌트는 갤러리만 받는다.
 */
const props = defineProps({
    images: { type: Array, default: () => [] },
    alt: { type: String, default: '' },
});

const active = ref(props.images[0]?.url ?? null);
</script>

<template>
    <div>
        <div class="aspect-square overflow-hidden rounded-lg bg-neutral-100">
            <img v-if="active" :src="active" :alt="alt" class="h-full w-full object-cover">
            <div v-else class="flex h-full w-full items-center justify-center text-neutral-400">
                이미지 없음
            </div>
        </div>

        <!-- 한 장뿐이면 썸네일 줄을 만들지 않는다. 누를 것이 없다. -->
        <div v-if="images.length > 1" class="mt-3 flex gap-2">
            <button
                v-for="image in images"
                :key="image.id"
                type="button"
                class="h-16 w-16 overflow-hidden rounded border"
                :class="active === image.url ? 'border-neutral-900' : 'border-neutral-200'"
                @click="active = image.url"
            >
                <img :src="image.url" :alt="image.alt ?? ''" class="h-full w-full object-cover">
            </button>
        </div>
    </div>
</template>
