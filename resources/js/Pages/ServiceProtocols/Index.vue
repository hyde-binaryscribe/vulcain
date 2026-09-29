<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    types: { type: Array, default: () => [] },
    phases: { type: Array, default: () => [] },
    fieldTypes: { type: Array, default: () => [] },
    severities: { type: Array, default: () => [] },
    protocols: { type: Array, default: () => [] },
});

// Opérateurs d'alerte selon le type de champ.
const numericOps = [
    { value: 'lt', label: '<' }, { value: 'lte', label: '≤' },
    { value: 'gt', label: '>' }, { value: 'gte', label: '≥' }, { value: 'eq', label: '=' },
];
const opsFor = (type) => {
    if (type === 'number' || type === 'gauge') return numericOps;
    if (type === 'tristate') return [{ value: 'is_nok', label: 'est NOK' }];
    if (type === 'checkbox') return [{ value: 'unchecked', label: 'non cochée' }, { value: 'checked', label: 'cochée' }];
    return [];
};
const isNumeric = (type) => type === 'number' || type === 'gauge';
const canAlert = (type) => opsFor(type).length > 0;

const selType = ref(''); // '' = défaut (tous types)
const selPhase = ref(props.phases[0]?.value ?? 'ouverture');

const current = reactive({ fields: [] });

function findProtocol(type, phase) {
    return props.protocols.find((p) => (p.vehicle_type ?? '') === (type ?? '') && p.phase === phase);
}
function cloneFields(fields) {
    return (fields || []).map((f) => ({
        label: f.label ?? '',
        type: f.type ?? 'text',
        required: !!f.required,
        config: { ...(f.config || {}) },
        alert: { enabled: false, operator: '', threshold: '', severity: props.severities[1]?.value ?? 'warning', message: '', ...(f.alert || {}) },
    }));
}
function load() {
    const p = findProtocol(selType.value, selPhase.value);
    current.fields = cloneFields(p?.fields);
}
watch([selType, selPhase], load, { immediate: true });

function addField() {
    current.fields.push({ label: '', type: 'tristate', required: false, config: {}, alert: { enabled: false, operator: 'is_nok', threshold: '', severity: props.severities[1]?.value ?? 'warning', message: '' } });
}
function removeField(i) { current.fields.splice(i, 1); }
function move(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= current.fields.length) return;
    const [x] = current.fields.splice(i, 1);
    current.fields.splice(j, 0, x);
}

const saving = ref(false);
function save() {
    saving.value = true;
    router.post('/protocoles-service', {
        vehicle_type: selType.value || null,
        phase: selPhase.value,
        fields: current.fields,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Protocoles de service" />
        <template #title>Protocoles de service</template>

        <p class="mb-4 max-w-2xl text-sm text-gray-500">
            Construisez les protocoles de <span class="font-medium text-gray-700">prise</span> et de
            <span class="font-medium text-gray-700">fin de service</span> par type de véhicule. Chaque champ peut
            déclencher une alerte (rouge / orange / jaune) selon la réponse.
        </p>

        <!-- Sélection type + phase -->
        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-gray-600">Type de véhicule</label>
                <select v-model="selType" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                    <option value="">Défaut (tous les types)</option>
                    <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Moment</label>
                <select v-model="selPhase" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                    <option v-for="p in phases" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
            </div>
        </div>

        <!-- Champs -->
        <div class="space-y-3">
            <div v-for="(f, i) in current.fields" :key="i" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-start gap-2">
                    <div class="flex flex-col gap-1 pt-1">
                        <button type="button" class="text-gray-300 hover:text-gray-600" title="Monter" @click="move(i, -1)"><Icon name="arrow-left" :size="14" class="-rotate-90" /></button>
                        <button type="button" class="text-gray-300 hover:text-gray-600" title="Descendre" @click="move(i, 1)"><Icon name="arrow-right" :size="14" class="-rotate-90" /></button>
                    </div>
                    <div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-12">
                        <input v-model="f.label" type="text" placeholder="Intitulé du champ (ex. Pression O₂)" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-7" />
                        <select v-model="f.type" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-4">
                            <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <button type="button" class="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 sm:col-span-1" title="Supprimer" @click="removeField(i)"><Icon name="x" :size="15" /></button>
                    </div>
                </div>

                <!-- Config selon type -->
                <div class="ml-7 mt-2 flex flex-wrap items-center gap-3 text-xs">
                    <label class="flex items-center gap-1.5 text-gray-600"><input v-model="f.required" type="checkbox" class="rounded border-gray-300" /> Obligatoire</label>
                    <template v-if="isNumeric(f.type)">
                        <span class="flex items-center gap-1 text-gray-500">Unité <input v-model="f.config.unit" type="text" placeholder="bar, L…" class="w-16 rounded-md border-gray-300 px-1.5 py-1 text-xs" /></span>
                        <span class="flex items-center gap-1 text-gray-500">Min <input v-model.number="f.config.min" type="number" class="w-16 rounded-md border-gray-300 px-1.5 py-1 text-xs" /></span>
                        <span class="flex items-center gap-1 text-gray-500">Max <input v-model.number="f.config.max" type="number" class="w-16 rounded-md border-gray-300 px-1.5 py-1 text-xs" /></span>
                    </template>
                    <label v-else-if="f.type === 'text'" class="flex items-center gap-1.5 text-gray-600"><input v-model="f.config.multiline" type="checkbox" class="rounded border-gray-300" /> Multiligne</label>
                </div>

                <!-- Alerte -->
                <div v-if="canAlert(f.type)" class="ml-7 mt-2 rounded-lg bg-gray-50 p-2.5">
                    <label class="flex items-center gap-1.5 text-xs font-medium text-gray-700"><input v-model="f.alert.enabled" type="checkbox" class="rounded border-gray-300" /> Déclencher une alerte</label>
                    <div v-if="f.alert.enabled" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-gray-500">Si la réponse</span>
                        <select v-model="f.alert.operator" class="rounded-md border-gray-300 px-1.5 py-1 text-xs">
                            <option v-for="o in opsFor(f.type)" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <input v-if="isNumeric(f.type)" v-model.number="f.alert.threshold" type="number" placeholder="seuil" class="w-20 rounded-md border-gray-300 px-1.5 py-1 text-xs" />
                        <span class="text-gray-500">→ niveau</span>
                        <select v-model="f.alert.severity" class="rounded-md border-gray-300 px-1.5 py-1 text-xs">
                            <option v-for="s in severities" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <input v-model="f.alert.message" type="text" placeholder="Message (optionnel)" class="min-w-0 flex-1 rounded-md border-gray-300 px-2 py-1 text-xs" />
                    </div>
                </div>
            </div>

            <p v-if="current.fields.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Aucun champ pour ce protocole. Ajoutez-en, ou laissez vide pour utiliser la procédure par défaut.
            </p>
        </div>

        <div class="mt-4 flex items-center gap-2">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="addField">
                <Icon name="plus" :size="16" /> Ajouter un champ
            </button>
            <button type="button" :disabled="saving" class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="save">
                Enregistrer ce protocole
            </button>
        </div>
    </AppLayout>
</template>
