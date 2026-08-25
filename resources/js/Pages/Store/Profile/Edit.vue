<script setup>
import { useForm } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

// 저장 성공 메시지는 StoreLayout 의 전역 배너가 보여준다(flash.status).
// Fortify 가 넘기는 원본 슬러그는 HandleInertiaRequests 에서 한국어로 바꿔둔다.

const props = defineProps({
    profile: { type: Object, required: true },
});

/*
 * Fortify 가 등록한 PUT /user/profile-information 로 직접 보낸다.
 * useForm 이 Inertia 라 CSRF·에러백(updateProfileInformation)을 알아서 처리한다.
 */
const form = useForm({
    name: props.profile.name,
    email: props.profile.email,
    phone: props.profile.phone ?? '',
    marketing_email_agreed: props.profile.marketing_email_agreed,
    marketing_sms_agreed: props.profile.marketing_sms_agreed,
});

const submit = () => form.put('/user/profile-information', {
    preserveScroll: true,
    errorBag: 'updateProfileInformation',
});

const inputClass = 'mt-1 w-full max-w-sm rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900';
</script>

<template>
    <StoreLayout title="내 정보">
        <h1 class="text-2xl font-semibold tracking-tight">내 정보</h1>

        <dl class="mt-6 grid max-w-sm grid-cols-2 gap-y-2 text-sm text-neutral-500">
            <dt>가입일</dt>
            <dd class="text-neutral-800">{{ profile.joined_at }}</dd>
            <dt>마지막 로그인</dt>
            <dd class="text-neutral-800">{{ profile.last_login_at ?? '이번이 처음이에요' }}</dd>
        </dl>

        <form class="mt-8 max-w-sm space-y-5" @submit.prevent="submit">
            <div>
                <label class="text-sm text-neutral-600">이름</label>
                <input v-model="form.name" type="text" :class="inputClass">
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="text-sm text-neutral-600">이메일</label>
                <input v-model="form.email" type="email" :class="inputClass">
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                <p v-if="!profile.email_verified" class="mt-1 text-xs text-amber-700">
                    이메일을 바꾸면 인증이 다시 필요합니다.
                </p>
            </div>

            <div>
                <label class="text-sm text-neutral-600">휴대폰 번호</label>
                <input v-model="form.phone" type="tel" placeholder="010-0000-0000" :class="inputClass">
                <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                <p class="mt-1 text-xs text-neutral-500">주문서의 주문자 연락처를 자동으로 채우는 데 씁니다.</p>
            </div>

            <fieldset class="space-y-2 border-t border-neutral-200 pt-5">
                <legend class="text-sm font-medium">마케팅 수신동의</legend>
                <p class="text-xs text-neutral-500">
                    할인 쿠폰·재입고 알림 등을 받아보시려면 동의해 주세요. 언제든 해제할 수 있습니다.
                </p>

                <label class="flex items-center gap-2 text-sm text-neutral-700">
                    <input v-model="form.marketing_email_agreed" type="checkbox" class="rounded border-neutral-300">
                    이메일 수신 동의
                </label>
                <label class="flex items-center gap-2 text-sm text-neutral-700">
                    <input v-model="form.marketing_sms_agreed" type="checkbox" class="rounded border-neutral-300">
                    문자(SMS) 수신 동의
                </label>
            </fieldset>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
            >
                저장
            </button>
        </form>
    </StoreLayout>
</template>
