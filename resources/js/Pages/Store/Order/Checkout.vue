<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { storeField } from '@/ui';

const props = defineProps({
    cart: { type: Object, required: true },
    shipping_fee: { type: Number, required: true },
    coupons: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    hasBankAccount: { type: Boolean, required: true },
    // 바로구매로 들어온 주문서. 장바구니는 그대로 남아 있다.
    isDirect: { type: Boolean, default: false },
    savedAddresses: { type: Array, default: () => [] },
});

const user = computed(() => usePage().props.auth.user);

// 기본 배송지가 있으면 처음부터 채워서 보여준다 — 매번 새로 입력하지 않게 하려는 것.
const defaultAddress = props.savedAddresses.find((a) => a.is_default) ?? null;

const form = useForm({
    orderer_name: user.value?.name ?? '',
    orderer_phone: user.value?.phone ?? '',
    orderer_email: user.value?.email ?? '',
    receiver_name: defaultAddress?.receiver_name ?? '',
    receiver_phone: defaultAddress?.receiver_phone ?? '',
    postcode: defaultAddress?.postcode ?? '',
    address1: defaultAddress?.address1 ?? '',
    address2: defaultAddress?.address2 ?? '',
    delivery_memo: '',
    user_coupon_id: null,
    payment_method: props.paymentMethods[0]?.value ?? 'BANK_TRANSFER',
    depositor_name: '',
    // 처음 주문하는 회원에게는 저장을 권한다. 이미 있으면 굳이 또 안 만든다.
    save_address: props.savedAddresses.length === 0,
});

// 목록에서 고른 배송지 id. '직접 입력' 이면 null.
const selectedAddressId = ref(defaultAddress?.id ?? null);

const pickAddress = (address) => {
    selectedAddressId.value = address.id;
    form.receiver_name = address.receiver_name;
    form.receiver_phone = address.receiver_phone;
    form.postcode = address.postcode;
    form.address1 = address.address1;
    form.address2 = address.address2 ?? '';
    // 이미 저장된 배송지를 그대로 쓰는 거라 다시 저장할 필요가 없다.
    form.save_address = false;
};

const pickManual = () => {
    selectedAddressId.value = null;
    form.receiver_name = '';
    form.receiver_phone = '';
    form.postcode = '';
    form.address1 = '';
    form.address2 = '';
};

const isBankTransfer = computed(() => form.payment_method === 'BANK_TRANSFER');

const won = (n) => `${Number(n ?? 0).toLocaleString('ko-KR')}원`;

const discount = computed(() => {
    if (!form.user_coupon_id) {
        return 0;
    }

    return props.coupons.find((c) => c.id === form.user_coupon_id)?.discount ?? 0;
});

const total = computed(
    () => Math.max(0, props.cart.items_total - discount.value) + props.shipping_fee,
);

const copyOrderer = () => {
    selectedAddressId.value = null;
    form.receiver_name = form.orderer_name;
    form.receiver_phone = form.orderer_phone;
};

const submit = () => form.post('/orders');

const inputClass = storeField;
</script>

<template>
    <StoreLayout title="주문서">
        <h1 class="text-2xl font-semibold tracking-tight">주문서</h1>

        <p v-if="form.errors.general" class="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">
            {{ form.errors.general }}
        </p>

        <form class="mt-6 grid gap-8 lg:grid-cols-[1fr_20rem]" @submit.prevent="submit">
            <div class="space-y-6">
                <section class="rounded-lg border border-neutral-200 p-5">
                    <p class="font-medium">주문 상품</p>
                    <div class="mt-4 divide-y divide-neutral-100">
                        <p
                            v-if="isDirect"
                            class="mb-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs text-neutral-600"
                        >
                            바로 구매 주문입니다. 장바구니에 담아두신 상품은 그대로 남아 있습니다.
                        </p>

                        <div v-for="item in cart.items" :key="item.variant_id" class="flex justify-between py-2 text-sm">
                            <div>
                                <p>{{ item.product_name }}</p>
                                <p v-if="item.option_label" class="text-neutral-500">
                                    {{ item.option_label }} · {{ item.quantity }}개
                                </p>
                                <p v-else class="text-neutral-500">{{ item.quantity }}개</p>
                            </div>
                            <p>{{ won(item.subtotal) }}</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-lg border border-neutral-200 p-5">
                    <p class="font-medium">주문자</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm text-neutral-600">이름</label>
                            <input v-model="form.orderer_name" type="text" :class="inputClass">
                            <p v-if="form.errors.orderer_name" class="mt-1 text-xs text-red-600">
                                {{ form.errors.orderer_name }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm text-neutral-600">연락처</label>
                            <input v-model="form.orderer_phone" type="tel" placeholder="010-0000-0000" :class="inputClass">
                            <p v-if="form.errors.orderer_phone" class="mt-1 text-xs text-red-600">
                                {{ form.errors.orderer_phone }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600">
                            이메일 <span class="text-neutral-400">(선택)</span>
                        </label>
                        <input v-model="form.orderer_email" type="email" :class="inputClass">
                        <p v-if="form.errors.orderer_email" class="mt-1 text-xs text-red-600">
                            {{ form.errors.orderer_email }}
                        </p>
                    </div>

                </section>

                <section class="space-y-4 rounded-lg border border-neutral-200 p-5">
                    <div class="flex items-center justify-between">
                        <p class="font-medium">배송지</p>
                        <button type="button" class="text-sm text-neutral-500 hover:text-neutral-900" @click="copyOrderer">
                            주문자와 동일
                        </button>
                    </div>

                    <!-- 저장된 배송지가 있으면 골라 쓸 수 있다. 없으면 그냥 아래 폼만 보인다. -->
                    <div v-if="savedAddresses.length" class="flex flex-wrap gap-2">
                        <button
                            v-for="a in savedAddresses"
                            :key="a.id"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-left text-xs"
                            :class="selectedAddressId === a.id
                                ? 'border-neutral-900 bg-neutral-900 text-white'
                                : 'border-neutral-300 text-neutral-600 hover:border-neutral-400'"
                            @click="pickAddress(a)"
                        >
                            {{ a.label || a.receiver_name }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-xs"
                            :class="selectedAddressId === null
                                ? 'border-neutral-900 bg-neutral-900 text-white'
                                : 'border-neutral-300 text-neutral-600 hover:border-neutral-400'"
                            @click="pickManual"
                        >
                            직접 입력
                        </button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm text-neutral-600">수령인</label>
                            <input v-model="form.receiver_name" type="text" :class="inputClass">
                            <p v-if="form.errors.receiver_name" class="mt-1 text-xs text-red-600">
                                {{ form.errors.receiver_name }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm text-neutral-600">연락처</label>
                            <input v-model="form.receiver_phone" type="tel" :class="inputClass">
                            <p v-if="form.errors.receiver_phone" class="mt-1 text-xs text-red-600">
                                {{ form.errors.receiver_phone }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600">우편번호</label>
                        <input v-model="form.postcode" type="text" class="mt-1 w-40 rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900">
                        <p v-if="form.errors.postcode" class="mt-1 text-xs text-red-600">{{ form.errors.postcode }}</p>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600">주소</label>
                        <input v-model="form.address1" type="text" :class="inputClass">
                        <p v-if="form.errors.address1" class="mt-1 text-xs text-red-600">{{ form.errors.address1 }}</p>
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600">상세주소</label>
                        <input v-model="form.address2" type="text" :class="inputClass">
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600">배송 메모</label>
                        <input v-model="form.delivery_memo" type="text" :class="inputClass">
                    </div>

                    <label
                        v-if="selectedAddressId === null"
                        class="flex items-center gap-2 text-sm text-neutral-700"
                    >
                        <input v-model="form.save_address" type="checkbox" class="rounded border-neutral-300">
                        이 배송지를 저장
                    </label>
                </section>

                <section class="space-y-4 rounded-lg border border-neutral-200 p-5">
                    <p class="font-medium">결제 수단</p>

                    <div class="space-y-2">
                        <label
                            v-for="m in paymentMethods"
                            :key="m.value"
                            class="flex items-center gap-3 rounded-lg border px-4 py-3 text-sm"
                            :class="form.payment_method === m.value ? 'border-neutral-900' : 'border-neutral-200'"
                        >
                            <input v-model="form.payment_method" type="radio" :value="m.value">
                            {{ m.label }}
                        </label>
                    </div>
                    <p v-if="form.errors.payment_method" class="text-xs text-red-600">
                        {{ form.errors.payment_method }}
                    </p>

                    <template v-if="isBankTransfer">
                        <div
                            v-if="!hasBankAccount"
                            class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"
                        >
                            입금 계좌가 설정되지 않았습니다. 주문할 수 없습니다.
                        </div>

                        <template v-else>
                            <div>
                                <label class="block text-sm text-neutral-600">입금자명</label>
                                <input
                                    v-model="form.depositor_name"
                                    type="text"
                                    :placeholder="form.orderer_name || '주문자명과 동일'"
                                    :class="inputClass"
                                >
                                <p class="mt-1 text-xs text-neutral-500">
                                    주문자와 다른 이름으로 입금하실 경우 입력하세요.
                                </p>
                            </div>

                            <div class="rounded-lg bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
                                주문 후 입금 계좌를 안내해 드립니다.
                                기한 내 입금하지 않으면 주문이 자동 취소됩니다.
                            </div>
                        </template>
                    </template>
                </section>
            </div>

            <aside class="h-fit space-y-4 rounded-lg border border-neutral-200 p-5">
                <p class="font-medium">결제 금액</p>

                <div>
                    <label class="block text-sm text-neutral-600">쿠폰</label>
                    <select v-model="form.user_coupon_id" :class="inputClass">
                        <option :value="null">사용 안 함</option>
                        <option
                            v-for="c in coupons"
                            :key="c.id"
                            :value="c.id"
                            :disabled="!c.applicable"
                        >
                            {{ c.name }} (−{{ won(c.discount) }})
                            {{ c.applicable ? '' : `· ${won(c.min_order_amount)} 이상` }}
                        </option>
                    </select>
                    <p v-if="form.errors.user_coupon_id" class="mt-1 text-xs text-red-600">
                        {{ form.errors.user_coupon_id }}
                    </p>
                </div>

                <dl class="space-y-2 border-t border-neutral-200 pt-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">상품 합계</dt>
                        <dd>{{ won(cart.items_total) }}</dd>
                    </div>
                    <div v-if="discount > 0" class="flex justify-between text-red-600">
                        <dt>쿠폰 할인</dt>
                        <dd>−{{ won(discount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">배송비</dt>
                        <dd>{{ shipping_fee === 0 ? '무료' : won(shipping_fee) }}</dd>
                    </div>
                </dl>

                <div class="flex justify-between border-t border-neutral-200 pt-4">
                    <span class="font-medium">총 결제금액</span>
                    <span class="text-lg font-semibold">{{ won(total) }}</span>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || cart.has_issue || (isBankTransfer && !hasBankAccount)"
                    class="w-full rounded-lg bg-neutral-900 px-4 py-3 text-sm font-medium text-white disabled:cursor-not-allowed disabled:bg-neutral-300"
                >
                    {{ form.processing ? '처리 중…' : '주문하기' }}
                </button>

                <p class="text-center text-xs text-neutral-400">
                    주문 시 재고가 예약됩니다.
                </p>
            </aside>
        </form>
    </StoreLayout>
</template>
