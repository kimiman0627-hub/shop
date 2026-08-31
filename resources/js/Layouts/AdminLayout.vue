<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AdminSidebar from '@/Layouts/Partials/AdminSidebar.vue';
import AdminHeader from '@/Layouts/Partials/AdminHeader.vue';

/**
 * 관리자 공통 레이아웃.
 *
 * 사이드바·헤더·알림은 `Partials/` 로 뺐다. 여기 남은 것은 **조립과 사이드바
 * 열림 상태**뿐이다 — 헤더의 버튼이 여는 것을 사이드바가 받아야 해서 상태를
 * 공통 부모가 들고 있다.
 */
defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const status = computed(() => page.props.flash?.status);

const sidebarOpen = ref(false);

// 페이지가 바뀌면 열려 있던 사이드바를 닫는다.
watch(() => page.url, () => {
    sidebarOpen.value = false;
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-full bg-neutral-950 text-neutral-100">
        <!-- 좁은 화면에서 사이드바를 열었을 때 뒤를 덮는 막. 누르면 닫힌다. -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/60 lg:hidden"
            @click="sidebarOpen = false"
        />

        <AdminSidebar :open="sidebarOpen" />

        <div class="flex min-w-0 flex-1 flex-col">
            <AdminHeader :title="title" :sidebar-open="sidebarOpen" @open-sidebar="sidebarOpen = true" />

            <main class="flex-1 p-4 sm:p-6">
                <p
                    v-if="status"
                    class="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300"
                >
                    {{ status }}
                </p>

                <slot />
            </main>
        </div>
    </div>
</template>
