<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * 페이지 번호. 고객·관리자 화면 8곳이 같은 마크업을 각자 갖고 있었다.
 *
 * Laravel 페이지네이터가 주는 `links` 를 그대로 받는다 — 번호를 직접 계산하면
 * '이전/다음' 라벨과 생략 부호(`...`)를 다시 만들어야 한다.
 *
 * `link.label` 은 `&laquo;` 같은 HTML 엔티티라 `v-html` 로 넣는다.
 * **서버가 만든 문자열이라 사용자 입력이 섞이지 않는다.**
 */
const props = defineProps({
    /** Laravel 페이지네이터 객체 (`links`, `last_page` 를 쓴다) */
    paginator: { type: Object, required: true },
    /** 관리자 화면은 어두운 배경이라 활성/호버 색이 다르다 */
    theme: { type: String, default: 'light' },
    /** 페이지 안의 한 구역만 넘길 때(상품 상세의 후기·문의). 스크롤 위치를 지킨다. */
    preserveScroll: { type: Boolean, default: false },
    /** 가운데 정렬 */
    center: { type: Boolean, default: false },
});

const show = computed(() => (props.paginator?.last_page ?? 1) > 1);

const activeClass = computed(() => (props.theme === 'dark'
    ? 'bg-neutral-100 text-neutral-900'
    : 'bg-neutral-900 text-white'));

const idleClass = computed(() => (props.theme === 'dark'
    ? 'text-neutral-400 hover:bg-neutral-900'
    : 'text-neutral-500 hover:bg-neutral-100'));
</script>

<template>
    <div v-if="show" class="mt-6 flex flex-wrap gap-1" :class="center ? 'justify-center' : ''">
        <Link
            v-for="link in paginator.links"
            :key="link.label"
            :href="link.url ?? '#'"
            :preserve-scroll="preserveScroll"
            class="rounded px-3 py-1 text-sm transition"
            :class="link.active ? activeClass : idleClass"
            v-html="link.label"
        />
    </div>
</template>
