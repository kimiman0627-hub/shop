<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    roles: { type: Array, required: true },
});

const showCreate = ref(false);

const form = useForm({ code: '', name: '', description: '' });

const submit = () => form.post('/admin/settings/roles', {
    onSuccess: () => {
        form.reset();
        showCreate.value = false;
    },
});

const remove = (role) => {
    if (!confirm(`'${role.name}' 역할을 삭제할까요?`)) {
        return;
    }

    useForm({}).delete(`/admin/settings/roles/${role.id}`);
};
</script>

<template>
    <AdminLayout title="권한설정">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight">역할</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    관리자 1명은 역할 1개를 가집니다. 역할이 접근 가능한 메뉴를 정합니다.
                </p>
            </div>

            <button
                type="button"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 transition hover:bg-white"
                @click="showCreate = !showCreate"
            >
                {{ showCreate ? '취소' : '역할 추가' }}
            </button>
        </div>

        <form
            v-if="showCreate"
            class="mt-6 max-w-2xl rounded-lg border border-neutral-800 p-4"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm text-neutral-400">역할 코드</label>
                    <input
                        v-model="form.code"
                        type="text"
                        placeholder="MANAGER"
                        class="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm uppercase outline-none focus:border-neutral-400"
                    >
                    <p v-if="form.errors.code" class="mt-1 text-xs text-red-400">{{ form.errors.code }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">역할 이름</label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="운영자"
                        class="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    >
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">설명</label>
                    <input
                        v-model="form.description"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    >
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                추가
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full max-w-4xl text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">코드</th>
                        <th class="py-2 font-medium">이름</th>
                        <th class="py-2 font-medium">설명</th>
                        <th class="py-2 font-medium">소속</th>
                        <th class="py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="role in roles" :key="role.id" class="border-b border-neutral-900">
                        <td class="py-3 font-mono text-xs text-neutral-400">{{ role.code }}</td>
                        <td class="py-3">
                            {{ role.name }}
                            <span v-if="role.is_super_admin" class="ml-2 rounded bg-amber-500/15 px-1.5 py-0.5 text-xs text-amber-300">
                                전체 권한
                            </span>
                        </td>
                        <td class="py-3 text-neutral-500">{{ role.description }}</td>
                        <td class="py-3 text-neutral-400">{{ role.admin_count }}명</td>
                        <td class="py-3 text-right">
                            <Link
                                v-if="!role.is_super_admin"
                                :href="`/admin/settings/roles/${role.id}/edit`"
                                class="text-neutral-400 hover:text-neutral-100"
                            >
                                권한 편집
                            </Link>
                            <button
                                v-if="!role.is_super_admin && role.admin_count === 0"
                                type="button"
                                class="ml-4 text-red-400 hover:text-red-300"
                                @click="remove(role)"
                            >
                                삭제
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 max-w-4xl text-xs text-neutral-600">
            최고관리자 역할은 권한 검사를 전부 통과하므로 편집·삭제할 수 없습니다.
            소속 관리자가 있는 역할도 삭제할 수 없습니다.
        </p>
    </AdminLayout>
</template>
