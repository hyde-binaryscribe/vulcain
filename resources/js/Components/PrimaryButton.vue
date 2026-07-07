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
        class="inline-flex w-full items-center justify-center rounded-lg bg-red-800 px-4 py-2.5 font-semibold text-white shadow-sm transition hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-700/40 disabled:cursor-not-allowed disabled:opacity-60"
    >
        <slot />
    </button>
</template>
