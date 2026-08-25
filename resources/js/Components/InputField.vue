<script setup>
defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    autocomplete: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-neutral-700">{{ label }}</label>

        <input
            v-model="model"
            :type="type"
            :autocomplete="autocomplete"
            :required="required"
            :disabled="disabled"
            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900 disabled:bg-neutral-100"
            :class="error ? 'border-red-400' : ''"
        >

        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
