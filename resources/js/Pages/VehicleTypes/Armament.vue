<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    type: { type: Object, required: true },
    equipment: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
    templates: { type: Object, default: () => ({}) },
});

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

function addItem() { current.items.push({ category: '', designation: '', quantity: 1, level: 'obligatoire', note: '' }); }
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
    current.items = t.items.map((it) => ({ ...it, note: it.note ?? '' }));
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
                <span class="sm:col-span-2">Niveau</span>
                <span class="sm:col-span-2">Note</span>
            </div>

            <div v-for="(it, i) in current.items" :key="i" class="rounded-xl border border-gray-200 bg-white p-2.5 shadow-sm">
                <div class="grid grid-cols-1 items-center gap-2 sm:grid-cols-12">
                    <input v-model="it.category" type="text" placeholder="Catégorie" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-3" />
                    <input v-model="it.designation" type="text" placeholder="Désignation de l'équipement" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-4" />
                    <input v-model.number="it.quantity" type="number" min="1" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-1" />
                    <select v-model="it.level" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-2" :class="levelChip[it.level]">
                        <option v-for="l in levels" :key="l.value" :value="l.value">{{ l.label }}</option>
                    </select>
                    <div class="flex items-center gap-1 sm:col-span-2">
                        <input v-model="it.note" type="text" placeholder="Note" class="min-w-0 flex-1 rounded-md border-gray-300 px-2 py-1.5 text-xs" />
                        <div class="flex flex-col">
                            <button type="button" class="text-gray-300 hover:text-gray-600" title="Monter" @click="move(i, -1)"><Icon name="arrow-left" :size="12" class="-rotate-90" /></button>
                            <button type="button" class="text-gray-300 hover:text-gray-600" title="Descendre" @click="move(i, 1)"><Icon name="arrow-right" :size="12" class="-rotate-90" /></button>
                        </div>
                        <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Supprimer" @click="removeItem(i)"><Icon name="x" :size="14" /></button>
                    </div>
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
    </AppLayout>
</template>
