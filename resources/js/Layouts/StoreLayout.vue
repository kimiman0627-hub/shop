<script setup>
import { computed } from 'vue';
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
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-full flex-col bg-white text-neutral-900">
        <header class="border-b border-neutral-200">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-lg font-semibold">쇼핑몰</Link>

                <nav class="flex items-center gap-4 text-sm">
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
            </div>

            <nav class="border-t border-neutral-100">
                <div class="mx-auto flex max-w-6xl items-center gap-5 overflow-x-auto px-6 py-2.5 text-sm">
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

        <main class="mx-auto w-full max-w-6xl flex-1 px-6 py-8">
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
            <div class="mx-auto max-w-6xl px-6 py-6 text-xs text-neutral-500">
                쇼핑몰 · 로컬 개발 환경
            </div>
        </footer>
    </div>
</template>
