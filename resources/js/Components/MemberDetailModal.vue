<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    detail: { type: Object, required: true },
    inquiryStatusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const errors = computed(() => usePage().props.errors ?? {});
const flash = computed(() => usePage().props.flash?.status);

const tabs = [
    { key: 'profile', label: '정보' },
    { key: 'orders', label: '주문' },
    { key: 'payments', label: '결제' },
    { key: 'inquiries', label: '1:1문의' },
    { key: 'coupons', label: '쿠폰' },
    { key: 'memos', label: '메모' },
];

const tab = ref('profile');
const memberId = computed(() => props.detail.profile.id);

const profileForm = useForm({
    name: props.detail.profile.name,
    email: props.detail.profile.email,
    email_verified: props.detail.profile.email_verified,
});

const memoForm = useForm({ content: '' });
const answers = ref({});

const saveProfile = () => profileForm.put(`/admin/members/${memberId.value}`, {
    preserveScroll: true,
    preserveState: true,
});

const addMemo = () => memoForm.post(`/admin/members/${memberId.value}/memos`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => memoForm.reset(),
});

const removeMemo = (memo) => {
    if (!confirm('이 메모를 삭제할까요?')) {
        return;
    }

    router.delete(`/admin/members/${memberId.value}/memos/${memo.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const answer = (inquiry) => {
    const text = (answers.value[inquiry.id] ?? '').trim();

    if (!text) {
        alert('답변 내용을 입력하세요.');
        return;
    }

    router.put(`/admin/members/inquiries/${inquiry.id}/answer`, { answer: text }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { answers.value[inquiry.id] = ''; },
    });
};

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const pendingCount = computed(
    () => props.detail.inquiries.filter((i) => i.status === 'PENDING').length,
);

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <!-- 배경 클릭으로 닫기. Esc 도 동작하게 tabindex 를 준다. -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4"
        tabindex="-1"
        @click.self="emit('close')"
        @keydown.esc="emit('close')"
    >
        <div class="my-8 w-full max-w-4xl rounded-lg border border-neutral-700 bg-neutral-950">
            <!-- 헤더 -->
            <div class="flex items-start justify-between border-b border-neutral-800 px-6 py-4">
                <div>
                    <p class="text-lg font-semibold">{{ detail.profile.name }}</p>
                    <p class="mt-0.5 text-sm text-neutral-500">
                        {{ detail.profile.email }}
                        <span
                            class="ml-2 rounded px-1.5 py-0.5 text-xs"
                            :class="detail.profile.email_verified
                                ? 'bg-emerald-500/15 text-emerald-300'
                                : 'bg-neutral-700/40 text-neutral-400'"
                        >
                            {{ detail.profile.email_verified ? '인증완료' : '미인증' }}
                        </span>
                    </p>
                </div>
                <button type="button" class="text-neutral-500 hover:text-neutral-200" @click="emit('close')">
                    닫기 ✕
                </button>
            </div>

            <!-- 요약 -->
            <dl class="grid grid-cols-2 gap-px border-b border-neutral-800 bg-neutral-800 sm:grid-cols-5">
                <div class="bg-neutral-950 px-4 py-3">
                    <dt class="text-xs text-neutral-500">가입일</dt>
                    <dd class="mt-0.5 text-sm">{{ detail.profile.joined_at?.slice(0, 10) }}</dd>
                </div>
                <div class="bg-neutral-950 px-4 py-3">
                    <dt class="text-xs text-neutral-500">구매 건수</dt>
                    <dd class="mt-0.5 text-sm">{{ detail.stats.paid_orders_count }}건</dd>
                </div>
                <div class="bg-neutral-950 px-4 py-3">
                    <dt class="text-xs text-neutral-500">총 구매금액</dt>
                    <dd class="mt-0.5 text-sm font-medium">{{ won(detail.stats.total_spent) }}</dd>
                </div>
                <div class="bg-neutral-950 px-4 py-3">
                    <dt class="text-xs text-neutral-500">취소·환불</dt>
                    <dd class="mt-0.5 text-sm">{{ detail.stats.canceled_count }}건</dd>
                </div>
                <div class="bg-neutral-950 px-4 py-3">
                    <dt class="text-xs text-neutral-500">최근 주문</dt>
                    <dd class="mt-0.5 text-sm">{{ detail.stats.last_ordered_at?.slice(0, 10) ?? '-' }}</dd>
                </div>
            </dl>

            <p v-if="flash" class="mx-6 mt-4 rounded-lg bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300">
                {{ flash }}
            </p>

            <!-- 탭 -->
            <div class="flex gap-1 border-b border-neutral-800 px-6 pt-4">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    class="rounded-t px-3 py-2 text-sm"
                    :class="tab === t.key
                        ? 'bg-neutral-800 text-neutral-100'
                        : 'text-neutral-500 hover:text-neutral-300'"
                    @click="tab = t.key"
                >
                    {{ t.label }}
                    <span
                        v-if="t.key === 'inquiries' && pendingCount > 0"
                        class="ml-1 rounded bg-amber-500/20 px-1 text-xs text-amber-300"
                    >{{ pendingCount }}</span>
                </button>
            </div>

            <div class="max-h-[55vh] overflow-y-auto px-6 py-5">
                <!-- 정보 수정 -->
                <template v-if="tab === 'profile'">
                <form class="max-w-md space-y-4" @submit.prevent="saveProfile">
                    <div>
                        <label class="block text-sm text-neutral-400">이름</label>
                        <input v-model="profileForm.name" type="text" :class="inputClass">
                        <p v-if="errors.name" class="mt-1 text-xs text-red-400">{{ errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-400">이메일</label>
                        <input v-model="profileForm.email" type="email" :class="inputClass">
                        <p v-if="errors.email" class="mt-1 text-xs text-red-400">{{ errors.email }}</p>
                        <p class="mt-1 text-xs text-neutral-600">
                            이메일을 바꾸면 인증이 자동으로 해제됩니다. 새 주소는 확인된 적이 없습니다.
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-neutral-400">
                        <input v-model="profileForm.email_verified" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                        이메일 인증 완료로 처리
                    </label>
                    <p class="text-xs text-neutral-600">
                        고객이 인증 메일을 받지 못하는 경우 수동으로 처리합니다.
                    </p>

                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
                    >
                        저장
                    </button>
                </form>

                <!--
                    읽기 전용 — 전화번호·수신동의·마지막 로그인·배송지는 회원 본인이
                    '내 정보' / '배송지 관리' 에서 바꾼다. 관리자는 CS 응대용으로 보기만 한다.
                -->
                <dl class="mt-6 grid max-w-md grid-cols-2 gap-x-4 gap-y-3 border-t border-neutral-800 pt-5 text-sm">
                    <div>
                        <dt class="text-xs text-neutral-500">전화번호</dt>
                        <dd class="mt-0.5">{{ detail.profile.phone || '미등록' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-500">마지막 로그인</dt>
                        <dd class="mt-0.5">{{ detail.profile.last_login_at?.slice(0, 16).replace('T', ' ') || '기록 없음' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-500">마케팅 수신동의</dt>
                        <dd class="mt-0.5 flex gap-1">
                            <span
                                class="rounded px-1.5 py-0.5 text-xs"
                                :class="detail.profile.marketing_email_agreed
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : 'bg-neutral-700/40 text-neutral-500'"
                            >이메일 {{ detail.profile.marketing_email_agreed ? 'O' : 'X' }}</span>
                            <span
                                class="rounded px-1.5 py-0.5 text-xs"
                                :class="detail.profile.marketing_sms_agreed
                                    ? 'bg-emerald-500/15 text-emerald-300'
                                    : 'bg-neutral-700/40 text-neutral-500'"
                            >SMS {{ detail.profile.marketing_sms_agreed ? 'O' : 'X' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-500">저장된 배송지</dt>
                        <dd class="mt-0.5">{{ detail.addresses.length }}개</dd>
                    </div>
                </dl>

                <div v-if="detail.addresses.length" class="mt-4 max-w-md space-y-2">
                    <p
                        v-for="a in detail.addresses"
                        :key="a.id"
                        class="rounded-lg border border-neutral-800 px-3 py-2 text-xs text-neutral-400"
                    >
                        <span v-if="a.is_default" class="mr-1 rounded bg-neutral-100 px-1.5 py-0.5 text-neutral-900">기본</span>
                        {{ a.label || a.receiver_name }} · {{ a.receiver_name }} {{ a.receiver_phone }} ·
                        ({{ a.postcode }}) {{ a.address1 }} {{ a.address2 }}
                    </p>
                </div>
                </template>

                <!-- 주문 -->
                <div v-else-if="tab === 'orders'">
                    <div v-if="detail.orders.length" class="space-y-3">
                        <div
                            v-for="o in detail.orders"
                            :key="o.id"
                            class="flex gap-3 rounded-lg border border-neutral-800 p-3"
                        >
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded bg-neutral-900">
                                <img v-if="o.thumbnail_url" :src="o.thumbnail_url" alt="" class="h-full w-full object-cover">
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <a
                                        :href="`/admin/orders/${o.id}`"
                                        class="font-mono text-xs text-sky-300 hover:underline"
                                    >{{ o.order_no }}</a>
                                    <span class="rounded bg-neutral-800 px-1.5 py-0.5 text-xs">{{ o.status_label }}</span>
                                </div>
                                <p class="mt-1 text-sm">{{ o.item_summary }}</p>
                                <p class="mt-0.5 text-xs text-neutral-500">{{ o.ordered_at }}</p>

                                <p v-if="o.tracking_no || o.shipment_status_label" class="mt-1 text-xs text-neutral-400">
                                    {{ o.shipment_status_label }} · {{ o.carrier_name }}
                                    <a
                                        v-if="o.tracking_url"
                                        :href="o.tracking_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="ml-1 font-mono text-sky-300 hover:underline"
                                    >{{ o.tracking_no }}</a>
                                    <span v-else-if="o.tracking_no" class="ml-1 font-mono">{{ o.tracking_no }}</span>
                                </p>
                            </div>

                            <p class="shrink-0 text-sm font-medium">{{ won(o.total_amount) }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-neutral-500">주문 내역이 없습니다.</p>
                </div>

                <!-- 결제 -->
                <div v-else-if="tab === 'payments'">
                    <div v-if="detail.payments.length" class="overflow-x-auto">
                        <table class="min-w-[44rem] w-full text-sm">
                            <thead class="border-b border-neutral-800 text-left text-neutral-500">
                                <tr>
                                    <th class="py-1.5 font-medium">주문번호</th>
                                    <th class="py-1.5 font-medium">수단</th>
                                    <th class="py-1.5 text-right font-medium">금액</th>
                                    <th class="py-1.5 text-center font-medium">상태</th>
                                    <th class="py-1.5 font-medium">처리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in detail.payments" :key="p.id" class="border-b border-neutral-900">
                                    <td class="py-2 font-mono text-xs">{{ p.order_no }}</td>
                                    <td class="py-2">{{ p.method_label }}</td>
                                    <td class="py-2 text-right">{{ won(p.amount) }}</td>
                                    <td class="py-2 text-center">
                                        <span
                                            class="rounded px-1.5 py-0.5 text-xs"
                                            :class="p.status === 'PAID'
                                                ? 'bg-emerald-500/15 text-emerald-300'
                                                : p.status === 'READY'
                                                    ? 'bg-amber-500/15 text-amber-300'
                                                    : 'bg-neutral-700/40 text-neutral-400'"
                                        >{{ p.status_label }}</span>
                                    </td>
                                    <td class="py-2 text-xs text-neutral-500">
                                        <span v-if="p.paid_at">{{ p.paid_at }}</span>
                                        <span v-if="p.confirmed_by"> · {{ p.confirmed_by }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-neutral-500">결제 내역이 없습니다.</p>
                </div>

                <!-- 1:1 문의 -->
                <div v-else-if="tab === 'inquiries'">
                    <div v-if="detail.inquiries.length" class="space-y-4">
                        <div
                            v-for="q in detail.inquiries"
                            :key="q.id"
                            class="rounded-lg border p-4"
                            :class="q.status === 'PENDING' ? 'border-amber-500/40' : 'border-neutral-800'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{{ q.title }}</p>
                                    <p class="mt-0.5 text-xs text-neutral-500">
                                        {{ q.category_label }}
                                        <span v-if="q.order_no"> · {{ q.order_no }}</span>
                                        · {{ q.created_at }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded px-1.5 py-0.5 text-xs"
                                    :class="q.status === 'PENDING'
                                        ? 'bg-amber-500/15 text-amber-300'
                                        : 'bg-emerald-500/15 text-emerald-300'"
                                >{{ q.status_label }}</span>
                            </div>

                            <p class="mt-3 whitespace-pre-line text-sm text-neutral-300">{{ q.content }}</p>

                            <div v-if="q.answer" class="mt-3 rounded bg-neutral-900 p-3">
                                <p class="text-xs text-neutral-500">
                                    답변 · {{ q.answered_by }} · {{ q.answered_at }}
                                </p>
                                <p class="mt-1 whitespace-pre-line text-sm text-neutral-200">{{ q.answer }}</p>
                            </div>

                            <div v-else class="mt-3">
                                <textarea
                                    v-model="answers[q.id]"
                                    rows="3"
                                    placeholder="답변을 입력하세요"
                                    class="w-full rounded border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                />
                                <button
                                    type="button"
                                    class="mt-2 rounded bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-900"
                                    @click="answer(q)"
                                >
                                    답변 등록
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-neutral-500">문의 내역이 없습니다.</p>
                </div>

                <!-- 쿠폰 -->
                <div v-else-if="tab === 'coupons'">
                    <div v-if="detail.coupons.length" class="overflow-x-auto">
                        <table class="min-w-[44rem] w-full text-sm">
                            <thead class="border-b border-neutral-800 text-left text-neutral-500">
                                <tr>
                                    <th class="py-1.5 font-medium">쿠폰</th>
                                    <th class="py-1.5 font-medium">할인</th>
                                    <th class="py-1.5 font-medium">만료일</th>
                                    <th class="py-1.5 text-center font-medium">상태</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in detail.coupons" :key="c.id" class="border-b border-neutral-900">
                                    <td class="py-2">{{ c.name }}</td>
                                    <td class="py-2 text-neutral-400">{{ c.discount_label }}</td>
                                    <td class="py-2 text-neutral-500">{{ c.expires_at }}</td>
                                    <td class="py-2 text-center text-xs">
                                        <span v-if="c.used" class="text-neutral-500">사용완료</span>
                                        <span v-else-if="c.expired" class="text-red-400">만료</span>
                                        <span v-else class="text-emerald-300">사용가능</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-neutral-500">보유 쿠폰이 없습니다.</p>
                </div>

                <!-- 메모 -->
                <div v-else-if="tab === 'memos'">
                    <form class="space-y-2" @submit.prevent="addMemo">
                        <textarea
                            v-model="memoForm.content"
                            rows="3"
                            placeholder="고객 응대 메모 (고객에게 보이지 않습니다)"
                            class="w-full rounded border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="errors.content" class="text-xs text-red-400">{{ errors.content }}</p>
                        <button
                            type="submit"
                            :disabled="memoForm.processing"
                            class="rounded bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-900 disabled:opacity-50"
                        >
                            메모 추가
                        </button>
                    </form>

                    <div v-if="detail.memos.length" class="mt-5 space-y-3">
                        <div
                            v-for="m in detail.memos"
                            :key="m.id"
                            class="rounded-lg border border-neutral-800 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <p class="whitespace-pre-line text-sm">{{ m.content }}</p>
                                <button
                                    type="button"
                                    class="shrink-0 text-xs text-red-400 hover:text-red-300"
                                    @click="removeMemo(m)"
                                >
                                    삭제
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-neutral-600">{{ m.admin_name }} · {{ m.created_at }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-5 text-sm text-neutral-500">메모가 없습니다.</p>
                </div>
            </div>
        </div>
    </div>
</template>
