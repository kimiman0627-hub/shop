<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

const props = defineProps({
    addresses: { type: Array, required: true },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

const editingId = ref(null); // null = 목록, 'new' = 새로 추가, 숫자 = 수정 중
const isFormOpen = computed(() => editingId.value !== null);

const emptyForm = () => ({
    label: '', receiver_name: '', receiver_phone: '',
    postcode: '', address1: '', address2: '', is_default: false,
});

const form = useForm(emptyForm());

const openCreate = () => {
    editingId.value = 'new';
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
};

const openEdit = (address) => {
    editingId.value = address.id;
    form.defaults({ ...address });
    form.reset();
    form.clearErrors();
};

const close = () => {
    editingId.value = null;
    form.clearErrors();
};

const submit = () => {
    const onSuccess = () => { editingId.value = null; };

    if (editingId.value === 'new') {
        form.post('/addresses', { preserveScroll: true, onSuccess });
    } else {
        form.put(`/addresses/${editingId.value}`, { preserveScroll: true, onSuccess });
    }
};

const remove = (address) => {
    if (confirm(`'${address.label || address.receiver_name}' 배송지를 삭제할까요?`)) {
        router.delete(`/addresses/${address.id}`, { preserveScroll: true });
    }
};

const setDefault = (address) => {
    router.put(`/addresses/${address.id}/default`, {}, { preserveScroll: true });
};

const inputClass = 'w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900';
</script>

<template>
    <StoreLayout title="배송지 관리">
        <div class="flex items-baseline justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">배송지 관리</h1>
            <button
                v-if="!isFormOpen"
                type="button"
                class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white"
                @click="openCreate"
            >
                새 배송지 추가
            </button>
        </div>
        <p class="mt-2 text-sm text-neutral-500">
            자주 쓰는 배송지를 저장해두면 주문할 때 다시 입력하지 않아도 됩니다.
        </p>

        <p v-if="errors.general" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
            {{ errors.general }}
        </p>

        <!-- 목록 -->
        <div v-if="!isFormOpen" class="mt-6 space-y-3">
            <article
                v-for="a in addresses"
                :key="a.id"
                class="rounded-xl border p-5"
                :class="a.is_default ? 'border-neutral-900' : 'border-neutral-200'"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="flex items-center gap-2 text-sm font-medium">
                            {{ a.label || '배송지' }}
                            <span v-if="a.is_default" class="rounded bg-neutral-900 px-2 py-0.5 text-xs text-white">
                                기본
                            </span>
                        </p>
                        <p class="mt-2 text-sm text-neutral-700">
                            {{ a.receiver_name }} · {{ a.receiver_phone }}
                        </p>
                        <p class="mt-1 text-sm text-neutral-500">
                            ({{ a.postcode }}) {{ a.address1 }} {{ a.address2 }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2 text-xs">
                        <div class="flex gap-3">
                            <button type="button" class="text-neutral-600 hover:underline" @click="openEdit(a)">
                                수정
                            </button>
                            <button type="button" class="text-red-600 hover:underline" @click="remove(a)">
                                삭제
                            </button>
                        </div>
                        <button
                            v-if="!a.is_default"
                            type="button"
                            class="text-neutral-500 hover:underline"
                            @click="setDefault(a)"
                        >
                            기본으로 설정
                        </button>
                    </div>
                </div>
            </article>

            <div v-if="addresses.length === 0" class="rounded-xl border border-dashed border-neutral-300 px-5 py-12 text-center text-neutral-500">
                저장된 배송지가 없습니다. 주문서에서 입력한 배송지를 여기서 저장해두면 다음 주문이 빨라집니다.
            </div>
        </div>

        <!-- 추가·수정 폼 -->
        <form v-else class="mt-6 max-w-lg space-y-4" @submit.prevent="submit">
            <div>
                <label class="text-sm text-neutral-600">별칭 (선택)</label>
                <input v-model="form.label" type="text" placeholder="집, 회사 등" :class="[inputClass, 'mt-1']">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-neutral-600">수령인</label>
                    <input v-model="form.receiver_name" type="text" :class="[inputClass, 'mt-1']">
                    <p v-if="form.errors.receiver_name" class="mt-1 text-xs text-red-600">{{ form.errors.receiver_name }}</p>
                </div>
                <div>
                    <label class="text-sm text-neutral-600">연락처</label>
                    <input v-model="form.receiver_phone" type="text" :class="[inputClass, 'mt-1']">
                    <p v-if="form.errors.receiver_phone" class="mt-1 text-xs text-red-600">{{ form.errors.receiver_phone }}</p>
                </div>
            </div>

            <div>
                <label class="text-sm text-neutral-600">우편번호</label>
                <input v-model="form.postcode" type="text" :class="[inputClass, 'mt-1 w-40']">
                <p v-if="form.errors.postcode" class="mt-1 text-xs text-red-600">{{ form.errors.postcode }}</p>
            </div>

            <div>
                <label class="text-sm text-neutral-600">주소</label>
                <input v-model="form.address1" type="text" :class="[inputClass, 'mt-1']">
                <p v-if="form.errors.address1" class="mt-1 text-xs text-red-600">{{ form.errors.address1 }}</p>
            </div>

            <div>
                <label class="text-sm text-neutral-600">상세주소</label>
                <input v-model="form.address2" type="text" :class="[inputClass, 'mt-1']">
            </div>

            <label class="flex items-center gap-2 text-sm text-neutral-700">
                <input v-model="form.is_default" type="checkbox" class="rounded border-neutral-300">
                기본 배송지로 설정
            </label>

            <div class="flex gap-2 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                    저장
                </button>
                <button type="button" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm text-neutral-600" @click="close">
                    취소
                </button>
            </div>
        </form>
    </StoreLayout>
</template>
