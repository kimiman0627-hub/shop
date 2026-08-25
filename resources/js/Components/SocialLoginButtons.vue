<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * 카카오·네이버 간편로그인 버튼.
 *
 * 로그인·가입 화면 둘 다 같은 링크를 쓴다 — 계정이 있으면 로그인,
 * 없으면 그 자리에서 가입까지 끝난다(SocialLoginLibrary::findOrCreate).
 * 그래서 "간편가입" 이 따로 있지 않고 이 버튼 하나가 둘 다 한다.
 *
 * 제공자별로 Client ID 가 .env 에 없으면 서버가 아예 내려주지 않는다
 * (HandleInertiaRequests) — 눌러도 실패할 버튼을 화면에 남기지 않는다.
 */
const socialLogin = computed(() => usePage().props.socialLogin ?? {});

const providers = [
    { key: 'kakao', label: '카카오로 계속하기', className: 'bg-[#FEE500] text-[#191600] hover:brightness-95' },
    { key: 'naver', label: '네이버로 계속하기', className: 'bg-[#03C75A] text-white hover:brightness-95' },
];

const visibleProviders = computed(() => providers.filter((p) => socialLogin.value?.[p.key]));
</script>

<template>
    <div v-if="visibleProviders.length" class="space-y-4">
        <div class="flex items-center gap-3 text-xs text-neutral-400">
            <span class="h-px flex-1 bg-neutral-200" />
            간편로그인
            <span class="h-px flex-1 bg-neutral-200" />
        </div>

        <div class="space-y-2">
            <a
                v-for="p in visibleProviders"
                :key="p.key"
                :href="`/login/${p.key}/redirect`"
                class="block rounded-lg px-4 py-2.5 text-center text-sm font-medium transition"
                :class="p.className"
            >
                {{ p.label }}
            </a>
        </div>
    </div>
</template>
