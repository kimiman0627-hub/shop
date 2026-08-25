<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    status: { type: String, default: '' },
});

const form = useForm({});

const submit = () => form.post('/email/verification-notification');

const linkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <AuthLayout
        title="이메일 인증"
        subtitle="가입하신 이메일로 인증 링크를 보냈습니다. 메일함을 확인해 주세요."
    >
        <p
            v-if="linkSent"
            class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
        >
            인증 링크를 다시 보냈습니다.
        </p>

        <form @submit.prevent="submit">
            <PrimaryButton :disabled="form.processing">인증 메일 다시 보내기</PrimaryButton>
        </form>

        <template #footer>
            <Link
                href="/logout"
                method="post"
                as="button"
                class="font-medium text-neutral-900 hover:underline"
            >
                로그아웃
            </Link>
        </template>
    </AuthLayout>
</template>
