<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-full items-center justify-center bg-neutral-50 px-4 py-12">
        <div class="w-full max-w-sm">
            <Link href="/" class="block text-center text-xl font-semibold text-neutral-900">
                쇼핑몰
            </Link>

            <div class="mt-8 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h1 class="text-lg font-semibold text-neutral-900">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-neutral-500">{{ subtitle }}</p>

                <div class="mt-6">
                    <slot />
                </div>
            </div>

            <div class="mt-6 text-center text-sm text-neutral-500">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
