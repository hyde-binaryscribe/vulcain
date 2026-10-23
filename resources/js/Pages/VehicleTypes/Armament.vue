<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    type: { type: Object, required: true },
    equipment: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
    materialTypes: { type: Array, default: () => [] },
    oxygenBottleTypes: { type: Array, default: () => [] },
    oxygenDotation: { type: Array, default: () => [] },
    templates: { type: Object, default: () => ({}) },
});

// --- Dotation oxygène standard (répartition en bouteilles) ---
const dotation = reactive({ items: (props.oxygenDotation || []).map((d) => ({ ...d })) });
const bottleTypeById = computed(() => Object.fromEntries(props.oxygenBottleTypes.map((t) => [t.id, t])));
const dotationTotal = computed(() => dotation.items.reduce((s, d) => {
    const t = bottleTypeById.value[d.oxygen_bottle_type_id];
    return s + (t?.volume_l ? t.volume_l * (d.quantity || 1) : 0);
}, 0));
function addDotation() { dotation.items.push({ oxygen_bottle_type_id: props.oxygenBottleTypes[0]?.id ?? null, quantity: 1, label: '' }); }
function removeDotation(i) { dotation.items.splice(i, 1); }
const savingOxygen = ref(false);
function saveOxygen() {
    savingOxygen.value = true;
    router.post(`/vehicle-types/${props.type.id}/dotation-oxygene`, { items: dotation.items }, {
        preserveScroll: true, preserveState: true, onFinish: () => { savingOxygen.value = false; },
    });
}

const current = reactive({ items: (props.equipment || []).map((e) => ({ ...e, note: e.note ?? '' })) });
const selTemplate = ref('');

const levelChip = {
    obligatoire: 'bg-red-100 text-red-700',
    conditionnel: 'bg-amber-100 text-amber-800',
    facultatif: 'bg-gray-100 text-gray-600',
};

const counts = computed(() => {
    const c = { obligatoire: 0, conditionnel: 0, facultatif: 0 };
    current.items.forEach((i) => { if (c[i.level] != null) c[i.level]++; });
    return c;
});

function addItem() { current.items.push({ category: '', designation: '', material_type_id: null, quantity: 1, volume_l: null, level: 'obligatoire', note: '' }); }
function removeItem(i) { current.items.splice(i, 1); }
function move(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= current.items.length) return;
    const [x] = current.items.splice(i, 1);
    current.items.splice(j, 0, x);
}

function loadTemplate() {
    const t = props.templates[selTemplate.value];
    if (!t) return;
    if (current.items.length && !confirm(`Remplacer l'armement actuel par le modèle « ${t.label} » ?`)) return;
    current.items = t.items.map((it) => ({ ...it, material_type_id: it.material_type_id ?? null, note: it.note ?? '' }));
    selTemplate.value = '';
}

const saving = ref(false);
function save() {
    saving.value = true;
    router.post(`/vehicle-types/${props.type.id}/armement`, { items: current.items }, {
        preserveScroll: true, preserveState: true, onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="`Armement — ${type.name}`" />
        <template #title>Armement — {{ type.name }}</template>

        <div class="mb-4 flex items-center gap-2 border-b border-gray-200 pb-3">
            <Link href="/vehicle-types" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-800"><Icon name="arrow-left" :size="15" /> Types de véhicule</Link>
        </div>

        <p class="mb-4 max-w-2xl text-sm text-gray-500">
            Définissez l'<span class="font-medium text-gray-700">armement de référence</span> de ce type : les équipements
            attendus, leur quantité et leur niveau (obligatoire, conditionnel, facultatif). Partez d'un
            <span class="font-medium text-gray-700">modèle légal</span> puis affinez — l'oxygène est à préciser selon vos conditionnements.
        </p>

        <!-- Barre : modèle + récap -->
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
            <div class="flex items-center gap-2">
                <select v-model="selTemplate" class="rounded-lg border-gray-300 px-3 py-2 text-sm">
                    <option value="">Charger un modèle légal…</option>
                    <option v-for="(t, key) in templates" :key="key" :value="key">{{ t.label }}</option>
                </select>
                <button type="button" :disabled="!selTemplate" class="rounded-lg border border-[var(--brand)]/40 px-3 py-2 text-sm font-semibold text-[var(--brand)] hover:bg-[var(--brand)]/10 disabled:opacity-40" @click="loadTemplate">Charger</button>
            </div>
            <span class="ml-auto flex items-center gap-2 text-xs">
                <span class="rounded-full bg-red-100 px-2 py-0.5 font-semibold text-red-700">{{ counts.obligatoire }} obligatoire(s)</span>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">{{ counts.conditionnel }} conditionnel(s)</span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600">{{ counts.facultatif }} facultatif(s)</span>
            </span>
        </div>

        <!-- Lignes -->
        <div class="space-y-2">
            <div class="hidden gap-2 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400 sm:grid sm:grid-cols-12">
                <span class="sm:col-span-3">Catégorie</span>
                <span class="sm:col-span-4">Désignation</span>
                <span class="sm:col-span-1">Qté</span>
                <span class="sm:col-span-2">Vol. O₂ (L)</span>
                <span class="sm:col-span-2">Niveau</span>
            </div>

            <div v-for="(it, i) in current.items" :key="i" class="rounded-xl border border-gray-200 bg-white p-2.5 shadow-sm">
                <div class="grid grid-cols-1 items-center gap-2 sm:grid-cols-12">
                    <input v-model="it.category" type="text" placeholder="Catégorie" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-3" />
                    <input v-model="it.designation" type="text" placeholder="Désignation de l'équipement" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-4" />
                    <input v-model.number="it.quantity" type="number" min="1" title="Quantité" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-1" />
                    <input v-model.number="it.volume_l" type="number" min="0" placeholder="ex. 3000" title="Volume d'oxygène obligatoire (litres)" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-2" />
                    <select v-model="it.level" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-2" :class="levelChip[it.level]">
                        <option v-for="l in levels" :key="l.value" :value="l.value">{{ l.label }}</option>
                    </select>
                </div>
                <div class="mt-1.5 flex items-center gap-1">
                    <select v-model="it.material_type_id" title="Lier à un type de matériel du catalogue (conformité exacte)" class="w-48 shrink-0 rounded-md border-gray-300 px-2 py-1 text-xs" :class="it.material_type_id ? 'text-gray-900' : 'text-gray-400'">
                        <option :value="null">— Matériel lié (conformité exacte) —</option>
                        <option v-for="mt in materialTypes" :key="mt.id" :value="mt.id">{{ mt.name }}</option>
                    </select>
                    <input v-model="it.note" type="text" placeholder="Note (optionnel)" class="min-w-0 flex-1 rounded-md border-gray-300 px-2 py-1 text-xs" />
                    <button type="button" class="rounded-md p-1 text-gray-300 hover:text-gray-600" title="Monter" @click="move(i, -1)"><Icon name="arrow-left" :size="13" class="-rotate-90" /></button>
                    <button type="button" class="rounded-md p-1 text-gray-300 hover:text-gray-600" title="Descendre" @click="move(i, 1)"><Icon name="arrow-right" :size="13" class="-rotate-90" /></button>
                    <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Supprimer" @click="removeItem(i)"><Icon name="x" :size="14" /></button>
                </div>
            </div>

            <p v-if="current.items.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Aucun équipement. Chargez un modèle légal ci-dessus, ou ajoutez des lignes.
            </p>
        </div>

        <div class="mt-4 flex items-center gap-2">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="addItem">
                <Icon name="plus" :size="16" /> Ajouter une ligne
            </button>
            <button type="button" :disabled="saving" class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="save">
                Enregistrer l'armement
            </button>
        </div>

        <!-- Dotation oxygène standard (répartition en bouteilles) -->
        <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-sky-600"><Icon name="pharmacy" :size="15" /></span>
                    Dotation oxygène standard
                </h2>
                <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">Volume total : {{ dotationTotal.toLocaleString('fr-FR') }} L</span>
            </div>
            <p class="mt-1 text-sm text-gray-500">Répartition O₂ attendue pour ce type (ex. 2× B5). Le volume requis se calcule automatiquement et sert à la conformité ; tu pourras générer ces bouteilles sur un véhicule neuf.</p>

            <div class="mt-3 space-y-2">
                <div v-for="(d, i) in dotation.items" :key="i" class="grid grid-cols-1 items-center gap-2 sm:grid-cols-12">
                    <select v-model.number="d.oxygen_bottle_type_id" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-4">
                        <option v-for="t in oxygenBottleTypes" :key="t.id" :value="t.id">{{ t.name }}<template v-if="t.volume_l"> ({{ t.volume_l }} L)</template></option>
                    </select>
                    <div class="flex items-center gap-1 text-sm text-gray-500 sm:col-span-2">× <input v-model.number="d.quantity" type="number" min="1" class="w-16 rounded-md border-gray-300 px-2 py-1.5 text-sm" /></div>
                    <input v-model="d.label" type="text" placeholder="Emplacement (ex. Arrière)" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-5" />
                    <button type="button" class="justify-self-start rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 sm:col-span-1" title="Retirer" @click="removeDotation(i)"><Icon name="x" :size="15" /></button>
                </div>
                <p v-if="dotation.items.length === 0" class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">Aucune dotation O₂ définie. Ajoutez des bouteilles pour fixer la répartition attendue.</p>
            </div>

            <div class="mt-3 flex items-center gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="addDotation"><Icon name="plus" :size="15" /> Ajouter une bouteille</button>
                <button type="button" :disabled="savingOxygen" class="rounded-lg bg-[var(--brand)] px-5 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="saveOxygen">Enregistrer la dotation O₂</button>
            </div>
        </div>
    </AppLayout>
</template>
