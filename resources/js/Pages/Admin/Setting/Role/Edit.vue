<script setup>
import { reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    role: { type: Object, required: true },
    permissions: { type: Object, required: true },
    menuTree: { type: Object, required: true },
});

// config/admin/menu.php 트리를 그대로 그리고, 저장된 권한을 얹는다.
const state = reactive(
    Object.fromEntries(
        Object.values(props.menuTree).flatMap((group) =>
            Object.keys(group.children ?? {}).map((code) => [
                code,
                {
                    can_read: props.permissions[code]?.can_read ?? false,
                    can_write: props.permissions[code]?.can_write ?? false,
                },
            ]),
        ),
    ),
);

const infoForm = useForm({
    name: props.role.name,
    description: props.role.description ?? '',
});

const permissionForm = useForm({ permissions: state });

// 쓰기를 켜면 조회도 켠다 — 불가능한 조합을 만들지 않는다 (서버도 같은 규칙).
const onWriteChange = (code) => {
    if (state[code].can_write) {
        state[code].can_read = true;
    }
};

// 조회를 끄면 쓰기도 꺼진다.
const onReadChange = (code) => {
    if (!state[code].can_read) {
        state[code].can_write = false;
    }
};

const saveInfo = () => infoForm.put(`/admin/settings/roles/${props.role.id}`);
const savePermissions = () => permissionForm.put(`/admin/settings/roles/${props.role.id}/permissions`);
</script>

<template>
    <AdminLayout :title="`권한설정 · ${role.name}`">
        <Link href="/admin/settings/roles" class="text-sm text-neutral-500 hover:text-neutral-300">
            &larr; 역할 목록
        </Link>

        <div class="mt-4 flex items-baseline gap-3">
            <h2 class="text-xl font-semibold tracking-tight">{{ role.name }}</h2>
            <span class="font-mono text-xs text-neutral-500">{{ role.code }}</span>
        </div>

        <form class="mt-6 max-w-xl space-y-4 rounded-lg border border-neutral-800 p-4" @submit.prevent="saveInfo">
            <p class="text-sm font-medium">기본 정보</p>

            <div>
                <label class="block text-sm text-neutral-400">역할 이름</label>
                <input
                    v-model="infoForm.name"
                    type="text"
                    class="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                >
                <p v-if="infoForm.errors.name" class="mt-1 text-xs text-red-400">{{ infoForm.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-400">설명</label>
                <input
                    v-model="infoForm.description"
                    type="text"
                    class="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                >
            </div>

            <p class="text-xs text-neutral-600">
                역할 코드는 권한 레코드가 참조하므로 변경할 수 없습니다.
            </p>

            <button
                type="submit"
                :disabled="infoForm.processing"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                기본 정보 저장
            </button>
        </form>

        <form class="mt-6 max-w-2xl rounded-lg border border-neutral-800 p-4" @submit.prevent="savePermissions">
            <p class="text-sm font-medium">메뉴 권한</p>

            <p v-if="permissionForm.errors.permissions" class="mt-2 text-sm text-red-400">
                {{ permissionForm.errors.permissions }}
            </p>
            <p v-if="permissionForm.errors.general" class="mt-2 text-sm text-red-400">
                {{ permissionForm.errors.general }}
            </p>

            <div class="mt-4 space-y-5">
                <div v-for="(group, groupCode) in menuTree" :key="groupCode">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                        {{ group.name }}
                    </p>

                    <div class="overflow-x-auto">
                        <table class="mt-2 w-full text-sm">
                            <tbody>
                                <tr v-for="(child, code) in group.children" :key="code" class="border-b border-neutral-900">
                                    <td class="py-2">{{ child.name }}</td>
                                    <td class="py-2 font-mono text-xs text-neutral-600">{{ code }}</td>
                                    <td class="w-24 py-2">
                                        <label class="flex items-center gap-2 text-neutral-400">
                                            <input
                                                v-model="state[code].can_read"
                                                type="checkbox"
                                                class="rounded border-neutral-600 bg-neutral-950"
                                                @change="onReadChange(code)"
                                            >
                                            조회
                                        </label>
                                    </td>
                                    <td class="w-24 py-2">
                                        <label class="flex items-center gap-2 text-neutral-400">
                                            <input
                                                v-model="state[code].can_write"
                                                type="checkbox"
                                                class="rounded border-neutral-600 bg-neutral-950"
                                                @change="onWriteChange(code)"
                                            >
                                            쓰기
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <button
                type="submit"
                :disabled="permissionForm.processing"
                class="mt-5 rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
            >
                권한 저장
            </button>

            <p class="mt-3 text-xs text-neutral-600">
                본인이 속한 역할의 권한은 수정할 수 없습니다 — 권한 상승을 막기 위해서입니다.
            </p>
        </form>
    </AdminLayout>
</template>
