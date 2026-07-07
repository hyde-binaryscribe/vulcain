<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({ type: String, required: true });

const props = defineProps({
    type: { type: String, default: 'text' },
    autofocus: { type: Boolean, default: false },
});

const input = ref(null);

onMounted(() => {
    if (props.autofocus) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        ref="input"
        v-model="model"
        :type="type"
        class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm outline-none transition focus:border-red-700 focus:ring-2 focus:ring-red-700/30"
    />
</template>
