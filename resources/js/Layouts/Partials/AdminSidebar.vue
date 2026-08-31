<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

/**
 * 관리자 사이드바.
 *
 * 메뉴 트리는 서버가 **권한으로 걸러서** 내려준다(`HandleInertiaRequests`).
 * 여기서 다시 거르지 않는다 — 화면이 숨기는 것과 서버가 막는 것은 별개고,
 * 접근 차단은 라우트 미들웨어가 한다 (CLAUDE.md §7.5).
 *
 * 좁은 화면에서는 화면을 거의 다 덮으므로(w-60) 밀어서 여는 서랍이 된다.
 */
defineProps({
    open: { type: Boolean, default: false },
});

const page = usePage();
const menu = computed(() => page.props.adminMenu ?? []);

const currentPath = computed(() => new URL(page.url, 'http://localhost').pathname);
const isActive = (url) => url !== null && currentPath.value.startsWith(new URL(url, 'http://localhost').pathname);
</script>

<template>
    <aside
        class="fixed inset-y-0 left-0 z-40 w-60 shrink-0 overflow-y-auto border-r border-neutral-800 bg-neutral-950 p-4 transition-transform lg:static lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <Link href="/admin" class="block text-sm font-semibold">쇼핑몰 관리자</Link>

        <nav class="mt-6 space-y-5">
            <div v-for="group in menu" :key="group.name">
                <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase">
                    {{ group.name }}
                </p>
                <ul class="mt-2 space-y-1">
                    <li v-for="child in group.children" :key="child.code">
                        <Link
                            v-if="child.url"
                            :href="child.url"
                            class="block rounded px-2 py-1 text-sm transition"
                            :class="isActive(child.url)
                                ? 'bg-neutral-800 text-neutral-100'
                                : 'text-neutral-300 hover:bg-neutral-900'"
                        >
                            {{ child.name }}
                        </Link>

                        <!-- 아직 라우트가 없는 메뉴. 링크로 만들지 않는다. -->
                        <span
                            v-else
                            class="block rounded px-2 py-1 text-sm text-neutral-600"
                            title="아직 구현되지 않은 메뉴입니다"
                        >
                            {{ child.name }}
                        </span>
                    </li>
                </ul>
            </div>

            <p v-if="menu.length === 0" class="text-sm text-neutral-500">
                접근 가능한 메뉴가 없습니다.
            </p>
        </nav>
    </aside>
</template>
