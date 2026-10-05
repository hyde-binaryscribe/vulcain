<script setup>
// Sous-onglets « pills » pour découper le contenu d'un dashboard (distincts de
// la barre d'onglets de page, en soulignement).
defineProps({
    tabs: { type: Array, default: () => [] },
    modelValue: { type: String, default: '' },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="t in tabs"
            :key="t.key"
            type="button"
            class="flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium transition"
            :class="modelValue === t.key ? 'bg-[var(--brand)] text-white shadow-sm' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
            @click="$emit('update:modelValue', t.key)"
        >
            {{ t.label }}
            <span
                v-if="t.badge"
                class="rounded-full px-1.5 text-xs font-bold"
                :class="modelValue === t.key ? 'bg-white/25 text-white' : 'bg-red-100 text-red-700'"
            >{{ t.badge }}</span>
        </button>
    </div>
</template>
