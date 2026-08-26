<script setup>
import { computed, ref } from 'vue';

/**
 * 일별 막대 그래프. **차트 라이브러리를 쓰지 않는다** —
 * 막대 하나 그리자고 번들에 수백 KB 를 얹을 이유가 없다.
 *
 * series: [{ date, label, revenue, order_count }]
 */
const props = defineProps({
    series: { type: Array, required: true },
    valueKey: { type: String, default: 'revenue' },
});

const hovered = ref(null);

const max = computed(() => Math.max(1, ...props.series.map((d) => Number(d[props.valueKey]) || 0)));

const bars = computed(() => props.series.map((d) => {
    const value = Number(d[props.valueKey]) || 0;

    return {
        ...d,
        value,
        // 값이 0 이면 높이 0 이라 막대가 사라진다. 바닥선을 조금 남겨 날짜가 있다는 걸 보인다.
        height: value === 0 ? 2 : Math.max(4, Math.round((value / max.value) * 100)),
        isZero: value === 0,
    };
}));

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

// 눈금은 최대값만. 촘촘한 축은 이 크기에서 읽히지도 않는다.
const peak = computed(() => won(max.value));
</script>

<template>
    <div>
        <div class="flex items-end justify-between text-xs text-neutral-500">
            <span>최대 {{ peak }}</span>
            <span v-if="hovered" class="text-neutral-200">
                {{ hovered.date }} · {{ won(hovered.value) }} · 주문 {{ hovered.order_count }}건
            </span>
        </div>

        <div class="mt-2 flex h-32 items-end gap-1">
            <div
                v-for="d in bars"
                :key="d.date"
                class="group flex h-full min-w-0 flex-1 flex-col justify-end"
                @mouseenter="hovered = d"
                @mouseleave="hovered = null"
            >
                <div
                    class="w-full rounded-t transition"
                    :class="d.isZero ? 'bg-neutral-800' : 'bg-neutral-500 group-hover:bg-neutral-300'"
                    :style="{ height: `${d.height}%` }"
                />
            </div>
        </div>

        <div class="mt-1 flex gap-1 text-[10px] text-neutral-600">
            <span v-for="(d, i) in bars" :key="d.date" class="min-w-0 flex-1 overflow-hidden text-center">
                <!-- 라벨이 겹치면 못 읽는다. 막대가 많으면 건너뛰며 찍는다. -->
                {{ bars.length <= 10 || i % 2 === 0 ? d.label : '' }}
            </span>
        </div>
    </div>
</template>
