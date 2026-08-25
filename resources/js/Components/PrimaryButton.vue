<script setup>
defineProps({
    type: { type: String, default: 'submit' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="w-full rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <slot />
    </button>
</template>
