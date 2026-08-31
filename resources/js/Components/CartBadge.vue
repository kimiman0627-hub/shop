<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * 장바구니 수량 배지. 3곳(헤더 데스크톱·헤더 모바일·하단 탭)이 같은 모양을 쓴다.
 *
 * **0 이면 아무것도 그리지 않는다** — 빈 장바구니에 0 이 붙어 있으면 눈에 걸린다.
 */
defineProps({
    /** 아이콘 위에 겹쳐 띄울지. false 면 글자 옆에 인라인으로 붙는다. */
    floating: { type: Boolean, default: false },
});

const count = computed(() => usePage().props.cartCount ?? 0);
</script>

<template>
    <span
        v-if="count > 0"
        class="rounded-full bg-neutral-900 text-white"
        :class="floating
            ? 'absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center px-1 text-[10px] font-medium'
            : 'px-1.5 py-0.5 text-xs'"
    >
        {{ count }}
    </span>
</template>
