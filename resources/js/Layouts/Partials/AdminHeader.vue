<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import AdminTodoBell from '@/Layouts/Partials/AdminTodoBell.vue';

/**
 * 관리자 상단 바. 화면 제목 · 처리대기 알림 · 계정.
 */
defineProps({
    title: { type: String, required: true },
    sidebarOpen: { type: Boolean, default: false },
});

defineEmits(['open-sidebar']);

const admin = computed(() => usePage().props.auth.admin);
</script>

<template>
    <header class="flex items-center justify-between gap-2 border-b border-neutral-800 px-4 py-3 sm:px-6">
        <div class="flex min-w-0 items-center gap-2">
            <button
                type="button"
                class="rounded border border-neutral-700 px-2 py-1 text-sm text-neutral-300 lg:hidden"
                aria-label="메뉴 열기"
                :aria-expanded="sidebarOpen"
                @click="$emit('open-sidebar')"
            >
                <Icon name="menu" class="size-5" />
            </button>
            <h1 class="truncate text-sm font-medium">{{ title }}</h1>
        </div>

        <div class="flex shrink-0 items-center gap-2 text-sm sm:gap-4">
            <AdminTodoBell />

            <Link href="/admin/profile" class="truncate text-neutral-400 transition hover:text-neutral-100">
                {{ admin?.name }}
                <span v-if="admin?.role" class="hidden text-neutral-600 sm:inline">· {{ admin.role }}</span>
            </Link>

            <Link
                href="/admin/logout"
                method="post"
                as="button"
                class="text-neutral-400 transition hover:text-neutral-100"
            >
                로그아웃
            </Link>
        </div>
    </header>
</template>
