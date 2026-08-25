<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputField from '@/Components/InputField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <AuthLayout title="비밀번호 재설정">
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
                label="새 비밀번호"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password"
            />

            <InputField
                v-model="form.password_confirmation"
                label="새 비밀번호 확인"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password_confirmation"
            />

            <PrimaryButton :disabled="form.processing">비밀번호 변경</PrimaryButton>
        </form>
    </AuthLayout>
</template>
