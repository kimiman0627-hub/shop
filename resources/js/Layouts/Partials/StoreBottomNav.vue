<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import CartBadge from '@/Components/CartBadge.vue';
import { useStoreNav } from '@/Composables/useStoreNav';

/**
 * 좁은 화면 하단 탭.
 *
 * **자주 쓰는 4개만 둔다.** 엄지가 닿는 자리이고, 늘리면 아이콘이 좁아져
 * 오히려 누르기 어려워진다(통상 5개가 상한이다). 나머지는 서랍으로 간다.
 */
defineProps({
    /** 서랍이 열려 있는지. '카테고리' 탭의 활성 표시에 쓴다. */
    drawerOpen: { type: Boolean, default: false },
});

defineEmits(['open-drawer']);

const { user, isAt } = useStoreNav();

const tab = 'flex flex-col items-center gap-0.5 py-2';
const label = 'text-[11px]';
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-neutral-200 bg-white/95 backdrop-blur lg:hidden">
        <div class="mx-auto grid max-w-6xl grid-cols-4">
            <Link href="/" :class="[tab, isAt('/') ? 'text-neutral-900' : 'text-neutral-400']">
                <Icon name="home" :solid="isAt('/')" class="size-5" />
                <span :class="label">홈</span>
            </Link>

            <button
                type="button"
                :class="[tab, drawerOpen ? 'text-neutral-900' : 'text-neutral-400']"
                @click="$emit('open-drawer')"
            >
                <Icon name="category" :solid="drawerOpen" class="size-5" />
                <span :class="label">카테고리</span>
            </button>

            <Link href="/cart" :class="[tab, 'relative', isAt('/cart') ? 'text-neutral-900' : 'text-neutral-400']">
                <span class="relative">
                    <Icon name="bag" :solid="isAt('/cart')" class="size-5" />
                    <CartBadge floating />
                </span>
                <span :class="label">장바구니</span>
            </Link>

            <Link
                :href="user ? '/profile' : '/login'"
                :class="[tab, isAt('/profile') ? 'text-neutral-900' : 'text-neutral-400']"
            >
                <Icon name="user" :solid="isAt('/profile')" class="size-5" />
                <span :class="label">{{ user ? '마이' : '로그인' }}</span>
            </Link>
        </div>
    </nav>
</template>
