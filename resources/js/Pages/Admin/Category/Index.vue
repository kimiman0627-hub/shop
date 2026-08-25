<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CategoryTreeRow from '@/Components/CategoryTreeRow.vue';

const props = defineProps({
    tree: { type: Array, required: true },
    parentOptions: { type: Array, required: true },
    maxDepth: { type: Number, required: true },
    editing: { type: Object, default: null },
});

const isEdit = computed(() => props.editing !== null);

const blank = () => ({
    parent_id: null,
    name: '',
    slug: '',
    sort_order: 0,
    is_active: true,
});

const form = useForm(props.editing ? { ...props.editing, slug: props.editing.slug ?? '' } : blank());

// 수정 링크를 누르면 같은 페이지가 editing 과 함께 다시 온다. 폼을 갈아끼운다.
watch(() => props.editing, (next) => {
    form.defaults(next ? { ...next, slug: next.slug ?? '' } : blank());
    form.reset();
    form.clearErrors();
});

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/categories/${props.editing.id}`);
        return;
    }

    form.post('/admin/categories', { onSuccess: () => form.reset() });
};

const cancelEdit = () => router.get('/admin/categories');

const remove = (node) => {
    if (!confirm(`'${node.name}' 카테고리를 삭제할까요?`)) {
        return;
    }

    router.delete(`/admin/categories/${node.id}`);
};

const inputClass = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm outline-none focus:border-neutral-400';
</script>

<template>
    <AdminLayout title="카테고리">
        <div class="flex items-baseline justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight">카테고리</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    최대 {{ maxDepth }}단계까지 만들 수 있습니다.
                </p>
            </div>
        </div>

        <div class="mt-6 flex gap-6">
            <div class="min-w-0 flex-1">
                <table class="w-full text-sm">
                    <thead class="border-b border-neutral-800 text-left text-neutral-500">
                        <tr>
                            <th class="py-2 font-medium">카테고리명</th>
                            <th class="py-2 font-medium">URL 주소</th>
                            <th class="w-20 py-2 text-center font-medium">정렬</th>
                            <th class="w-20 py-2 text-center font-medium">노출</th>
                            <th class="w-28 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <CategoryTreeRow
                            :nodes="tree"
                            :editing-id="editing?.id ?? null"
                            @remove="remove"
                        />
                    </tbody>
                </table>

                <p v-if="tree.length === 0" class="mt-6 text-sm text-neutral-500">
                    등록된 카테고리가 없습니다. 오른쪽 폼으로 첫 카테고리를 만드세요.
                </p>
            </div>

            <form
                class="w-80 shrink-0 space-y-4 self-start rounded-lg border border-neutral-800 p-4"
                @submit.prevent="submit"
            >
                <p class="text-sm font-medium">
                    {{ isEdit ? `수정 · ${editing.name}` : '카테고리 추가' }}
                </p>

                <div>
                    <label class="block text-sm text-neutral-400">상위 카테고리</label>
                    <select v-model="form.parent_id" :class="inputClass">
                        <option :value="null">최상위</option>
                        <option
                            v-for="opt in parentOptions"
                            :key="opt.id"
                            :value="opt.id"
                            :disabled="!opt.selectable"
                        >
                            {{ opt.label }}{{ opt.selectable ? '' : ' (최대 깊이)' }}
                        </option>
                    </select>
                    <p v-if="form.errors.parent_id" class="mt-1 text-xs text-red-400">
                        {{ form.errors.parent_id }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">카테고리명</label>
                    <input v-model="form.name" type="text" :class="inputClass">
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">URL 주소</label>
                    <input
                        v-model="form.slug"
                        type="text"
                        placeholder="비워두면 이름으로 자동 생성"
                        :class="inputClass"
                    >
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-400">{{ form.errors.slug }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-400">정렬 순서</label>
                    <input v-model.number="form.sort_order" type="number" min="0" :class="inputClass">
                    <p v-if="form.errors.sort_order" class="mt-1 text-xs text-red-400">
                        {{ form.errors.sort_order }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-neutral-400">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-neutral-600 bg-neutral-950">
                    노출
                </label>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-900 disabled:opacity-50"
                    >
                        {{ isEdit ? '저장' : '추가' }}
                    </button>

                    <button
                        v-if="isEdit"
                        type="button"
                        class="rounded-lg border border-neutral-700 px-3 py-2 text-sm text-neutral-300"
                        @click="cancelEdit"
                    >
                        취소
                    </button>
                </div>

                <p v-if="isEdit" class="text-xs text-neutral-600">
                    상위를 옮기면 하위 카테고리도 함께 이동합니다.
                    자기 자신이나 하위는 상위로 지정할 수 없습니다.
                </p>
            </form>
        </div>
    </AdminLayout>
</template>
