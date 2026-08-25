<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputField from '@/Components/InputField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

/**
 * 간편로그인 추가입력.
 *
 * 제공자가 이메일을 안 줄 때만 보이는 화면이다(카카오는 개인 개발자 앱에서
 * 이메일 동의항목 권한이 없다). 소셜 신원은 세션에 있으므로 여기서 넘기지 않는다 —
 * 이 폼은 모자란 항목만 받는다.
 */
const props = defineProps({
    provider: { type: String, required: true },
    name: { type: String, default: '' },
});

const providerLabel = computed(() => ({ kakao: '카카오', naver: '네이버' }[props.provider] ?? props.provider));

const form = useForm({
    name: props.name,
    email: '',
});

const submit = () => form.post('/login/social/complete');
</script>

<template>
    <AuthLayout
        title="추가 정보 입력"
        :subtitle="`${providerLabel} 계정에서 이메일을 받지 못했습니다. 가입을 마치려면 이메일이 필요합니다.`"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <InputField
                v-model="form.name"
                label="이름"
                autocomplete="name"
                required
                :error="form.errors.name"
            />

            <InputField
                v-model="form.email"
                label="이메일"
                type="email"
                autocomplete="email"
                required
                :error="form.errors.email"
            />

            <p class="text-xs text-neutral-500">
                입력하신 주소로 인증 메일을 보냅니다. 주문 내역과 계정 안내를 받을 주소입니다.
            </p>

            <PrimaryButton :disabled="form.processing">가입 완료</PrimaryButton>
        </form>

        <template #footer>
            <Link href="/login" class="font-medium text-neutral-900 hover:underline">로그인으로 돌아가기</Link>
        </template>
    </AuthLayout>
</template>
