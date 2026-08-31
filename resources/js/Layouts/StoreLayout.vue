<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import StoreHeader from '@/Layouts/Partials/StoreHeader.vue';
import StoreDrawer from '@/Layouts/Partials/StoreDrawer.vue';
import StoreBottomNav from '@/Layouts/Partials/StoreBottomNav.vue';
import StoreFooter from '@/Layouts/Partials/StoreFooter.vue';
import SupportChannelButton from '@/Components/SupportChannelButton.vue';

/**
 * 스토어 공통 레이아웃.
 *
 * 머리·서랍·하단 탭·바닥은 `Partials/` 로 뺐다. 여기 남은 것은 **조립과
 * 서랍 열림 상태**뿐이다 — 서랍은 헤더와 하단 탭 양쪽에서 열 수 있어서
 * 상태를 공통 부모가 들고 있어야 한다.
 */
defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const status = computed(() => page.props.flash?.status);

const drawerOpen = ref(false);

// 페이지가 바뀌면 서랍을 닫는다. 안 그러면 이동 후에도 덮여 있다.
watch(() => page.url, () => {
    drawerOpen.value = false;
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-full flex-col bg-white text-neutral-900">
        <StoreHeader @open-drawer="drawerOpen = true" />

        <StoreDrawer :open="drawerOpen" @close="drawerOpen = false" />

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 pb-24 sm:px-6 lg:pb-8">
            <p
                v-if="status"
                class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
            >
                {{ status }}
            </p>

            <slot />
        </main>

        <!-- 상담 채널. 설정이 없으면 아무것도 안 그린다. -->
        <SupportChannelButton />

        <StoreFooter />

        <StoreBottomNav :drawer-open="drawerOpen" @open-drawer="drawerOpen = true" />
    </div>
</template>
