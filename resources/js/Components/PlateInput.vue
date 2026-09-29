<script setup>
import { onMounted, ref, watch } from 'vue';

/**
 * Champ d'immatriculation formaté, deux modes :
 *  - SIV (depuis 2009, « nouveau ») : AA-123-AA (2 lettres, 3 chiffres, 2 lettres, tirets)
 *  - FNI (avant, « ancien ») : 1234 ABC 75 (chiffres, lettres, département, espaces)
 * Majuscules systématiques, séparateurs insérés automatiquement.
 */
const model = defineModel({ type: String, default: '' });

const mode = ref('siv'); // 'siv' | 'fni'

/** Format SIV : LL-DDD-LL. */
function formatSiv(raw) {
    const s = raw.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 7);
    const parts = [s.slice(0, 2)];
    if (s.length > 2) parts.push(s.slice(2, 5));
    if (s.length > 5) parts.push(s.slice(5, 7));
    return parts.filter(Boolean).join('-');
}

/** Format FNI : DDDD LL(L) DD(D) — segmenté par transitions chiffres/lettres/chiffres. */
function formatFni(raw) {
    const s = raw.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    const m = s.match(/^(\d{0,4})([A-Z]{0,3})(\d{0,3})/);
    if (!m) return s;
    return [m[1], m[2], m[3]].filter(Boolean).join(' ');
}

function format(raw) {
    return mode.value === 'siv' ? formatSiv(raw) : formatFni(raw);
}

/** Devine le mode à partir d'une valeur existante (édition d'un véhicule). */
function detectMode(value) {
    const s = (value || '').replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    // SIV : 2 lettres + 3 chiffres + 2 lettres.
    if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(s)) return 'siv';
    // FNI : commence par des chiffres, lettres au milieu.
    if (/^\d+[A-Z]/.test(s)) return 'fni';
    return mode.value;
}

function onInput(event) {
    model.value = format(event.target.value);
}

function setMode(next) {
    if (mode.value === next) return;
    mode.value = next;
    // Reformate la valeur courante selon le nouveau mode.
    model.value = format(model.value);
}

// Détection initiale du mode d'après la valeur reçue (création : vide → SIV).
onMounted(() => {
    if (model.value) mode.value = detectMode(model.value);
});

// Si la valeur change de l'extérieur (ex. ouverture d'une autre fiche), on
// réajuste le mode détecté.
watch(model, (v, old) => {
    if (v && v !== old) {
        const detected = detectMode(v);
        if (detected !== mode.value) mode.value = detected;
    }
});
</script>

<template>
    <div>
        <div class="mb-1.5 inline-flex rounded-lg border border-gray-300 p-0.5 text-xs">
            <button
                type="button"
                class="rounded-md px-2.5 py-1 font-medium transition"
                :class="mode === 'siv' ? 'bg-red-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                @click="setMode('siv')"
            >
                Nouveau (SIV)
            </button>
            <button
                type="button"
                class="rounded-md px-2.5 py-1 font-medium transition"
                :class="mode === 'fni' ? 'bg-red-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                @click="setMode('fni')"
            >
                Ancien (FNI)
            </button>
        </div>
        <input
            :value="model"
            type="text"
            inputmode="latin"
            autocapitalize="characters"
            spellcheck="false"
            :placeholder="mode === 'siv' ? 'AA-123-AA' : '1234 ABC 75'"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 uppercase tracking-wider text-gray-900 shadow-sm outline-none transition focus:border-red-700 focus:ring-2 focus:ring-red-700/30"
            @input="onInput"
        />
    </div>
</template>
