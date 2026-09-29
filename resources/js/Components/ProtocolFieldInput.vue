<script setup>
import { ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
});
const emit = defineEmits(['update:modelValue', 'photo']);

const preview = ref(null);
function set(v) { emit('update:modelValue', v); }
function onFile(e) {
    const f = e.target.files?.[0] ?? null;
    emit('photo', f);
    preview.value = f ? URL.createObjectURL(f) : null;
}

const cfg = props.field.config || {};
</script>

<template>
    <div>
        <label class="flex items-center justify-between text-sm font-medium text-gray-800">
            <span>{{ field.label }}<span v-if="field.required" class="text-red-500"> *</span></span>
        </label>

        <!-- Contrôle vide / OK / NOK -->
        <div v-if="field.type === 'tristate'" class="mt-1.5 grid grid-cols-2 gap-2">
            <button type="button" class="rounded-lg border py-2 text-sm font-semibold" :class="modelValue === 'ok' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300 text-gray-500'" @click="set(modelValue === 'ok' ? '' : 'ok')">OK</button>
            <button type="button" class="rounded-lg border py-2 text-sm font-semibold" :class="modelValue === 'nok' ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-300 text-gray-500'" @click="set(modelValue === 'nok' ? '' : 'nok')">NOK</button>
        </div>

        <!-- Case à cocher -->
        <label v-else-if="field.type === 'checkbox'" class="mt-1.5 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2.5 text-sm text-gray-700">
            <input type="checkbox" :checked="!!modelValue" class="h-4 w-4 rounded border-gray-300" @change="set($event.target.checked)" />
            Conforme
        </label>

        <!-- Jauge -->
        <div v-else-if="field.type === 'gauge'" class="mt-1.5">
            <div class="flex items-center gap-3">
                <input type="range" :min="cfg.min ?? 0" :max="cfg.max ?? 100" :step="cfg.step ?? 1" :value="modelValue ?? cfg.min ?? 0" class="h-2 flex-1 accent-[var(--brand,#C6362B)]" @input="set(Number($event.target.value))" />
                <span class="w-16 text-right text-sm font-semibold text-gray-900">{{ modelValue ?? cfg.min ?? 0 }}<span v-if="cfg.unit" class="ml-0.5 text-xs text-gray-400">{{ cfg.unit }}</span></span>
            </div>
        </div>

        <!-- Valeur chiffrée -->
        <div v-else-if="field.type === 'number'" class="mt-1.5 flex items-center gap-2">
            <input type="number" inputmode="decimal" :min="cfg.min" :max="cfg.max" :step="cfg.step ?? 'any'" :value="modelValue ?? ''" class="block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" @input="set($event.target.value === '' ? null : Number($event.target.value))" />
            <span v-if="cfg.unit" class="text-sm text-gray-400">{{ cfg.unit }}</span>
        </div>

        <!-- Photo -->
        <div v-else-if="field.type === 'photo'" class="mt-1.5">
            <div v-if="preview" class="relative inline-block">
                <img :src="preview" alt="" class="h-32 w-full rounded-lg object-cover" />
            </div>
            <label v-else class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-5 text-sm text-gray-500 hover:bg-gray-50">
                <Icon name="camera" :size="18" /> Prendre une photo
                <input type="file" accept="image/*" capture="environment" class="hidden" @change="onFile" />
            </label>
        </div>

        <!-- Texte libre -->
        <textarea v-else-if="field.type === 'text' && cfg.multiline" rows="2" :value="modelValue ?? ''" class="mt-1.5 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm" @input="set($event.target.value)"></textarea>
        <input v-else type="text" :value="modelValue ?? ''" class="mt-1.5 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" @input="set($event.target.value)" />
    </div>
</template>
