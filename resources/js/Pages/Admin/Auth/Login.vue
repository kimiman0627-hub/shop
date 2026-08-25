<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    login_id: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/admin/login', {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <Head title="관리자 로그인" />

    <div class="flex min-h-full items-center justify-center bg-neutral-950 px-4 py-12">
        <div class="w-full max-w-sm">
            <p class="text-center text-xl font-semibold text-neutral-100">쇼핑몰 관리자</p>

            <div class="mt-8 rounded-xl border border-neutral-800 bg-neutral-900 p-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium text-neutral-300">로그인 ID</label>
                        <input
                            v-model="form.login_id"
                            type="text"
                            autocomplete="username"
                            required
                            class="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-neutral-100 outline-none transition focus:border-neutral-400"
                            :class="form.errors.login_id ? 'border-red-500' : ''"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-300">비밀번호</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-neutral-100 outline-none transition focus:border-neutral-400"
                        >
                    </div>

                    <p v-if="form.errors.login_id" class="text-sm text-red-400">
                        {{ form.errors.login_id }}
                    </p>

                    <label class="flex items-center gap-2 text-sm text-neutral-400">
                        <input v-model="form.remember" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                        로그인 유지
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-neutral-100 px-4 py-2 text-sm font-medium text-neutral-900 transition hover:bg-white disabled:opacity-50"
                    >
                        로그인
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-neutral-500">
                관리자 계정은 상위 관리자가 생성합니다.
            </p>
        </div>
    </div>
</template>
