<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

// Lien Itineris (transport sanitaire) : un champ chiffré peut être transmis comme niveau d'oxygène.
const itineris = computed(() => usePage().props.tenant?.profile?.sector === 'ambulance_privee');

const props = defineProps({
    types: { type: Array, default: () => [] },
    models: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
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
const isSection = (type) => type === 'section';
const canAlert = (type) => opsFor(type).length > 0;

// Portée du protocole en cours d'édition.
const selScope = ref('default'); // default | type | model | vehicle
const selType = ref('');
const selModelId = ref('');
const selVehicleId = ref('');
const selPhase = ref(props.phases[0]?.value ?? 'ouverture');

const current = reactive({ fields: [] });

const defaultSeverity = () => props.severities[1]?.value ?? 'warning';

function protoMatch(p, phase) {
    if (p.phase !== phase) return false;
    if (selScope.value === 'vehicle') return p.vehicle_id === Number(selVehicleId.value);
    if (selScope.value === 'model') return p.vehicle_model_id === Number(selModelId.value) && p.vehicle_id == null;
    if (selScope.value === 'type') return (p.vehicle_type ?? '') === (selType.value ?? '') && p.vehicle_model_id == null && p.vehicle_id == null;
    return p.vehicle_type == null && p.vehicle_model_id == null && p.vehicle_id == null;
}
function findProtocol(phase) {
    return props.protocols.find((p) => protoMatch(p, phase));
}
function cloneFields(fields) {
    return (fields || []).map((f) => ({
        label: f.label ?? '',
        type: f.type ?? 'text',
        required: !!f.required,
        config: { ...(f.config || {}) },
        alert: { enabled: false, operator: '', threshold: '', basis: 'value', severity: defaultSeverity(), message: '', ...(f.alert || {}) },
    }));
}
function load() {
    const p = findProtocol(selPhase.value);
    current.fields = cloneFields(p?.fields);
}
watch([selScope, selType, selModelId, selVehicleId, selPhase], load, { immediate: true });

// Le véhicule sélectionné (pour calculer l'héritage).
const selectedVehicle = computed(() => props.vehicles.find((v) => v.id === Number(selVehicleId.value)) || null);

// Y a-t-il une portée valide ? (une cible choisie pour type/modèle/véhicule).
const scopeReady = computed(() => {
    if (selScope.value === 'type') return !!selType.value;
    if (selScope.value === 'model') return !!selModelId.value;
    if (selScope.value === 'vehicle') return !!selVehicleId.value;
    return true;
});

// Champs hérités du niveau au-dessus (pour « partir de la procédure héritée »).
function inheritedFields() {
    const phase = selPhase.value;
    const byDefault = () => props.protocols.find((p) => p.phase === phase && p.vehicle_type == null && p.vehicle_model_id == null && p.vehicle_id == null);
    const byType = (t) => t ? props.protocols.find((p) => p.phase === phase && (p.vehicle_type ?? '') === t && p.vehicle_model_id == null && p.vehicle_id == null) : null;
    const byModel = (m) => m ? props.protocols.find((p) => p.phase === phase && p.vehicle_model_id === m && p.vehicle_id == null) : null;

    let src = null;
    if (selScope.value === 'vehicle' && selectedVehicle.value) {
        src = byModel(selectedVehicle.value.vehicle_model_id) || byType(selectedVehicle.value.type) || byDefault();
    } else if (selScope.value === 'model') {
        src = byDefault();
    } else if (selScope.value === 'type') {
        src = byDefault();
    }
    return cloneFields(src?.fields);
}
const canInherit = computed(() => selScope.value !== 'default' && scopeReady.value && inheritedFields().length > 0);
function loadInherited() {
    current.fields = inheritedFields();
}

function addField(type = 'tristate') {
    current.fields.push({
        label: '', type, required: false,
        config: {},
        alert: { enabled: false, operator: type === 'tristate' ? 'is_nok' : '', threshold: '', basis: 'value', severity: defaultSeverity(), message: '' },
    });
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
        vehicle_type: selScope.value === 'type' ? (selType.value || null) : null,
        vehicle_model_id: selScope.value === 'model' ? (Number(selModelId.value) || null) : null,
        vehicle_id: selScope.value === 'vehicle' ? (Number(selVehicleId.value) || null) : null,
        phase: selPhase.value,
        fields: current.fields,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { saving.value = false; },
    });
}

const scopeTabs = [
    { value: 'default', label: 'Base (défaut)' },
    { value: 'type', label: 'Par type' },
    { value: 'model', label: 'Par modèle' },
    { value: 'vehicle', label: 'Par véhicule' },
];
</script>

<template>
    <AppLayout>
        <Head title="Protocoles de service" />
        <template #title>Protocoles de service</template>

        <p class="mb-4 max-w-2xl text-sm text-gray-500">
            Construisez les protocoles de <span class="font-medium text-gray-700">prise</span> et de
            <span class="font-medium text-gray-700">fin de service</span>. La procédure appliquée à un véhicule est
            résolue en cascade : <span class="font-medium text-gray-700">véhicule → modèle → type → base</span>
            (le niveau le plus précis l'emporte). Chaque champ peut déclencher une alerte.
        </p>

        <!-- Portée -->
        <div class="mb-3 flex flex-wrap gap-1.5">
            <button
                v-for="t in scopeTabs"
                :key="t.value"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                :class="selScope === t.value ? 'bg-[var(--brand)] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                @click="selScope = t.value"
            >{{ t.label }}</button>
        </div>

        <!-- Sélecteur de cible + phase -->
        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-gray-600">
                    <template v-if="selScope === 'type'">Type de véhicule</template>
                    <template v-else-if="selScope === 'model'">Modèle de véhicule</template>
                    <template v-else-if="selScope === 'vehicle'">Véhicule</template>
                    <template v-else>Portée</template>
                </label>
                <select v-if="selScope === 'type'" v-model="selType" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                    <option value="">— Choisir un type —</option>
                    <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                </select>
                <select v-else-if="selScope === 'model'" v-model="selModelId" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                    <option value="">— Choisir un modèle —</option>
                    <option v-for="m in models" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <select v-else-if="selScope === 'vehicle'" v-model="selVehicleId" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                    <option value="">— Choisir un véhicule —</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
                </select>
                <p v-else class="mt-1 rounded-lg bg-gray-50 px-3 py-2.5 text-sm text-gray-500">Procédure par défaut, appliquée à tout véhicule sans règle plus précise.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Moment</label>
                <select v-model="selPhase" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                    <option v-for="p in phases" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
            </div>
        </div>

        <!-- Rappel de portée non choisie -->
        <div v-if="!scopeReady" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
            Choisissez une cible ci-dessus pour éditer sa procédure.
        </div>

        <template v-else>
            <!-- Partir de l'héritage -->
            <div v-if="selScope !== 'default' && current.fields.length === 0 && canInherit" class="mb-3 flex items-center justify-between gap-3 rounded-xl border border-[var(--brand)]/30 bg-[var(--brand)]/5 p-3 text-sm">
                <span class="text-gray-600">Cette cible n'a pas de procédure propre : elle hérite du niveau au-dessus.</span>
                <button type="button" class="shrink-0 rounded-lg border border-[var(--brand)]/40 px-3 py-1.5 text-xs font-semibold text-[var(--brand)] hover:bg-[var(--brand)]/10" @click="loadInherited">
                    Partir de la procédure héritée
                </button>
            </div>

            <!-- Champs -->
            <div class="space-y-3">
                <div v-for="(f, i) in current.fields" :key="i" class="rounded-2xl border bg-white p-4 shadow-sm" :class="isSection(f.type) ? 'border-[var(--brand)]/30 bg-[var(--brand)]/5' : 'border-gray-200'">
                    <div class="flex items-start gap-2">
                        <div class="flex flex-col gap-1 pt-1">
                            <button type="button" class="text-gray-300 hover:text-gray-600" title="Monter" @click="move(i, -1)"><Icon name="arrow-left" :size="14" class="-rotate-90" /></button>
                            <button type="button" class="text-gray-300 hover:text-gray-600" title="Descendre" @click="move(i, 1)"><Icon name="arrow-right" :size="14" class="-rotate-90" /></button>
                        </div>
                        <div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-12">
                            <input v-model="f.label" type="text" :placeholder="isSection(f.type) ? 'Titre de section (ex. Oxygène)' : 'Intitulé du champ (ex. Pression O₂)'" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-7" :class="isSection(f.type) ? 'font-semibold' : ''" />
                            <select v-model="f.type" class="rounded-md border-gray-300 px-2 py-1.5 text-sm sm:col-span-4">
                                <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <button type="button" class="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 sm:col-span-1" title="Supprimer" @click="removeField(i)"><Icon name="x" :size="15" /></button>
                        </div>
                    </div>

                    <!-- Config selon type (hors section) -->
                    <div v-if="!isSection(f.type)" class="ml-7 mt-2 flex flex-wrap items-center gap-3 text-xs">
                        <label class="flex items-center gap-1.5 text-gray-600"><input v-model="f.required" type="checkbox" class="rounded border-gray-300" /> Obligatoire</label>
                        <template v-if="isNumeric(f.type)">
                            <span class="flex items-center gap-1 text-gray-500">Unité <input v-model="f.config.unit" type="text" placeholder="bar, L…" class="w-16 rounded-md border-gray-300 px-1.5 py-1 text-xs" /></span>
                            <span class="flex items-center gap-1 text-gray-500">Min <input v-model.number="f.config.min" type="number" class="w-16 rounded-md border-gray-300 px-1.5 py-1 text-xs" /></span>
                            <span class="flex items-center gap-1 text-gray-500">Max <input v-model.number="f.config.max" type="number" class="w-16 rounded-md border-gray-300 px-1.5 py-1 text-xs" /></span>
                            <span v-if="f.type === 'gauge'" class="text-gray-400">→ % calculé automatiquement</span>
                            <span v-if="itineris" class="flex items-center gap-1 text-gray-500">
                                Transmis à Itineris
                                <select v-model="f.config.share" class="rounded-md border-gray-300 py-1 pl-1.5 pr-6 text-xs">
                                    <option :value="undefined">— (reconnu au libellé « oxygène », « O2 »)</option>
                                    <option value="o2">Niveau d'oxygène (O2)</option>
                                </select>
                            </span>
                        </template>
                        <template v-else-if="f.type === 'text'">
                            <label class="flex items-center gap-1.5 text-gray-600"><input v-model="f.config.multiline" type="checkbox" class="rounded border-gray-300" /> Multiligne</label>
                            <label class="flex items-center gap-1.5 text-gray-600"><input v-model="f.config.uppercase" type="checkbox" class="rounded border-gray-300" /> Majuscules imposées</label>
                        </template>
                    </div>

                    <!-- Alerte (hors section) -->
                    <div v-if="!isSection(f.type) && canAlert(f.type)" class="ml-7 mt-2 rounded-lg bg-gray-50 p-2.5">
                        <label class="flex items-center gap-1.5 text-xs font-medium text-gray-700"><input v-model="f.alert.enabled" type="checkbox" class="rounded border-gray-300" /> Déclencher une alerte</label>
                        <div v-if="f.alert.enabled" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="text-gray-500">Si la réponse</span>
                            <select v-model="f.alert.operator" class="rounded-md border-gray-300 px-1.5 py-1 text-xs">
                                <option v-for="o in opsFor(f.type)" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                            <input v-if="isNumeric(f.type)" v-model.number="f.alert.threshold" type="number" placeholder="seuil" class="w-20 rounded-md border-gray-300 px-1.5 py-1 text-xs" />
                            <select v-if="f.type === 'gauge'" v-model="f.alert.basis" class="rounded-md border-gray-300 px-1.5 py-1 text-xs">
                                <option value="value">en valeur</option>
                                <option value="percent">en %</option>
                            </select>
                            <span class="text-gray-500">→ niveau</span>
                            <select v-model="f.alert.severity" class="rounded-md border-gray-300 px-1.5 py-1 text-xs">
                                <option v-for="s in severities" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                            <input v-model="f.alert.message" type="text" placeholder="Message (optionnel)" class="min-w-0 flex-1 rounded-md border-gray-300 px-2 py-1 text-xs" />
                        </div>
                    </div>
                </div>

                <p v-if="current.fields.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                    Aucun champ pour cette procédure. Ajoutez-en, ou laissez vide pour hériter du niveau au-dessus.
                </p>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="addField('tristate')">
                    <Icon name="plus" :size="16" /> Ajouter un champ
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="addField('section')">
                    <Icon name="plus" :size="16" /> Ajouter une section
                </button>
                <button type="button" :disabled="saving" class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="save">
                    Enregistrer cette procédure
                </button>
            </div>
        </template>
    </AppLayout>
</template>
