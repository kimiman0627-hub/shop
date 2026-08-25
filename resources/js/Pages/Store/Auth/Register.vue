<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputField from '@/Components/InputField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SocialLoginButtons from '@/Components/SocialLoginButtons.vue';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    marketing_email_agreed: false,
    marketing_sms_agreed: false,
});

const submit = () => form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <AuthLayout title="회원가입" subtitle="가입 후 이메일 인증이 필요합니다.">
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
                autocomplete="username"
                required
                :error="form.errors.email"
            />

            <InputField
                v-model="form.phone"
                label="휴대폰 번호 (선택)"
                type="tel"
                autocomplete="tel"
                :error="form.errors.phone"
            />

            <InputField
                v-model="form.password"
                label="비밀번호"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password"
            />

            <InputField
                v-model="form.password_confirmation"
                label="비밀번호 확인"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password_confirmation"
            />

            <fieldset class="space-y-2 border-t border-neutral-200 pt-4">
                <legend class="text-xs text-neutral-500">마케팅 수신동의 (선택, 가입 후 언제든 변경 가능)</legend>
                <label class="flex items-center gap-2 text-sm text-neutral-700">
                    <input v-model="form.marketing_email_agreed" type="checkbox" class="rounded border-neutral-300">
                    이메일 수신 동의
                </label>
                <label class="flex items-center gap-2 text-sm text-neutral-700">
                    <input v-model="form.marketing_sms_agreed" type="checkbox" class="rounded border-neutral-300">
                    문자(SMS) 수신 동의
                </label>
            </fieldset>

            <PrimaryButton :disabled="form.processing">가입하기</PrimaryButton>
        </form>

        <div class="mt-6">
            <SocialLoginButtons />
        </div>

        <template #footer>
            이미 계정이 있으신가요?
            <Link href="/login" class="font-medium text-neutral-900 hover:underline">로그인</Link>
        </template>
    </AuthLayout>
</template>
