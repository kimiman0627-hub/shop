<script setup>
import { computed } from 'vue';

/**
 * 별점 표시 · 입력.
 *
 * `editable` 이면 클릭으로 점수를 고른다(v-model:value).
 * 아니면 읽기 전용이고, 소수점 평균은 **반올림해 채운 별**로 보여준다 —
 * 반쪽 별을 그리려면 SVG 마스크가 필요한데 이 크기에서는 차이가 안 보인다.
 */
const props = defineProps({
    value: { type: Number, default: 0 },
    editable: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const emit = defineEmits(['update:value']);

const filled = computed(() => Math.round(props.value));

const sizeClass = computed(() => ({
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-2xl',
}[props.size] ?? 'text-base'));

const pick = (score) => {
    if (props.editable) {
        emit('update:value', score);
    }
};
</script>

<template>
    <span class="inline-flex items-center gap-0.5" :class="sizeClass">
        <button
            v-for="score in 5"
            :key="score"
            type="button"
            :disabled="!editable"
            :aria-label="`${score}점`"
            class="leading-none"
            :class="[
                score <= filled ? 'text-amber-400' : 'text-neutral-300',
                editable ? 'cursor-pointer hover:text-amber-300' : 'cursor-default',
            ]"
            @click="pick(score)"
        >
            ★
        </button>
    </span>
</template>
