<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import SupportChannelButton from '@/Components/SupportChannelButton.vue';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const categories = computed(() => page.props.storeCategories ?? []);
const cartCount = computed(() => page.props.cartCount ?? 0);
const status = computed(() => page.props.flash?.status);

/*
 * 회원 메뉴가 9개라 좁은 화면에서는 한 줄에 안 들어간다(글자가 세로로 쪼개져 깨졌다).
 * lg 미만에서는 접어두고 버튼으로 연다. 장바구니만 밖에 남긴다 — 가장 자주 누른다.
 */
const menuOpen = ref(false);

// 페이지가 바뀌면 열려 있던 메뉴를 닫는다. 안 그러면 이동 후에도 덮여 있다.
watch(() => page.url, () => {
    menuOpen.value = false;
});

const mobileLink = 'block rounded px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-100';
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-full flex-col bg-white text-neutral-900">
        <header class="border-b border-neutral-200">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <Link href="/" class="shrink-0 text-lg font-semibold">쇼핑몰</Link>

                <!-- 넓은 화면: 전부 펼친다 -->
                <nav class="hidden items-center gap-4 text-sm lg:flex">
                    <Link href="/cart" class="text-neutral-600 hover:text-neutral-900">
                        장바구니
                        <span
                            v-if="cartCount > 0"
                            class="ml-1 rounded-full bg-neutral-900 px-1.5 py-0.5 text-xs text-white"
                        >
                            {{ cartCount }}
                        </span>
                    </Link>

                    <template v-if="user">
                        <Link href="/orders" class="text-neutral-600 hover:text-neutral-900">주문내역</Link>
                        <Link href="/addresses" class="text-neutral-600 hover:text-neutral-900">배송지</Link>
                        <Link href="/coupons" class="text-neutral-600 hover:text-neutral-900">내 쿠폰</Link>
                        <Link href="/reviews" class="text-neutral-600 hover:text-neutral-900">후기쓰기</Link>
                        <Link href="/returns" class="text-neutral-600 hover:text-neutral-900">반품·교환</Link>
                        <Link href="/inquiries" class="text-neutral-600 hover:text-neutral-900">1:1문의</Link>
                        <Link href="/profile" class="text-neutral-600 hover:text-neutral-900">내 정보</Link>
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

                <!-- 좁은 화면: 장바구니 + 메뉴 버튼만 -->
                <div class="flex items-center gap-3 lg:hidden">
                    <Link href="/cart" class="text-sm text-neutral-600">
                        장바구니
                        <span
                            v-if="cartCount > 0"
                            class="ml-1 rounded-full bg-neutral-900 px-1.5 py-0.5 text-xs text-white"
                        >
                            {{ cartCount }}
                        </span>
                    </Link>

                    <button
                        type="button"
                        class="rounded border border-neutral-300 px-2.5 py-1.5 text-sm text-neutral-700"
                        :aria-expanded="menuOpen"
                        aria-label="메뉴 열기"
                        @click="menuOpen = !menuOpen"
                    >
                        {{ menuOpen ? '닫기' : '메뉴' }}
                    </button>
                </div>
            </div>

            <!-- 좁은 화면 펼침 메뉴 -->
            <div v-if="menuOpen" class="border-t border-neutral-100 px-4 py-2 sm:px-6 lg:hidden">
                <template v-if="user">
                    <p class="px-3 py-2 text-sm text-neutral-500">
                        {{ user.name }}님
                        <Link
                            v-if="!user.email_verified"
                            href="/email/verify"
                            class="ml-1 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800"
                        >
                            이메일 인증 필요
                        </Link>
                    </p>

                    <div class="grid grid-cols-2 gap-x-2 sm:grid-cols-3">
                        <Link href="/orders" :class="mobileLink">주문내역</Link>
                        <Link href="/addresses" :class="mobileLink">배송지</Link>
                        <Link href="/coupons" :class="mobileLink">내 쿠폰</Link>
                        <Link href="/reviews" :class="mobileLink">후기쓰기</Link>
                        <Link href="/returns" :class="mobileLink">반품·교환</Link>
                        <Link href="/inquiries" :class="mobileLink">1:1문의</Link>
                        <Link href="/profile" :class="mobileLink">내 정보</Link>
                    </div>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        :class="[mobileLink, 'w-full text-left text-neutral-500']"
                    >
                        로그아웃
                    </Link>
                </template>

                <div v-else class="grid grid-cols-2 gap-x-2">
                    <Link href="/login" :class="mobileLink">로그인</Link>
                    <Link href="/register" :class="mobileLink">회원가입</Link>
                </div>
            </div>

            <nav class="border-t border-neutral-100">
                <div class="mx-auto flex max-w-6xl items-center gap-5 overflow-x-auto px-4 py-2.5 text-sm sm:px-6">
                    <Link href="/products" class="whitespace-nowrap text-neutral-600 hover:text-neutral-900">
                        전체
                    </Link>
                    <Link
                        v-for="c in categories"
                        :key="c.id"
                        :href="`/products?category=${c.id}`"
                        class="whitespace-nowrap text-neutral-600 hover:text-neutral-900"
                    >
                        {{ c.name }}
                    </Link>
                </div>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
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

        <footer class="border-t border-neutral-200">
            <div class="mx-auto max-w-6xl px-4 py-6 text-xs text-neutral-500 sm:px-6">
                쇼핑몰 · 로컬 개발 환경
            </div>
        </footer>
    </div>
</template>
