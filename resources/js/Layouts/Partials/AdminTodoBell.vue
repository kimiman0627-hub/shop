<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/**
 * 관리자 상단 처리대기 알림.
 *
 * 대시보드의 '처리 대기' 와 **같은 데이터**를 본다(`AdminTodoLibrary`) — 두 곳에서
 * 각자 세면 숫자가 어긋난다. 권한이 없는 항목은 서버가 아예 안 내려준다.
 *
 * **건수가 0 인 항목은 목록에서 빼고, 전부 0 이면 배지를 안 그린다.**
 * 할 일이 없는데 빨간 점이 떠 있으면 그때부터 아무도 안 본다.
 */
const page = usePage();

const todos = computed(() => (page.props.adminTodo ?? []).filter((t) => t.count > 0));
const total = computed(() => todos.value.reduce((sum, t) => sum + t.count, 0));

const open = ref(false);

watch(() => page.url, () => {
    open.value = false;
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative rounded px-2 py-1 text-neutral-400 transition hover:text-neutral-100"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Icon name="bell" class="inline-block size-5 align-text-bottom" />
            <span class="ml-1 hidden sm:inline">알림</span>
            <span
                v-if="total > 0"
                class="ml-1 rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-medium text-white"
            >
                {{ total }}
            </span>
        </button>

        <!-- 바깥을 눌러 닫는다 -->
        <div v-if="open" class="fixed inset-0 z-10" @click="open = false" />

        <div
            v-if="open"
            class="absolute right-0 z-20 mt-2 w-72 rounded-lg border border-neutral-700 bg-neutral-900 p-2 shadow-xl sm:w-80"
        >
            <p v-if="todos.length === 0" class="px-3 py-4 text-center text-sm text-neutral-500">
                처리할 일이 없습니다.
            </p>

            <Link
                v-for="todo in todos"
                :key="todo.label"
                :href="todo.href"
                class="block rounded px-3 py-2 transition hover:bg-neutral-800"
                @click="open = false"
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
</template>
