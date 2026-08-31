<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MemberDetailModal from '@/Components/MemberDetailModal.vue';
import Pagination from '@/Components/Pagination.vue';
import { adminInput } from '@/ui';

const props = defineProps({
    members: { type: Object, required: true },
    filters: { type: Object, required: true },
    selectedId: { type: Number, default: null },
    detail: { type: Object, default: null },
    inquiryStatusOptions: { type: Array, required: true },
});

const search = reactive({
    keyword: props.filters.keyword ?? '',
    verified: props.filters.verified ?? '',
});

const apply = () => router.get('/admin/members', { ...search }, {
    preserveState: true,
    replace: true,
});

// 모달은 URL 쿼리로 연다. 새로고침·뒤로가기가 동작한다.
const open = (member) => router.get('/admin/members', { ...search, selected: member.id }, {
    preserveState: true,
    preserveScroll: true,
});

const close = () => router.get('/admin/members', { ...search }, {
    preserveState: true,
    preserveScroll: true,
});

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const isOpen = computed(() => props.selectedId !== null && props.detail !== null);

const inputClass = adminInput;
</script>

<template>
    <AdminLayout title="회원목록">
        <h2 class="text-xl font-semibold tracking-tight">회원</h2>
        <p class="mt-1 text-sm text-neutral-500">
            회원을 클릭하면 주문·결제·배송·문의를 한 화면에서 볼 수 있습니다.
        </p>

        <form class="mt-6 flex flex-wrap gap-2" @submit.prevent="apply">
            <select v-model="search.verified" :class="inputClass">
                <option value="">이메일 인증 전체</option>
                <option value="Y">인증완료</option>
                <option value="N">미인증</option>
            </select>
            <input v-model="search.keyword" type="text" placeholder="이름 · 이메일" :class="inputClass">
            <button type="submit" class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900">
                검색
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-[44rem] mt-6 w-full text-sm">
                <thead class="border-b border-neutral-800 text-left text-neutral-500">
                    <tr>
                        <th class="py-2 font-medium">이름</th>
                        <th class="py-2 font-medium">이메일</th>
                        <th class="py-2 font-medium">전화번호</th>
                        <th class="py-2 text-center font-medium">인증</th>
                        <th class="py-2 text-center font-medium">주문</th>
                        <th class="py-2 text-right font-medium">구매금액</th>
                        <th class="py-2 font-medium">가입일</th>
                        <th class="py-2 font-medium">마지막 로그인</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="m in members.data"
                        :key="m.id"
                        class="cursor-pointer border-b border-neutral-900 hover:bg-neutral-900"
                        @click="open(m)"
                    >
                        <td class="py-3">
                            {{ m.name }}
                            <span
                                v-if="m.pending_inquiries_count > 0"
                                class="ml-2 rounded bg-amber-500/15 px-1.5 py-0.5 text-xs text-amber-300"
                            >
                                미답변 {{ m.pending_inquiries_count }}
                            </span>
                        </td>
                        <td class="py-3 text-neutral-400">{{ m.email }}</td>
                        <td class="py-3 text-neutral-500">{{ m.phone || '-' }}</td>
                        <td class="py-3 text-center">
                            <span
                                class="rounded px-1.5 py-0.5 text-xs"
                                :class="m.email_verified
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : 'bg-neutral-700/40 text-neutral-400'"
                            >
                                {{ m.email_verified ? '완료' : '미인증' }}
                            </span>
                        </td>
                        <td class="py-3 text-center text-neutral-400">
                            {{ m.paid_orders_count }}
                            <span v-if="m.orders_count !== m.paid_orders_count" class="text-neutral-600">
                                / {{ m.orders_count }}
                            </span>
                        </td>
                        <td class="py-3 text-right">{{ won(m.total_spent) }}</td>
                        <td class="py-3 text-neutral-500">{{ m.joined_at }}</td>
                        <td class="py-3 text-neutral-500">{{ m.last_login_at?.slice(0, 10) || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="members.data.length === 0" class="mt-6 text-sm text-neutral-500">
            조건에 맞는 회원이 없습니다.
        </p>

        <Pagination :paginator="members" theme="dark" />

        <MemberDetailModal
            v-if="isOpen"
            :detail="detail"
            :inquiry-status-options="inquiryStatusOptions"
            @close="close"
        />
    </AdminLayout>
</template>
