<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    admins: { type: Array, required: true },
});

const page = usePage();
const me = computed(() => page.props.auth.admin);
const errors = computed(() => page.props.errors ?? {});

const suspend = (admin) => {
    if (!confirm(`'${admin.name}' 계정을 정지할까요?\n계정은 삭제되지 않고 로그인만 차단됩니다.`)) {
        return;
    }

    useForm({}).delete(`/admin/settings/admins/${admin.id}`);
};
</script>

<template>
    <AdminLayout title="관리자관리">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight">관리자</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    관리자 계정은 여기서만 생성합니다. 공개 회원가입은 없습니다.
                </p>
            </div>

            <Link
                href="/admin/settings/admins/create"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 transition hover:bg-white"
            >
                관리자 생성
            </Link>
        </div>

        <p
            v-if="errors.general"
            class="mt-4 max-w-4xl rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-300"
        >
            {{ errors.general }}
        </p>

        <table class="mt-6 w-full max-w-5xl text-sm">
            <thead class="border-b border-neutral-800 text-left text-neutral-500">
                <tr>
                    <th class="py-2 font-medium">로그인 ID</th>
                    <th class="py-2 font-medium">이름</th>
                    <th class="py-2 font-medium">역할</th>
                    <th class="py-2 font-medium">상태</th>
                    <th class="py-2 font-medium">최근 로그인</th>
                    <th class="py-2" />
                </tr>
            </thead>
            <tbody>
                <tr v-for="admin in admins" :key="admin.id" class="border-b border-neutral-900">
                    <td class="py-3 font-mono text-xs">
                        {{ admin.login_id }}
                        <span v-if="admin.id === me?.id" class="ml-2 text-xs text-neutral-500">(본인)</span>
                    </td>
                    <td class="py-3">{{ admin.name }}</td>
                    <td class="py-3 text-neutral-400">{{ admin.role_name }}</td>
                    <td class="py-3">
                        <span
                            class="rounded px-1.5 py-0.5 text-xs"
                            :class="admin.status === 'ACTIVE'
                                ? 'bg-emerald-500/15 text-emerald-300'
                                : 'bg-neutral-700/40 text-neutral-400'"
                        >
                            {{ admin.status_label }}
                        </span>
                    </td>
                    <td class="py-3 text-neutral-500">{{ admin.last_login_at ?? '-' }}</td>
                    <td class="py-3 text-right">
                        <Link
                            :href="`/admin/settings/admins/${admin.id}/edit`"
                            class="text-neutral-400 hover:text-neutral-100"
                        >
                            수정
                        </Link>
                        <button
                            v-if="admin.id !== me?.id && admin.status === 'ACTIVE'"
                            type="button"
                            class="ml-4 text-red-400 hover:text-red-300"
                            @click="suspend(admin)"
                        >
                            정지
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="mt-4 max-w-5xl text-xs text-neutral-600">
            관리자는 삭제하지 않고 정지시킵니다. 주문·상품 이력이 관리자를 참조하기 때문입니다.
            마지막 최고관리자와 본인 계정은 정지할 수 없습니다.
        </p>
    </AdminLayout>
</template>
