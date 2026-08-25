<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    nodes: { type: Array, required: true },
    editingId: { type: Number, default: null },
});

defineEmits(['remove']);
</script>

<template>
    <template v-for="node in nodes" :key="node.id">
        <tr
            class="border-b border-neutral-900"
            :class="editingId === node.id ? 'bg-neutral-900' : ''"
        >
            <td class="py-2">
                <span :style="{ paddingLeft: `${node.depth * 20}px` }" class="inline-block">
                    <span v-if="node.depth > 0" class="mr-1 text-neutral-700">└</span>
                    {{ node.name }}
                </span>
            </td>
            <td class="py-2 font-mono text-xs text-neutral-500">{{ node.slug }}</td>
            <td class="py-2 text-center text-neutral-500">{{ node.sort_order }}</td>
            <td class="py-2 text-center">
                <span
                    class="rounded px-1.5 py-0.5 text-xs"
                    :class="node.is_active
                        ? 'bg-emerald-500/15 text-emerald-300'
                        : 'bg-neutral-700/40 text-neutral-400'"
                >
                    {{ node.is_active ? '노출' : '숨김' }}
                </span>
            </td>
            <td class="py-2 text-right">
                <Link
                    :href="`/admin/categories/${node.id}/edit`"
                    class="text-neutral-400 hover:text-neutral-100"
                >
                    수정
                </Link>
                <button
                    type="button"
                    class="ml-3 text-red-400 hover:text-red-300"
                    @click="$emit('remove', node)"
                >
                    삭제
                </button>
            </td>
        </tr>

        <CategoryTreeRow
            v-if="node.children.length"
            :nodes="node.children"
            :editing-id="editingId"
            @remove="$emit('remove', $event)"
        />
    </template>
</template>
