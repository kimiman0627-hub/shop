<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { myMenu, useStoreNav } from '@/Composables/useStoreNav';

/**
 * 좁은 화면 전체 메뉴 서랍.
 *
 * 계정 → 카테고리 → 마이 쇼핑 순서다. 로그인 여부에 따라 맨 위 블록이 바뀐다 —
 * 비회원에게 '주문내역' 만 잔뜩 보여주는 것보다 로그인 버튼이 먼저다.
 */
defineProps({
    open: { type: Boolean, default: false },
});

defineEmits(['close']);

const { user, categories } = useStoreNav();

const row = 'flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-neutral-700 transition hover:bg-neutral-100';
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-40 bg-black/40 lg:hidden"
        @click="$emit('close')"
    />

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-col bg-white shadow-xl transition-transform duration-200 lg:hidden"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
        aria-label="전체 메뉴"
    >
        <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3">
            <span class="text-lg font-semibold tracking-tight">쇼핑몰</span>
            <button
                type="button"
                class="rounded-lg p-1.5 text-neutral-500 transition hover:bg-neutral-100"
                aria-label="닫기"
                @click="$emit('close')"
            >
                <Icon name="close" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-3 py-4">
            <div class="mb-4 rounded-xl bg-neutral-50 p-4">
                <template v-if="user">
                    <p class="text-sm">
                        <span class="font-semibold">{{ user.name }}</span>님
                    </p>
                    <Link
                        v-if="!user.email_verified"
                        href="/email/verify"
                        class="mt-2 inline-block rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800"
                    >
                        이메일 인증 필요
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="mt-3 block w-full rounded-lg border border-neutral-300 py-2 text-xs text-neutral-600"
                    >
                        로그아웃
                    </Link>
                </template>
                <template v-else>
                    <p class="text-sm text-neutral-600">로그인하고 주문하세요</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <Link href="/login" class="rounded-lg bg-neutral-900 py-2 text-center text-xs font-medium text-white">
                            로그인
                        </Link>
                        <Link href="/register" class="rounded-lg border border-neutral-300 py-2 text-center text-xs">
                            회원가입
                        </Link>
                    </div>
                </template>
            </div>

            <p class="px-3 pb-1 text-xs font-medium text-neutral-400">카테고리</p>
            <Link href="/products" :class="row">
                전체 상품
                <Icon name="chevron" class="size-4 text-neutral-300" />
            </Link>
            <Link
                v-for="c in categories"
                :key="c.id"
                :href="`/products?category=${c.id}`"
                :class="row"
            >
                {{ c.name }}
                <Icon name="chevron" class="size-4 text-neutral-300" />
            </Link>

            <p class="mt-4 border-t border-neutral-100 px-3 pt-4 pb-1 text-xs font-medium text-neutral-400">
                마이 쇼핑
            </p>
            <Link v-for="m in myMenu" :key="m.href" :href="m.href" :class="row">
                {{ m.label }}
                <Icon name="chevron" class="size-4 text-neutral-300" />
            </Link>
        </div>
    </aside>
</template>
