<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    productId: { type: Number, required: true },
    images: { type: Array, required: true },

    /*
     * GALLERY = 상단 갤러리(첫 장이 목록 썸네일), DETAIL = 상세 설명 이미지.
     * 서버도 같은 값으로 용도를 구분한다 (App\Enums\Product\ProductImageType).
     */
    type: { type: String, default: 'GALLERY' },
    title: { type: String, default: '이미지' },
    hint: { type: String, default: null },
});

// 상세 이미지에는 대표(썸네일) 개념이 없다.
const hasPrimary = computed(() => props.type === 'GALLERY');

const errors = computed(() => usePage().props.errors ?? {});
const fileInput = ref(null);

const uploadForm = useForm({ images: [], type: props.type });

const onPick = (event) => {
    const files = [...event.target.files];

    if (files.length === 0) {
        return;
    }

    uploadForm.images = files;
    uploadForm.post(`/admin/products/${props.productId}/images`, {
        forceFormData: true,
        onFinish: () => {
            uploadForm.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const remove = (image) => {
    if (!confirm('이 이미지를 삭제할까요?')) {
        return;
    }

    router.delete(`/admin/products/${props.productId}/images/${image.id}`, {
        preserveScroll: true,
    });
};

const save = (orderedIds, primaryId) => router.put(
    `/admin/products/${props.productId}/images/order`,
    { ordered_ids: orderedIds, primary_id: primaryId, type: props.type },
    { preserveScroll: true },
);

const setPrimary = (image) => save(props.images.map((i) => i.id), image.id);

const move = (index, delta) => {
    const next = index + delta;

    if (next < 0 || next >= props.images.length) {
        return;
    }

    const ids = props.images.map((i) => i.id);
    [ids[index], ids[next]] = [ids[next], ids[index]];

    save(ids, props.images.find((i) => i.is_primary)?.id ?? null);
};
</script>

<template>
    <section class="max-w-3xl space-y-4 rounded-lg border border-neutral-800 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium">
                    {{ title }} <span class="text-neutral-500">({{ images.length }}장)</span>
                </p>
                <p v-if="hint" class="mt-1 text-xs text-neutral-500">{{ hint }}</p>
            </div>

            <label class="cursor-pointer rounded-lg border border-neutral-700 px-3 py-1.5 text-sm text-neutral-300 hover:bg-neutral-900">
                이미지 추가
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    class="hidden"
                    @change="onPick"
                >
            </label>
        </div>

        <p v-if="uploadForm.processing" class="text-sm text-neutral-400">업로드 중…</p>

        <p v-if="errors.images" class="text-sm text-red-400">{{ errors.images }}</p>
        <p v-if="errors['images.0']" class="text-sm text-red-400">{{ errors['images.0'] }}</p>

        <p v-if="images.length === 0" class="text-sm text-neutral-500">
            <template v-if="hasPrimary">등록된 이미지가 없습니다. 첫 이미지가 자동으로 대표 이미지가 됩니다.</template>
            <template v-else>등록된 상세 이미지가 없습니다.</template>
        </p>

        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                v-for="(image, index) in images"
                :key="image.id"
                class="overflow-hidden rounded-lg border"
                :class="hasPrimary && image.is_primary ? 'border-amber-500/60' : 'border-neutral-800'"
            >
                <div class="aspect-square bg-neutral-900">
                    <img :src="image.url" :alt="image.alt ?? ''" class="h-full w-full object-cover">
                </div>

                <div class="space-y-2 p-2">
                    <template v-if="hasPrimary">
                        <p v-if="image.is_primary" class="text-center text-xs text-amber-300">대표 이미지</p>
                        <button
                            v-else
                            type="button"
                            class="w-full rounded border border-neutral-700 py-1 text-xs text-neutral-300 hover:bg-neutral-900"
                            @click="setPrimary(image)"
                        >
                            대표로 지정
                        </button>
                    </template>
                    <p v-else class="text-center text-xs text-neutral-500">{{ index + 1 }}번째</p>

                    <div class="flex gap-1">
                        <button
                            type="button"
                            :disabled="index === 0"
                            class="flex-1 rounded border border-neutral-800 py-1 text-xs text-neutral-400 disabled:opacity-30"
                            @click="move(index, -1)"
                        >
                            ←
                        </button>
                        <button
                            type="button"
                            :disabled="index === images.length - 1"
                            class="flex-1 rounded border border-neutral-800 py-1 text-xs text-neutral-400 disabled:opacity-30"
                            @click="move(index, 1)"
                        >
                            →
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded border border-red-500/40 py-1 text-xs text-red-400 hover:bg-red-500/10"
                            @click="remove(image)"
                        >
                            삭제
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="hasPrimary" class="text-xs text-neutral-600">
            대표 이미지는 목록의 썸네일로 쓰입니다. 대표를 삭제하면 남은 첫 장이 자동으로 승계합니다.
        </p>
        <p v-else class="text-xs text-neutral-600">
            상세 이미지는 상품 페이지의 '상세정보' 탭에 <strong>여기 순서대로</strong> 세로로 이어 붙습니다.
        </p>
    </section>
</template>
