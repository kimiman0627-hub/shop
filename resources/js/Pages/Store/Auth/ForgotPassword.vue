<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputField from '@/Components/InputField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    status: { type: String, default: '' },
});

const form = useForm({ email: '' });

const submit = () => form.post('/forgot-password');
</script>

<template>
    <AuthLayout
        title="비밀번호 찾기"
        subtitle="가입한 이메일로 재설정 링크를 보내드립니다."
    >
        <p
            v-if="status"
            class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
        >
            {{ status }}
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

            <PrimaryButton :disabled="form.processing">재설정 링크 받기</PrimaryButton>
        </form>

        <template #footer>
            <Link href="/login" class="font-medium text-neutral-900 hover:underline">로그인으로 돌아가기</Link>
        </template>
    </AuthLayout>
</template>
