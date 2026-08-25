<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const admin = computed(() => page.props.auth.admin);
const menu = computed(() => page.props.adminMenu ?? []);

/*
 * 상단 알림 = 대시보드의 '처리 대기' 와 같은 데이터(AdminTodoLibrary).
 * 건수가 0 인 항목은 목록에서 빼고, 전부 0 이면 배지 자체를 안 그린다 —
 * 할 일이 없는데 빨간 점이 떠 있으면 그때부터 아무도 안 본다.
 */
const todos = computed(() => (page.props.adminTodo ?? []).filter((t) => t.count > 0));
const todoTotal = computed(() => todos.value.reduce((sum, t) => sum + t.count, 0));

const showTodos = ref(false);
const status = computed(() => page.props.flash?.status);
const currentPath = computed(() => new URL(page.url, 'http://localhost').pathname);

const isActive = (url) => url !== null && currentPath.value.startsWith(new URL(url, 'http://localhost').pathname);
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-full bg-neutral-950 text-neutral-100">
        <aside class="w-60 shrink-0 border-r border-neutral-800 p-4">
            <Link href="/admin" class="block text-sm font-semibold">쇼핑몰 관리자</Link>

            <nav class="mt-6 space-y-5">
                <div v-for="group in menu" :key="group.name">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">
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

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-neutral-800 px-6 py-3">
                <h1 class="text-sm font-medium">{{ title }}</h1>

                <div class="flex items-center gap-4 text-sm">
                    <!-- 처리 대기 알림 -->
                    <div class="relative">
                        <button
                            type="button"
                            class="relative rounded px-2 py-1 text-neutral-400 transition hover:text-neutral-100"
                            @click="showTodos = !showTodos"
                        >
                            <span aria-hidden="true">🔔</span>
                            <span class="ml-1">알림</span>
                            <span
                                v-if="todoTotal > 0"
                                class="ml-1 rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-medium text-white"
                            >
                                {{ todoTotal }}
                            </span>
                        </button>

                        <!-- 바깥을 눌러 닫는다 -->
                        <div v-if="showTodos" class="fixed inset-0 z-10" @click="showTodos = false" />

                        <div
                            v-if="showTodos"
                            class="absolute right-0 z-20 mt-2 w-80 rounded-lg border border-neutral-700 bg-neutral-900 p-2 shadow-xl"
                        >
                            <p v-if="todos.length === 0" class="px-3 py-4 text-center text-sm text-neutral-500">
                                처리할 일이 없습니다.
                            </p>

                            <Link
                                v-for="todo in todos"
                                :key="todo.label"
                                :href="todo.href"
                                class="block rounded px-3 py-2 transition hover:bg-neutral-800"
                                @click="showTodos = false"
                            >
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-sm text-neutral-100">{{ todo.label }}</span>
                                    <span class="rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-medium text-red-300">
                                        {{ todo.count }}
                                    </span>
                                </span>
                                <span class="mt-0.5 block text-xs text-neutral-500">{{ todo.hint }}</span>
                            </Link>
                        </div>
                    </div>

                    <Link href="/admin/profile" class="text-neutral-400 transition hover:text-neutral-100">
                        {{ admin?.name }}
                        <span v-if="admin?.role" class="text-neutral-600">· {{ admin.role }}</span>
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

            <main class="flex-1 p-6">
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
