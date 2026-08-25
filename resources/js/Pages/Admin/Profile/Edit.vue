<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const admin = computed(() => usePage().props.auth.admin);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.put('/admin/profile/password', {
    onSuccess: () => form.reset(),
});

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="내 계정">
        <h2 class="text-xl font-semibold tracking-tight">내 계정</h2>

        <dl class="mt-6 grid max-w-xl gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-neutral-800 px-4 py-3">
                <dt class="text-sm text-neutral-500">로그인 ID</dt>
                <dd class="mt-1 font-medium">{{ admin?.login_id }}</dd>
            </div>
            <div class="rounded-lg border border-neutral-800 px-4 py-3">
                <dt class="text-sm text-neutral-500">역할</dt>
                <dd class="mt-1 font-medium">{{ admin?.role }}</dd>
            </div>
        </dl>

        <form class="mt-6 max-w-xl space-y-4 rounded-lg border border-neutral-800 p-4" @submit.prevent="submit">
            <p class="text-sm font-medium">비밀번호 변경</p>

            <div>
                <label class="block text-sm text-neutral-400">현재 비밀번호</label>
                <input
                    v-model="form.current_password"
                    type="password"
                    autocomplete="current-password"
                    :class="inputClass"
                >
                <p v-if="form.errors.current_password" class="mt-1 text-xs text-red-400">
                    {{ form.errors.current_password }}
                </p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">새 비밀번호</label>
                <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass">
                <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                <p class="mt-1 text-xs text-neutral-600">10자 이상.</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">새 비밀번호 확인</label>
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    :class="inputClass"
                >
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                변경
            </button>
        </form>
    </AdminLayout>
</template>
