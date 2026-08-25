<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputField from '@/Components/InputField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SocialLoginButtons from '@/Components/SocialLoginButtons.vue';

defineProps({
    status: { type: String, default: '' },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/login', {
    onFinish: () => form.reset('password'),
});

/*
 * 간편로그인이 실패해 되돌아온 사유. 리다이렉트로 온 에러는 useForm 이 못 보므로
 * (form.errors 는 자기 요청의 응답만 받는다) 공유 errors 를 직접 읽는다.
 */
const generalError = computed(() => usePage().props.errors?.general);
</script>

<template>
    <AuthLayout title="로그인">
        <p
            v-if="status"
            class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
        >
            {{ status }}
        </p>

        <p
            v-if="generalError"
            class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            {{ generalError }}
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <InputField
                v-model="form.email"
                label="이메일"
                type="email"
                autocomplete="username"
                required
                :error="form.errors.email"
            />

            <InputField
                v-model="form.password"
                label="비밀번호"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.password"
            />

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-neutral-600">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="rounded border-neutral-300"
                    >
                    로그인 유지
                </label>

                <Link href="/forgot-password" class="text-sm text-neutral-500 hover:text-neutral-900">
                    비밀번호 찾기
                </Link>
            </div>

            <PrimaryButton :disabled="form.processing">로그인</PrimaryButton>
        </form>

        <div class="mt-6">
            <SocialLoginButtons />
        </div>

        <template #footer>
            계정이 없으신가요?
            <Link href="/register" class="font-medium text-neutral-900 hover:underline">회원가입</Link>
        </template>
    </AuthLayout>
</template>
