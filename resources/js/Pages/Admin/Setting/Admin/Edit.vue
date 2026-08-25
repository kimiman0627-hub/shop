<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    admin: { type: Object, default: null },
    roleOptions: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
});

const isCreate = computed(() => props.admin === null);
const me = computed(() => usePage().props.auth.admin);
const isSelf = computed(() => props.admin?.id === me.value?.id);

const form = useForm({
    login_id: props.admin?.login_id ?? '',
    name: props.admin?.name ?? '',
    email: props.admin?.email ?? '',
    admin_role_id: props.admin?.role_id ?? props.roleOptions[0]?.id ?? null,
    status: props.admin?.status ?? 'ACTIVE',
    password: '',
});

const passwordForm = useForm({ password: '', password_confirmation: '' });

const submit = () => {
    if (isCreate.value) {
        form.post('/admin/settings/admins', { onFinish: () => form.reset('password') });
        return;
    }

    form.put(`/admin/settings/admins/${props.admin.id}`);
};

const resetPassword = () => passwordForm.put(
    `/admin/settings/admins/${props.admin.id}/password`,
    { onSuccess: () => passwordForm.reset() },
);

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400 disabled:opacity-50';
</script>

<template>
    <AdminLayout :title="isCreate ? '관리자 생성' : `관리자 수정 · ${admin.name}`">
        <Link href="/admin/settings/admins" class="text-sm text-neutral-500 hover:text-neutral-300">
            &larr; 관리자 목록
        </Link>

        <form class="mt-6 max-w-xl space-y-4 rounded-lg border border-neutral-800 p-4" @submit.prevent="submit">
            <div>
                <label class="block text-sm text-neutral-400">로그인 ID</label>
                <input
                    v-model="form.login_id"
                    type="text"
                    :disabled="!isCreate"
                    :class="inputClass"
                >
                <p v-if="form.errors.login_id" class="mt-1 text-xs text-red-400">{{ form.errors.login_id }}</p>
                <p v-if="!isCreate" class="mt-1 text-xs text-neutral-600">로그인 ID는 변경할 수 없습니다.</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">이름</label>
                <input v-model="form.name" type="text" :class="inputClass">
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">이메일 (선택)</label>
                <input v-model="form.email" type="email" :class="inputClass">
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">역할</label>
                <select v-model="form.admin_role_id" :disabled="isSelf" :class="inputClass">
                    <option v-for="role in roleOptions" :key="role.id" :value="role.id">
                        {{ role.name }} ({{ role.code }})
                    </option>
                </select>
                <p v-if="form.errors.admin_role_id" class="mt-1 text-xs text-red-400">{{ form.errors.admin_role_id }}</p>
                <p v-if="isSelf" class="mt-1 text-xs text-neutral-600">
                    본인의 역할은 변경할 수 없습니다 — 권한 상승 차단.
                </p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">상태</label>
                <select v-model="form.status" :disabled="isSelf" :class="inputClass">
                    <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <p v-if="form.errors.status" class="mt-1 text-xs text-red-400">{{ form.errors.status }}</p>
            </div>

            <div v-if="isCreate">
                <label class="block text-sm text-neutral-400">초기 비밀번호</label>
                <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass">
                <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                <p class="mt-1 text-xs text-neutral-600">10자 이상.</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                {{ isCreate ? '생성' : '저장' }}
            </button>
        </form>

        <form
            v-if="!isCreate"
            class="mt-6 max-w-xl space-y-4 rounded-lg border border-neutral-800 p-4"
            @submit.prevent="resetPassword"
        >
            <p class="text-sm font-medium">비밀번호 초기화</p>

            <div>
                <label class="block text-sm text-neutral-400">새 비밀번호</label>
                <input v-model="passwordForm.password" type="password" autocomplete="new-password" :class="inputClass">
                <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-400">
                    {{ passwordForm.errors.password }}
                </p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">새 비밀번호 확인</label>
                <input
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    :class="inputClass"
                >
            </div>

            <button
                type="submit"
                :disabled="passwordForm.processing"
                class="rounded-lg border border-neutral-700 px-3 py-2 text-sm font-medium text-neutral-200 disabled:opacity-50"
            >
                초기화
            </button>

            <p class="text-xs text-neutral-600">
                본인 비밀번호를 바꾸려면 상단의 이름을 눌러 계정 화면으로 가세요. 현재 비밀번호 확인이 필요합니다.
            </p>
        </form>
    </AdminLayout>
</template>
