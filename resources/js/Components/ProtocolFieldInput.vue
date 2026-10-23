<script setup>
import { computed, ref } from 'vue';
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

// --- Champ Oxygène (bouteilles du véhicule, pré-remplies) ---
function bottleVal(b) {
    const m = props.modelValue || {};
    return m[b.id] || { serial: b.serial_number ?? '', pressure: b.pressure ?? null };
}
function setBottle(b, key, v) {
    const m = { ...(props.modelValue || {}) };
    m[b.id] = { ...bottleVal(b), [key]: v };
    emit('update:modelValue', m);
}
function bottlePercent(b) {
    const p = bottleVal(b).pressure;
    if (p == null || p === '' || isNaN(p)) return null;
    if (b.pressure_max === b.pressure_min) return null;
    return Math.round(Math.max(0, Math.min(100, ((Number(p) - b.pressure_min) / (b.pressure_max - b.pressure_min)) * 100)));
}
function oxyColor(p) {
    if (p == null) return '#9ca3af';
    if (p < 25) return '#d03b3b';
    if (p < 50) return '#e08a2b';
    return '#15a34a';
}

// Texte : majuscules imposées si configuré.
function onText(e) {
    const raw = e.target.value;
    set(cfg.uppercase ? raw.toUpperCase() : raw);
}

// Jauge : pourcentage d'après min/max, et couleur associée.
const percent = computed(() => {
    const v = props.modelValue;
    if (v === null || v === '' || isNaN(v)) return null;
    const min = Number(cfg.min ?? 0);
    const max = Number(cfg.max ?? 100);
    if (max === min) return null;
    return Math.round(Math.max(0, Math.min(100, ((Number(v) - min) / (max - min)) * 100)));
});
const percentColor = computed(() => {
    const p = percent.value;
    if (p === null) return '#9ca3af';
    if (p < 25) return '#d03b3b';
    if (p < 50) return '#e08a2b';
    return '#15a34a';
});
</script>

<template>
    <!-- Titre de section : simple intertitre, pas de saisie -->
    <div v-if="field.type === 'section'" class="flex items-center gap-2 border-t border-gray-100 pt-3 first:border-0 first:pt-0">
        <span class="text-xs font-bold uppercase tracking-wide text-[var(--brand,#C6362B)]">{{ field.label }}</span>
        <span class="h-px flex-1 bg-gray-100"></span>
    </div>

    <div v-else>
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

        <!-- Jauge (valeur + pourcentage + barre) -->
        <div v-else-if="field.type === 'gauge'" class="mt-1.5">
            <div class="flex items-center gap-3">
                <input type="range" :min="cfg.min ?? 0" :max="cfg.max ?? 100" :step="cfg.step ?? 1" :value="modelValue ?? cfg.min ?? 0" class="h-2 flex-1 accent-[var(--brand,#C6362B)]" @input="set(Number($event.target.value))" />
                <span class="w-16 text-right text-sm font-semibold text-gray-900">{{ modelValue ?? cfg.min ?? 0 }}<span v-if="cfg.unit" class="ml-0.5 text-xs text-gray-400">{{ cfg.unit }}</span></span>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full transition-all" :style="{ width: (percent ?? 0) + '%', background: percentColor }"></div>
                </div>
                <span class="w-10 text-right text-xs font-bold" :style="{ color: percentColor }">{{ percent ?? '—' }}%</span>
            </div>
        </div>

        <!-- Oxygène : bouteilles du véhicule (n° série + niveau pré-remplis) -->
        <div v-else-if="field.type === 'oxygen'" class="mt-1.5 space-y-2">
            <p v-if="!field.bottles || !field.bottles.length" class="rounded-lg bg-gray-50 px-3 py-2.5 text-xs text-gray-400">Aucune bouteille déclarée sur ce véhicule.</p>
            <div v-for="b in field.bottles" :key="b.id" class="rounded-lg border border-gray-200 p-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-800">{{ b.label }}</span>
                    <span class="text-xs font-bold" :style="{ color: oxyColor(bottlePercent(b)) }">{{ bottlePercent(b) ?? '—' }}%</span>
                </div>
                <div class="mt-1.5 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500">N° série</label>
                        <input type="text" :value="bottleVal(b).serial" class="mt-0.5 block w-full rounded-md border-gray-300 bg-gray-50 px-2 py-1.5 text-sm uppercase focus:bg-white" @input="setBottle(b, 'serial', $event.target.value.toUpperCase())" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500">Niveau (bar)</label>
                        <input type="number" inputmode="numeric" :min="b.pressure_min" :max="b.pressure_max" :value="bottleVal(b).pressure" class="mt-0.5 block w-full rounded-md border-gray-300 bg-gray-50 px-2 py-1.5 text-sm focus:bg-white" @input="setBottle(b, 'pressure', $event.target.value === '' ? null : Number($event.target.value))" />
                    </div>
                </div>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full transition-all" :style="{ width: (bottlePercent(b) ?? 0) + '%', background: oxyColor(bottlePercent(b)) }"></div>
                </div>
            </div>
        </div>

        <!-- Valeur chiffrée -->
        <div v-else-if="field.type === 'number'" class="mt-1.5 flex items-center gap-2">
            <input type="number" inputmode="decimal" :min="cfg.min" :max="cfg.max" :step="cfg.step ?? 'any'" :value="modelValue ?? ''" class="block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" @input="set($event.target.value === '' ? null : Number($event.target.value))" />
            <span v-if="cfg.unit" class="text-sm text-gray-400">{{ cfg.unit }}</span>
        </div>

        <!-- Photo -->
        <div v-else-if="field.type === 'photo'" class="mt-1.5">
            <div v-if="preview" class="relative inline-block">
                <img :src="preview" alt="" class="h-32 w-full rounded-lg object-cover" />
            </div>
            <label v-else class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-5 text-sm text-gray-500 hover:bg-gray-50">
                <Icon name="camera" :size="18" /> Prendre une photo
                <input type="file" accept="image/*" class="hidden" @change="onFile" />
            </label>
        </div>

        <!-- Texte libre (majuscules imposées si configuré) -->
        <textarea v-else-if="field.type === 'text' && cfg.multiline" rows="2" :value="modelValue ?? ''" :class="['mt-1.5 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white', cfg.uppercase ? 'uppercase' : '']" @input="onText"></textarea>
        <input v-else type="text" :value="modelValue ?? ''" :class="['mt-1.5 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white', cfg.uppercase ? 'uppercase' : '']" @input="onText" />
    </div>
</template>
