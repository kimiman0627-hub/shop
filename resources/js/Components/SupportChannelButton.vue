<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/**
 * 상담 채널 플로팅 버튼 (네이버 톡톡 · 카카오 채널 등).
 *
 * **실시간 상담을 자체 구현하지 않는다.** 상담원이 붙어 있어야 의미가 있고
 * 그건 시스템이 아니라 운영의 문제다. 여기서는 외부 채널로 보내기만 한다.
 *
 * URL 은 `config('shop.support.channel.url')` — 비어 있으면 버튼이 안 뜬다.
 */
const channel = computed(() => usePage().props.supportChannel ?? {});

const isEnabled = computed(() => Boolean(channel.value?.url));
</script>

<template>
    <a
        v-if="isEnabled"
        :href="channel.url"
        target="_blank"
        rel="noopener noreferrer"
        class="fixed right-5 bottom-20 z-30 lg:bottom-5 flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-3 text-sm font-medium text-white shadow-lg transition hover:bg-emerald-600"
    >
        <Icon name="chat" class="size-5" />
        {{ channel.label }}
    </a>
</template>
