<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    coupons: { type: Object, required: true },
});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const deactivate = (coupon) => {
    if (!confirm(`'${coupon.name}' 쿠폰을 중지할까요?\n이미 발급된 쿠폰도 사용할 수 없게 됩니다.`)) {
        return;
    }

    router.delete(`/admin/coupons/${coupon.id}`, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout title="쿠폰관리">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight">쿠폰</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    쿠폰은 삭제하지 않고 중지합니다. 사용 이력이 매출과 엮이기 때문입니다.
                </p>
            </div>

            <Link
                href="/admin/coupons/create"
                class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 transition hover:bg-white"
            >
                쿠폰 생성
            </Link>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">쿠폰명</th>
                        <th class="py-2 font-medium">발급 방식</th>
                        <th class="py-2 font-medium">할인</th>
                        <th class="py-2 font-medium">최소주문</th>
                        <th class="py-2 font-medium">유효기간</th>
                        <th class="py-2 text-center font-medium">발급/사용</th>
                        <th class="py-2 text-center font-medium">상태</th>
                        <th class="w-20 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in coupons.data" :key="c.id" class="border-b border-neutral-900">
                        <td class="py-3">
                            <Link :href="`/admin/coupons/${c.id}/edit`" class="hover:underline">{{ c.name }}</Link>
                            <span v-if="c.code" class="ml-2 font-mono text-xs text-neutral-500">{{ c.code }}</span>
                        </td>
                        <td class="py-3 text-neutral-400">{{ c.issue_type_label }}</td>
                        <td class="py-3">{{ c.discount_label }}</td>
                        <td class="py-3 text-neutral-400">
                            {{ c.min_order_amount > 0 ? won(c.min_order_amount) : '-' }}
                        </td>
                        <td class="py-3 text-neutral-400">
                            <span v-if="c.valid_days">발급 후 {{ c.valid_days }}일</span>
                            <span v-if="c.valid_days && c.valid_until"> · </span>
                            <span v-if="c.valid_until">~{{ c.valid_until }}</span>
                        </td>
                        <td class="py-3 text-center text-neutral-400">
                            {{ c.issued_count }} / {{ c.used_count }}
                            <span v-if="c.total_issue_limit" class="text-neutral-600">
                                (한도 {{ c.total_issue_limit }})
                            </span>
                        </td>
                        <td class="py-3 text-center">
                            <span
                                class="rounded px-1.5 py-0.5 text-xs"
                                :class="c.is_active
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : 'bg-neutral-700/40 text-neutral-400'"
                            >
                                {{ c.is_active ? '사용' : '중지' }}
                            </span>
                        </td>
                        <td class="py-3 text-right">
                            <button
                                v-if="c.is_active"
                                type="button"
                                class="text-red-400 hover:text-red-300"
                                @click="deactivate(c)"
                            >
                                중지
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="coupons.data.length === 0" class="mt-6 text-sm text-neutral-500">
            등록된 쿠폰이 없습니다.
        </p>
    </AdminLayout>
</template>
