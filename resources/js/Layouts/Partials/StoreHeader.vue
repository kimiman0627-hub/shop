<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import CartBadge from '@/Components/CartBadge.vue';
import { myMenu, useStoreNav } from '@/Composables/useStoreNav';

/**
 * 스토어 상단 바 + 카테고리 네비.
 *
 * 넓은 화면(lg↑)에서는 회원 메뉴를 전부 펼치고, 좁은 화면에서는 햄버거와
 * 장바구니만 남긴다 — 메뉴가 9개라 한 줄에 넣으면 글자가 세로로 쪼개진다.
 */
defineEmits(['open-drawer']);

const { user, categories, isAt } = useStoreNav();
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-neutral-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 sm:px-6">
            <button
                type="button"
                class="-ml-1 rounded-lg p-1.5 text-neutral-700 transition hover:bg-neutral-100 lg:hidden"
                aria-label="메뉴 열기"
                @click="$emit('open-drawer')"
            >
                <Icon name="menu" />
            </button>

            <Link href="/" class="shrink-0 text-lg font-semibold tracking-tight">쇼핑몰</Link>

            <!-- 넓은 화면: 전부 펼친다 -->
            <nav class="ml-auto hidden items-center gap-4 text-sm lg:flex">
                <Link href="/cart" class="text-neutral-600 hover:text-neutral-900">
                    장바구니
                    <CartBadge class="ml-1" />
                </Link>

                <template v-if="user">
                    <Link
                        v-for="m in myMenu"
                        :key="m.href"
                        :href="m.href"
                        class="text-neutral-600 hover:text-neutral-900"
                    >
                        {{ m.label }}
                    </Link>
                    <span class="text-neutral-500">{{ user.name }}님</span>
                    <Link
                        v-if="!user.email_verified"
                        href="/email/verify"
                        class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800"
                    >
                        이메일 인증 필요
                    </Link>
                    <Link href="/logout" method="post" as="button" class="text-neutral-500 hover:text-neutral-900">
                        로그아웃
                    </Link>
                </template>
                <template v-else>
                    <Link href="/login" class="text-neutral-500 hover:text-neutral-900">로그인</Link>
                    <Link href="/register" class="text-neutral-500 hover:text-neutral-900">회원가입</Link>
                </template>
            </nav>

            <!-- 좁은 화면: 장바구니만 밖에 남긴다 -->
            <Link
                href="/cart"
                class="relative ml-auto rounded-lg p-1.5 text-neutral-700 transition hover:bg-neutral-100 lg:hidden"
                aria-label="장바구니"
            >
                <Icon name="bag" />
                <CartBadge floating />
            </Link>
        </div>

        <nav class="border-t border-neutral-100">
            <div class="mx-auto flex max-w-6xl items-center gap-5 overflow-x-auto px-4 py-2.5 text-sm sm:px-6">
                <Link
                    href="/products"
                    class="whitespace-nowrap transition"
                    :class="isAt('/products') ? 'font-medium text-neutral-900' : 'text-neutral-600 hover:text-neutral-900'"
                >
                    전체
                </Link>
                <Link
                    v-for="c in categories"
                    :key="c.id"
                    :href="`/products?category=${c.id}`"
                    class="whitespace-nowrap text-neutral-600 transition hover:text-neutral-900"
                >
                    {{ c.name }}
                </Link>
            </div>
        </nav>
    </header>
</template>
