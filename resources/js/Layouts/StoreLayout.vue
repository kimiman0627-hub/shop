<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import SupportChannelButton from '@/Components/SupportChannelButton.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const categories = computed(() => page.props.storeCategories ?? []);
const cartCount = computed(() => page.props.cartCount ?? 0);
const status = computed(() => page.props.flash?.status);

const path = computed(() => new URL(page.url, 'http://localhost').pathname);
const isAt = (p) => (p === '/' ? path.value === '/' : path.value.startsWith(p));

/*
 * 좁은 화면 구조는 국내 커머스 앱의 통상적인 형태를 따랐다:
 *   상단 고정 바(햄버거 · 로고 · 장바구니) + 왼쪽 서랍 + 하단 탭바.
 *
 * 회원 메뉴가 9개라 예전처럼 한 줄에 펼치면 글자가 세로로 쪼개졌다.
 * 자주 쓰는 4개(홈·카테고리·장바구니·마이)는 **엄지가 닿는 하단**에 두고,
 * 나머지는 서랍으로 넘겼다.
 */
const drawerOpen = ref(false);

// 페이지가 바뀌면 서랍을 닫는다. 안 그러면 이동 후에도 덮여 있다.
watch(() => page.url, () => {
    drawerOpen.value = false;
});

const myMenu = [
    { href: '/orders', label: '주문내역' },
    { href: '/returns', label: '반품·교환' },
    { href: '/reviews', label: '후기쓰기' },
    { href: '/coupons', label: '내 쿠폰' },
    { href: '/addresses', label: '배송지' },
    { href: '/inquiries', label: '1:1문의' },
    { href: '/profile', label: '내 정보' },
];

const drawerRow = 'flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-neutral-700 transition hover:bg-neutral-100';
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-full flex-col bg-white text-neutral-900">
        <header class="sticky top-0 z-30 border-b border-neutral-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 sm:px-6">
                <!-- 좁은 화면: 서랍 열기 -->
                <button
                    type="button"
                    class="-ml-1 rounded-lg p-1.5 text-neutral-700 transition hover:bg-neutral-100 lg:hidden"
                    aria-label="메뉴 열기"
                    @click="drawerOpen = true"
                >
                    <Icon name="menu" />
                </button>

                <Link href="/" class="shrink-0 text-lg font-semibold tracking-tight">쇼핑몰</Link>

                <!-- 넓은 화면: 전부 펼친다 -->
                <nav class="ml-auto hidden items-center gap-4 text-sm lg:flex">
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
                    <span
                        v-if="cartCount > 0"
                        class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-neutral-900 px-1 text-[10px] font-medium text-white"
                    >
                        {{ cartCount }}
                    </span>
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

        <!-- ── 좁은 화면 서랍 ── -->
        <div
            v-if="drawerOpen"
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            @click="drawerOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-col bg-white shadow-xl transition-transform duration-200 lg:hidden"
            :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="전체 메뉴"
        >
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3">
                <span class="text-lg font-semibold tracking-tight">쇼핑몰</span>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-neutral-500 transition hover:bg-neutral-100"
                    aria-label="닫기"
                    @click="drawerOpen = false"
                >
                    <Icon name="close" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-3 py-4">
                <!-- 계정 -->
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
                <Link href="/products" :class="drawerRow">
                    전체 상품
                    <Icon name="chevron" class="size-4 text-neutral-300" />
                </Link>
                <Link
                    v-for="c in categories"
                    :key="c.id"
                    :href="`/products?category=${c.id}`"
                    :class="drawerRow"
                >
                    {{ c.name }}
                    <Icon name="chevron" class="size-4 text-neutral-300" />
                </Link>

                <p class="mt-4 border-t border-neutral-100 px-3 pt-4 pb-1 text-xs font-medium text-neutral-400">
                    마이 쇼핑
                </p>
                <Link v-for="m in myMenu" :key="m.href" :href="m.href" :class="drawerRow">
                    {{ m.label }}
                    <Icon name="chevron" class="size-4 text-neutral-300" />
                </Link>
            </div>
        </aside>

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

        <footer class="border-t border-neutral-200">
            <div class="mx-auto max-w-6xl px-4 py-6 pb-24 text-xs text-neutral-500 sm:px-6 lg:pb-6">
                쇼핑몰 · 로컬 개발 환경
            </div>
        </footer>

        <!-- ── 좁은 화면 하단 탭 ── 자주 쓰는 것만. 엄지가 닿는 자리다 -->
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-neutral-200 bg-white/95 backdrop-blur lg:hidden">
            <div class="mx-auto grid max-w-6xl grid-cols-4">
                <Link href="/" class="flex flex-col items-center gap-0.5 py-2" :class="isAt('/') ? 'text-neutral-900' : 'text-neutral-400'">
                    <Icon name="home" :solid="isAt('/')" class="size-5" />
                    <span class="text-[11px]">홈</span>
                </Link>

                <button
                    type="button"
                    class="flex flex-col items-center gap-0.5 py-2"
                    :class="drawerOpen ? 'text-neutral-900' : 'text-neutral-400'"
                    @click="drawerOpen = true"
                >
                    <Icon name="category" :solid="drawerOpen" class="size-5" />
                    <span class="text-[11px]">카테고리</span>
                </button>

                <Link href="/cart" class="relative flex flex-col items-center gap-0.5 py-2" :class="isAt('/cart') ? 'text-neutral-900' : 'text-neutral-400'">
                    <span class="relative">
                        <Icon name="bag" :solid="isAt('/cart')" class="size-5" />
                        <span
                            v-if="cartCount > 0"
                            class="absolute -top-1 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-neutral-900 px-1 text-[10px] font-medium text-white"
                        >
                            {{ cartCount }}
                        </span>
                    </span>
                    <span class="text-[11px]">장바구니</span>
                </Link>

                <Link
                    :href="user ? '/profile' : '/login'"
                    class="flex flex-col items-center gap-0.5 py-2"
                    :class="isAt('/profile') ? 'text-neutral-900' : 'text-neutral-400'"
                >
                    <Icon name="user" :solid="isAt('/profile')" class="size-5" />
                    <span class="text-[11px]">{{ user ? '마이' : '로그인' }}</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
