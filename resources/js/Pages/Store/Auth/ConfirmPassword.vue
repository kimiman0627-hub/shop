<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputField from '@/Components/InputField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const form = useForm({ password: '' });

const submit = () => form.post('/user/confirm-password', {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <AuthLayout
        title="비밀번호 확인"
        subtitle="보안을 위해 비밀번호를 한 번 더 입력해 주세요."
    >
        <form class="space-y-4" @submit.prevent="submit">
            <InputField
                v-model="form.password"
                label="비밀번호"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.password"
            />

            <PrimaryButton :disabled="form.processing">확인</PrimaryButton>
        </form>
    </AuthLayout>
</template>
