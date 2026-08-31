import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * 스토어 네비게이션이 쓰는 값들.
 *
 * 헤더(넓은 화면) · 서랍(좁은 화면) · 하단 탭이 **같은 메뉴 목록을 봐야 한다** —
 * 세 곳에 따로 적어두면 메뉴를 하나 추가할 때 한 군데를 반드시 빠뜨린다.
 */

/** 회원 전용 메뉴. 넓은 화면 헤더와 서랍이 공유한다. */
export const myMenu = [
    { href: '/orders', label: '주문내역' },
    { href: '/returns', label: '반품·교환' },
    { href: '/reviews', label: '후기쓰기' },
    { href: '/coupons', label: '내 쿠폰' },
    { href: '/addresses', label: '배송지' },
    { href: '/inquiries', label: '1:1문의' },
    { href: '/profile', label: '내 정보' },
];

export function useStoreNav() {
    const page = usePage();

    const path = computed(() => new URL(page.url, 'http://localhost').pathname);

    return {
        user: computed(() => page.props.auth.user),
        categories: computed(() => page.props.storeCategories ?? []),
        cartCount: computed(() => page.props.cartCount ?? 0),

        /** 현재 화면이 이 경로인가. 홈만 정확히 일치로 본다(모든 경로가 '/' 로 시작한다). */
        isAt: (p) => (p === '/' ? path.value === '/' : path.value.startsWith(p)),
    };
}
