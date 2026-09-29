<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HistoryList from '@/Components/HistoryList.vue';
import Icon from '@/Components/Icon.vue';
import VehicleQr from '@/Components/VehicleQr.vue';
import VehicleBodyMap from '@/Components/VehicleBodyMap.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    assigned: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    alerts: { type: Object, default: () => ({ expired: 0, expiring_soon: 0, below_threshold: 0, anomalies: 0 }) },
    disinfection: { type: Object, default: () => ({ records: [], types: [], can_record: false, state: 'none' }) },
    maintenance: { type: Object, default: () => ({ records: [], types: [], state: 'none' }) },
    fuel: { type: Object, default: null },
    tasks: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    can_manage_documents: { type: Boolean, default: false },
    body: { type: Object, default: () => ({ enabled: false, damages: [], schematics: {}, can_delete: false }) },
    history: { type: Array, default: () => [] },
});

const viewLabels = { avant: 'Avant', arriere: 'Arrière', gauche: 'Côté gauche', droite: 'Côté droit', dessus: 'Dessus' };
const viewLabel = (v) => viewLabels[v] ?? v;

const showQr = ref(false);

// --- Documents véhicule ---
const docCategories = ['Agrément', 'Contrôle technique', 'Carte grise', 'Assurance', 'Autre'];
const docForm = useForm({ subject_type: 'vehicle', subject_id: props.vehicle.id, category: 'Agrément', title: '', expires_at: '', file: null });
function submitDoc() {
    docForm.transform((d) => ({ ...d, expires_at: d.expires_at || null }))
        .post('/documents', { preserveScroll: true, forceFormData: true, onSuccess: () => { docForm.reset(); docForm.subject_type = 'vehicle'; docForm.subject_id = props.vehicle.id; docForm.category = 'Agrément'; } });
}
function deleteDoc(id) {
    if (confirm('Supprimer ce document ?')) router.delete(`/documents/${id}`, { preserveScroll: true });
}

// --- Carrosserie ---
const bodyAdd = ref(null);
const bodyDetail = ref(null);
const bodyForm = useForm({ view: '', pos_x: 0, pos_y: 0, description: '', photo: null });
function onBodyAdd({ view, x, y }) {
    bodyForm.reset();
    bodyForm.clearErrors();
    bodyForm.view = view;
    bodyForm.pos_x = x;
    bodyForm.pos_y = y;
    bodyAdd.value = { view };
}
function submitBody() {
    bodyForm.post(`/vehicles/${props.vehicle.id}/body-damages`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { bodyAdd.value = null; bodyForm.reset(); },
    });
}
function resolveBody(d) {
    router.post(`/vehicles/${props.vehicle.id}/body-damages/${d.id}/resolve`, {}, { preserveScroll: true, onSuccess: () => { bodyDetail.value = null; } });
}
function deleteBody(d) {
    if (confirm('Supprimer cette anomalie carrosserie ?')) router.delete(`/vehicles/${props.vehicle.id}/body-damages/${d.id}`, { preserveScroll: true, onSuccess: () => { bodyDetail.value = null; } });
}

// --- Tâches véhicule ---
const taskForm = useForm({ title: '', notes: '' });
function addTask() {
    taskForm.post(`/vehicles/${props.vehicle.id}/tasks`, { preserveScroll: true, onSuccess: () => taskForm.reset() });
}
function completeTask(id) {
    router.post(`/vehicles/${props.vehicle.id}/tasks/${id}/complete`, {}, { preserveScroll: true });
}
function reopenTask(id) {
    router.post(`/vehicles/${props.vehicle.id}/tasks/${id}/reopen`, {}, { preserveScroll: true });
}
function deleteTask(id) {
    if (confirm('Supprimer cette tâche ?')) router.delete(`/vehicles/${props.vehicle.id}/tasks/${id}`, { preserveScroll: true });
}

// --- Carburant ---
const fuelForm = useForm({ filled_at: '', mileage: '', liters: '', price_per_liter: '', full_tank: true });
function submitFuel() {
    fuelForm.transform((d) => ({ ...d, price_per_liter: d.price_per_liter || null, filled_at: d.filled_at || null }))
        .post(`/vehicles/${props.vehicle.id}/fuel`, { preserveScroll: true, onSuccess: () => fuelForm.reset() });
}
function deleteFuel(id) {
    if (confirm('Supprimer ce plein ?')) router.delete(`/vehicles/${props.vehicle.id}/fuel/${id}`, { preserveScroll: true });
}

const severityBadge = {
    critical: 'bg-red-100 text-red-800',
    warning: 'bg-orange-100 text-orange-800',
    watch: 'bg-yellow-100 text-yellow-800',
};

// Désinfection : couleur du statut selon la gravité (rouge/orange/jaune).
const disinfectionBadge = {
    critical: 'bg-red-100 text-red-800',
    warning: 'bg-orange-100 text-orange-800',
    watch: 'bg-yellow-100 text-yellow-800',
};
function disinfectionBadgeClass() {
    if (props.disinfection.severity) return disinfectionBadge[props.disinfection.severity];
    return props.disinfection.state === 'ok' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600';
}

function nowLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

const showDisinfectionForm = ref(false);
const disinfectionForm = useForm({
    type: props.disinfection.types?.[1]?.value ?? props.disinfection.types?.[0]?.value ?? 'desinfection',
    disinfection_protocol_id: '',
    steps: [],
    performed_at: nowLocal(),
    notes: '',
});

// Réalisation : sélectionner un protocole charge sa checklist et fixe le niveau.
watch(() => disinfectionForm.disinfection_protocol_id, (id) => {
    const proto = (props.disinfection.protocols || []).find((p) => String(p.id) === String(id));
    if (proto) {
        disinfectionForm.type = proto.type;
        disinfectionForm.steps = (proto.steps || []).map((label) => ({ label, done: true }));
    } else {
        disinfectionForm.steps = [];
    }
});

function submitDisinfection() {
    disinfectionForm.post(`/vehicles/${props.vehicle.id}/disinfections`, {
        preserveScroll: true,
        onSuccess: () => {
            disinfectionForm.reset('notes', 'disinfection_protocol_id', 'steps');
            disinfectionForm.performed_at = nowLocal();
            showDisinfectionForm.value = false;
        },
    });
}
function deleteDisinfection(id) {
    if (confirm('Supprimer cette entrée du journal de désinfection ?')) {
        router.delete(`/vehicles/${props.vehicle.id}/disinfections/${id}`, { preserveScroll: true });
    }
}

// --- Suivi mécanique ---
function maintenanceBadgeClass() {
    if (props.maintenance.severity) return severityBadge[props.maintenance.severity];
    return props.maintenance.state === 'ok' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600';
}
function todayLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 10);
}
const mileageInput = ref(props.vehicle.mileage ?? '');
function saveMileage() {
    router.post(`/vehicles/${props.vehicle.id}/mileage`, { mileage: mileageInput.value || 0 }, { preserveScroll: true });
}
const showMaintenanceForm = ref(false);
const maintenanceForm = useForm({
    type: props.maintenance.types?.[0]?.value ?? 'revision',
    performed_at: todayLocal(),
    mileage: '',
    cost: '',
    provider: '',
    notes: '',
    next_due_at: '',
    next_due_mileage: '',
});
function submitMaintenance() {
    maintenanceForm.transform((d) => ({
        ...d,
        mileage: d.mileage || null,
        cost: d.cost || null,
        next_due_mileage: d.next_due_mileage || null,
        next_due_at: d.next_due_at || null,
    })).post(`/vehicles/${props.vehicle.id}/maintenances`, {
        preserveScroll: true,
        onSuccess: () => {
            maintenanceForm.reset('mileage', 'cost', 'provider', 'notes', 'next_due_at', 'next_due_mileage');
            maintenanceForm.performed_at = todayLocal();
            showMaintenanceForm.value = false;
        },
    });
}
function deleteMaintenance(id) {
    if (confirm('Supprimer cette entrée du suivi mécanique ?')) {
        router.delete(`/vehicles/${props.vehicle.id}/maintenances/${id}`, { preserveScroll: true });
    }
}

const statusStyles = {
    conforme: 'bg-green-100 text-green-800', manquant: 'bg-amber-100 text-amber-800',
    hs: 'bg-red-100 text-red-800', a_remplacer: 'bg-orange-100 text-orange-800',
    en_reparation: 'bg-blue-100 text-blue-800', indisponible: 'bg-gray-200 text-gray-700',
};
// Statuts propres au véhicule (distincts des statuts matériel ci-dessus).
const vehicleStatusStyles = {
    disponible: 'bg-green-100 text-green-800', indisponible: 'bg-gray-200 text-gray-700',
    maintenance: 'bg-amber-100 text-amber-800', reparation: 'bg-orange-100 text-orange-800',
    reforme: 'bg-red-100 text-red-800',
};
const modeLabels = { quantity: 'Quantité', serial: 'Unitaire', lot: 'Lot' };
</script>

<template>
    <AppLayout>
        <Head :title="vehicle.callsign || vehicle.name" />
        <template #title>{{ vehicle.callsign || vehicle.name }}</template>

        <Link href="/vehicles" class="inline-flex items-center gap-1.5 text-sm text-[var(--brand)] hover:underline"><Icon name="arrow-left" :size="16" /> Véhicules</Link>

        <!-- En-tête : indicatif en avant, type en badge, nom seulement s'il diffère -->
        <div class="mt-3 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-6 py-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-bold text-gray-900">{{ vehicle.callsign || vehicle.name }}</h2>
                        <span v-if="vehicle.type" class="rounded-md bg-[var(--brand)]/10 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-[var(--brand)]">{{ vehicle.type }}</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        <span v-if="vehicle.callsign && vehicle.name && vehicle.name !== vehicle.callsign">{{ vehicle.name }} · </span>
                        <span class="inline-flex items-center gap-1"><Icon name="tag" :size="14" /> {{ vehicle.registration || '—' }}</span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" title="QR d'accès véhicule" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showQr = true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm-2 8h8v8H3v-8zm2 2v4h4v-4H5zM13 3h8v8h-8V3zm2 2v4h4V5h-4zM13 13h3v3h-3v-3zm5 0h3v3h-3v-3zm-5 5h3v3h-3v-3zm5 0h3v3h-3v-3z"/></svg>
                        QR
                    </button>
                    <span class="rounded-full px-3 py-1 text-sm font-medium" :class="vehicleStatusStyles[vehicle.status] || 'bg-gray-100 text-gray-700'">{{ vehicle.status_label }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-6 px-6 py-4 text-sm">
                <div><span class="text-gray-500">Kilométrage :</span> <span class="font-medium">{{ vehicle.mileage != null ? Number(vehicle.mileage).toLocaleString('fr-FR') + ' km' : '—' }}</span></div>
                <div><span class="text-gray-500">Autorisés :</span> <span class="font-medium">{{ assigned.length ? assigned.join(', ') : '—' }}</span></div>
            </div>
        </div>

        <!-- Alertes -->
        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-red-700">{{ alerts.expired }}</p>
                <p class="text-xs text-gray-500">Lots périmés</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-amber-600">{{ alerts.expiring_soon }}</p>
                <p class="text-xs text-gray-500">Péremption &lt; 30 j</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-orange-600">{{ alerts.below_threshold }}</p>
                <p class="text-xs text-gray-500">Sous le seuil</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-gray-800">{{ alerts.anomalies }}</p>
                <p class="text-xs text-gray-500">Non conformes</p>
            </div>
        </div>

        <!-- Tâches véhicule -->
        <section class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-900">Tâches</h3>
                <p class="mt-0.5 text-sm text-gray-500">Tâches persistantes assignées au véhicule ; l'agent les coche depuis le terrain.</p>
            </div>
            <div class="px-6 py-4">
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="addTask">
                    <div class="min-w-[12rem] flex-1">
                        <InputLabel value="Nouvelle tâche" />
                        <TextInput v-model="taskForm.title" placeholder="Ex. Rapporter la bouteille O2 vide" />
                        <InputError :message="taskForm.errors.title" />
                    </div>
                    <div class="min-w-[10rem] flex-1">
                        <InputLabel value="Précisions (optionnel)" />
                        <TextInput v-model="taskForm.notes" />
                    </div>
                    <button type="submit" :disabled="taskForm.processing || !taskForm.title" class="rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Ajouter</button>
                </form>

                <ul class="mt-4 divide-y divide-gray-100">
                    <li v-for="t in tasks" :key="t.id" class="flex items-start gap-3 py-2.5">
                        <button type="button" class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2" :class="t.done ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 text-transparent hover:border-green-500'" :title="t.done ? 'Ré-ouvrir' : 'Marquer fait'" @click="t.done ? reopenTask(t.id) : completeTask(t.id)">
                            <Icon name="check" :size="12" />
                        </button>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium" :class="t.done ? 'text-gray-400 line-through' : 'text-gray-900'">{{ t.title }}</span>
                            <span v-if="t.notes" class="block text-xs text-gray-500">{{ t.notes }}</span>
                            <span class="block text-[11px] text-gray-400">
                                <template v-if="t.done">Fait le {{ t.done_at }}<template v-if="t.done_by"> par {{ t.done_by }}</template></template>
                                <template v-else-if="t.by">Demandé par {{ t.by }}</template>
                            </span>
                        </span>
                        <button class="shrink-0 text-xs text-red-500 hover:underline" @click="deleteTask(t.id)">Suppr.</button>
                    </li>
                    <li v-if="tasks.length === 0" class="py-4 text-center text-sm text-gray-400">Aucune tâche.</li>
                </ul>
            </div>
        </section>

        <!-- Carrosserie -->
        <section v-if="body.enabled" class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-gray-100 px-6 py-4">
                <Icon name="vehicle" :size="18" class="text-gray-500" />
                <h3 class="text-base font-semibold text-gray-900">Carrosserie</h3>
            </div>
            <div class="grid gap-6 p-6 lg:grid-cols-2">
                <div>
                    <VehicleBodyMap :damages="body.damages" :schematics="body.schematics || {}" editable @add="onBodyAdd" @select="bodyDetail = $event" />
                </div>
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Anomalies ({{ body.damages.filter((d) => d.status === 'ouverte').length }} ouvertes)</h4>
                    <ul class="mt-3 space-y-2">
                        <li v-for="(d, i) in body.damages" :key="d.id" class="flex items-center gap-2 text-sm">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white" :class="d.status === 'ouverte' ? 'bg-red-600' : 'bg-green-600'">{{ i + 1 }}</span>
                            <button class="min-w-0 flex-1 truncate text-left text-gray-700 hover:underline" @click="bodyDetail = d">{{ d.description }}</button>
                            <span class="shrink-0 text-xs text-gray-400">{{ viewLabel(d.view) }}</span>
                        </li>
                        <li v-if="body.damages.length === 0" class="py-4 text-center text-sm text-gray-400">Aucune anomalie carrosserie.</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Documents véhicule -->
        <section class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-900">Documents</h3>
                <p class="mt-0.5 text-sm text-gray-500">Agrément, contrôle technique, carte grise… Consultables par l'agent en service (avec motif).</p>
            </div>
            <div class="px-6 py-4">
                <form v-if="can_manage_documents" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submitDoc">
                    <div>
                        <InputLabel value="Type" />
                        <select v-model="docForm.category" class="block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                            <option v-for="c in docCategories" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Intitulé" />
                        <TextInput v-model="docForm.title" class="w-full" placeholder="Ex. CT valable jusqu'au…" />
                        <InputError :message="docForm.errors.title" />
                    </div>
                    <div>
                        <InputLabel value="Expiration" />
                        <TextInput v-model="docForm.expires_at" type="date" class="w-full" />
                    </div>
                    <div class="min-w-0">
                        <InputLabel value="Fichier (PDF/image)" />
                        <input type="file" accept=".pdf,image/*" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700" @change="docForm.file = $event.target.files[0]" />
                        <InputError :message="docForm.errors.file" />
                    </div>
                    <button type="submit" :disabled="docForm.processing || !docForm.title || !docForm.file" class="rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60 sm:col-span-2 lg:col-span-4 lg:w-auto lg:justify-self-start">Ajouter</button>
                </form>

                <ul class="mt-4 divide-y divide-gray-100">
                    <li v-for="d in documents" :key="d.id" class="flex items-center gap-3 py-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-500"><Icon name="template" :size="16" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-gray-900">{{ d.title }}</span>
                            <span class="block text-xs text-gray-400">{{ d.category }}<template v-if="d.expires_at"> · expire le {{ d.expires_at }}</template></span>
                        </span>
                        <a :href="`/documents/${d.id}/file`" target="_blank" class="text-xs font-medium text-[var(--brand)] hover:underline">Voir</a>
                        <button v-if="can_manage_documents" class="text-xs text-red-500 hover:underline" @click="deleteDoc(d.id)">Suppr.</button>
                    </li>
                    <li v-if="documents.length === 0" class="py-4 text-center text-sm text-gray-400">Aucun document.</li>
                </ul>
            </div>
        </section>


        <!-- Désinfection / nettoyage -->
        <section class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-semibold text-gray-900">Désinfection</h3>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="disinfectionBadgeClass()">{{ disinfection.state_label }}</span>
                </div>
                <button
                    v-if="disinfection.can_record"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--brand)] px-3 py-1.5 text-sm font-semibold text-white hover:brightness-110"
                    @click="showDisinfectionForm = !showDisinfectionForm"
                >
                    <Icon name="plus" :size="16" /> Enregistrer
                </button>
            </div>

            <div class="flex flex-wrap gap-x-8 gap-y-2 px-6 py-4 text-sm">
                <div><span class="text-gray-500">Dernière :</span> <span class="font-medium">{{ disinfection.last_at || 'jamais' }}</span></div>
                <div v-if="disinfection.interval_days"><span class="text-gray-500">Périodicité :</span> <span class="font-medium">{{ disinfection.interval_days }} j</span></div>
                <div v-if="disinfection.due_at"><span class="text-gray-500">Prochaine échéance :</span> <span class="font-medium">{{ disinfection.due_at }}</span></div>
                <div v-if="!disinfection.interval_days" class="text-gray-400">Aucune périodicité définie pour ce type de véhicule.</div>
            </div>

            <!-- Formulaire -->
            <form v-if="showDisinfectionForm && disinfection.can_record" class="border-t border-gray-100 bg-gray-50 px-6 py-4" @submit.prevent="submitDisinfection">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Type</label>
                        <select v-model="disinfectionForm.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="t in disinfection.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div v-if="disinfection.protocols && disinfection.protocols.length">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Protocole appliqué</label>
                        <select v-model="disinfectionForm.disinfection_protocol_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            <option v-for="p in disinfection.protocols" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Date et heure</label>
                        <input v-model="disinfectionForm.performed_at" type="datetime-local" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        <p v-if="disinfectionForm.errors.performed_at" class="mt-1 text-xs text-red-600">{{ disinfectionForm.errors.performed_at }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Note (facultatif)</label>
                        <input v-model="disinfectionForm.notes" type="text" maxlength="2000" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Produit, zone, remarque…" />
                    </div>
                </div>

                <!-- Réalisation : checklist des étapes du protocole -->
                <div v-if="disinfectionForm.steps.length" class="mt-4 rounded-lg border border-gray-200 bg-white p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Étapes du protocole</p>
                        <span class="text-xs text-gray-400">{{ disinfectionForm.steps.filter((s) => s.done).length }}/{{ disinfectionForm.steps.length }}</span>
                    </div>
                    <ul class="space-y-1.5">
                        <li v-for="(s, i) in disinfectionForm.steps" :key="i">
                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input v-model="s.done" type="checkbox" class="mt-0.5 rounded border-gray-300 text-[var(--brand)]" />
                                <span :class="s.done ? '' : 'text-gray-400'">{{ s.label }}</span>
                            </label>
                        </li>
                    </ul>
                </div>

                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-white" @click="showDisinfectionForm = false">Annuler</button>
                    <button type="submit" :disabled="disinfectionForm.processing" class="rounded-lg bg-[var(--brand)] px-4 py-1.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Enregistrer</button>
                </div>
            </form>

            <!-- Journal -->
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-6 py-2">Date</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Protocole</th><th class="px-4 py-2">Par</th><th class="px-4 py-2">Note</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="d in disinfection.records" :key="d.id">
                            <td class="px-6 py-2 whitespace-nowrap font-medium text-gray-900">{{ d.performed_at }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ d.type_label }}</td>
                            <td class="px-4 py-2 text-gray-500">
                                {{ d.protocol || '—' }}
                                <span v-if="d.steps && d.steps.total" class="ml-1 text-xs text-gray-400">({{ d.steps.done }}/{{ d.steps.total }})</span>
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ d.user || '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ d.notes || '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <button v-if="disinfection.can_record" type="button" class="text-gray-300 hover:text-red-600" title="Supprimer" @click="deleteDisinfection(d.id)"><Icon name="x" :size="15" /></button>
                            </td>
                        </tr>
                        <tr v-if="disinfection.records.length === 0"><td colspan="6" class="px-6 py-6 text-center text-gray-400">Aucune désinfection enregistrée.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Suivi mécanique -->
        <section class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-semibold text-gray-900">Suivi mécanique</h3>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="maintenanceBadgeClass()">{{ maintenance.state_label }}</span>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--brand)] px-3 py-1.5 text-sm font-semibold text-white hover:brightness-110"
                    @click="showMaintenanceForm = !showMaintenanceForm"
                >
                    <Icon name="plus" :size="16" /> Enregistrer
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-x-8 gap-y-2 px-6 py-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-gray-500">Compteur :</span>
                    <input v-model="mileageInput" type="number" min="0" class="w-28 rounded-lg border border-gray-300 px-2 py-1 text-sm" />
                    <span class="text-gray-400">km</span>
                    <button type="button" class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="saveMileage">Mettre à jour</button>
                </div>
                <div v-if="maintenance.next_due_at"><span class="text-gray-500">Prochaine échéance :</span> <span class="font-medium">{{ maintenance.next_due_at }}</span></div>
                <div v-if="maintenance.next_due_mileage"><span class="text-gray-500">Échéance km :</span> <span class="font-medium">{{ Number(maintenance.next_due_mileage).toLocaleString('fr-FR') }} km</span></div>
                <div v-if="!maintenance.next_due_at && !maintenance.next_due_mileage" class="text-gray-400">Aucune échéance planifiée.</div>
            </div>

            <form v-if="showMaintenanceForm" class="border-t border-gray-100 bg-gray-50 px-6 py-4" @submit.prevent="submitMaintenance">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Type</label>
                        <select v-model="maintenanceForm.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="t in maintenance.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Date</label>
                        <input v-model="maintenanceForm.performed_at" type="date" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        <p v-if="maintenanceForm.errors.performed_at" class="mt-1 text-xs text-red-600">{{ maintenanceForm.errors.performed_at }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Km au compteur</label>
                        <input v-model="maintenanceForm.mileage" type="number" min="0" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Coût (€)</label>
                        <input v-model="maintenanceForm.cost" type="number" min="0" step="0.01" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Prestataire</label>
                        <input v-model="maintenanceForm.provider" type="text" maxlength="150" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Prochaine échéance (date)</label>
                        <input v-model="maintenanceForm.next_due_at" type="date" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Prochaine échéance (km)</label>
                        <input v-model="maintenanceForm.next_due_mileage" type="number" min="0" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Note</label>
                        <input v-model="maintenanceForm.notes" type="text" maxlength="2000" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-white" @click="showMaintenanceForm = false">Annuler</button>
                    <button type="submit" :disabled="maintenanceForm.processing" class="rounded-lg bg-[var(--brand)] px-4 py-1.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Enregistrer</button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-6 py-2">Date</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Km</th><th class="px-4 py-2">Coût</th><th class="px-4 py-2">Prestataire</th><th class="px-4 py-2">Échéance</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="m in maintenance.records" :key="m.id">
                            <td class="px-6 py-2 whitespace-nowrap font-medium text-gray-900">{{ m.performed_at }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ m.type_label }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ m.mileage != null ? Number(m.mileage).toLocaleString('fr-FR') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ m.cost != null ? Number(m.cost).toLocaleString('fr-FR') + ' €' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ m.provider || '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">
                                <span v-if="m.next_due_at">{{ m.next_due_at }}</span>
                                <span v-if="m.next_due_mileage"> · {{ Number(m.next_due_mileage).toLocaleString('fr-FR') }} km</span>
                                <span v-if="!m.next_due_at && !m.next_due_mileage">—</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" class="text-gray-300 hover:text-red-600" title="Supprimer" @click="deleteMaintenance(m.id)"><Icon name="x" :size="15" /></button>
                            </td>
                        </tr>
                        <tr v-if="maintenance.records.length === 0"><td colspan="7" class="px-6 py-6 text-center text-gray-400">Aucune opération enregistrée.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Carburant (si le suivi est activé) -->
        <section v-if="fuel" class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-900">Carburant &amp; consommation</h3>
                <div class="flex gap-2 text-sm">
                    <span v-if="fuel.average" class="rounded-full bg-gray-100 px-2.5 py-0.5 font-medium text-gray-700">Moy. {{ fuel.average }} L/100 km</span>
                    <span v-if="fuel.total_cost" class="rounded-full bg-gray-100 px-2.5 py-0.5 font-medium text-gray-700">{{ fuel.total_cost }} € cumulés</span>
                </div>
            </div>

            <div class="px-6 py-4">
                <form class="grid grid-cols-2 gap-3 sm:grid-cols-5" @submit.prevent="submitFuel">
                    <div>
                        <InputLabel value="Litres" />
                        <TextInput v-model="fuelForm.liters" type="number" step="0.01" min="0" />
                        <InputError :message="fuelForm.errors.liters" />
                    </div>
                    <div>
                        <InputLabel value="Kilométrage" />
                        <TextInput v-model="fuelForm.mileage" type="number" min="0" />
                        <InputError :message="fuelForm.errors.mileage" />
                    </div>
                    <div>
                        <InputLabel value="Prix / litre (€)" />
                        <TextInput v-model="fuelForm.price_per_liter" type="number" step="0.001" min="0" />
                    </div>
                    <div>
                        <InputLabel value="Date" />
                        <TextInput v-model="fuelForm.filled_at" type="date" />
                    </div>
                    <div class="flex items-end">
                        <button type="submit" :disabled="fuelForm.processing || !fuelForm.liters || !fuelForm.mileage" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Enregistrer</button>
                    </div>
                    <label class="col-span-2 flex items-center gap-2 text-sm text-gray-700 sm:col-span-5">
                        <input v-model="fuelForm.full_tank" type="checkbox" class="rounded border-gray-300" /> Plein complet (à ras) — requis pour le calcul de conso
                    </label>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Date</th><th class="px-6 py-3">Km</th><th class="px-6 py-3">Litres</th>
                            <th class="px-6 py-3">€/L</th><th class="px-6 py-3">Conso</th><th class="px-6 py-3">Coût</th><th class="px-6 py-3">Par</th><th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in fuel.records" :key="r.id">
                            <td class="px-6 py-3">{{ r.filled_at }}</td>
                            <td class="px-6 py-3">{{ r.mileage != null ? Number(r.mileage).toLocaleString('fr-FR') : '—' }}</td>
                            <td class="px-6 py-3">{{ r.liters }} L<span v-if="!r.full_tank" class="ml-1 text-xs text-gray-400">(partiel)</span></td>
                            <td class="px-6 py-3">{{ r.price_per_liter ? r.price_per_liter + ' €' : '—' }}</td>
                            <td class="px-6 py-3">{{ r.consumption ? r.consumption + ' L/100' : '—' }}</td>
                            <td class="px-6 py-3">{{ r.cost ? r.cost + ' €' : '—' }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ r.user || '—' }}</td>
                            <td class="px-6 py-3 text-right"><button class="text-xs text-red-600 hover:underline" @click="deleteFuel(r.id)">Suppr.</button></td>
                        </tr>
                        <tr v-if="fuel.records.length === 0"><td colspan="8" class="px-6 py-6 text-center text-gray-400">Aucun plein enregistré.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Matériel par emplacement -->
        <div class="mt-6 space-y-6">
            <section v-for="loc in locations" :key="loc.id">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ loc.name }}</h3>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-2">Matériel</th><th class="px-4 py-2">Suivi</th><th class="px-4 py-2">Stock</th><th class="px-4 py-2">Péremption</th><th class="px-4 py-2">État</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in loc.materials" :key="m.id">
                                <td class="px-4 py-2">
                                    <Link :href="`/materials/${m.id}`" class="font-medium text-gray-900 hover:text-[var(--brand)] hover:underline">{{ m.name }}</Link>
                                    <span v-if="m.brand" class="text-gray-500"> · {{ m.brand }}</span>
                                    <span v-if="m.type" class="ml-1 rounded bg-gray-100 px-1 py-0.5 text-[11px] text-gray-600">{{ m.type }}</span>
                                    <span class="ml-1 text-xs text-gray-400">{{ m.reference }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ modeLabels[m.tracking_mode] }}</td>
                                <td class="px-4 py-2">
                                    <span :class="m.below_threshold ? 'font-semibold text-orange-600' : 'text-gray-700'">{{ m.stock }}</span>
                                    <span class="text-xs text-gray-400">/ {{ m.theoretical_qty }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span v-if="m.expired" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Périmé</span>
                                    <span v-else-if="m.expiring_soon" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ m.nearest_expiry }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyles[m.status]">{{ m.status_label }}</span></td>
                            </tr>
                            <tr v-if="loc.materials.length === 0"><td colspan="5" class="px-4 py-4 text-center text-gray-400">Aucun matériel dans cet emplacement.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p v-if="locations.length === 0" class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                Aucun emplacement pour ce véhicule. Ajoutez-en dans le menu « Emplacements ».
            </p>
        </div>

        <!-- Historique -->
        <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Historique</h3>
            <div class="mt-3"><HistoryList :logs="history" dense /></div>
        </section>

        <!-- Modale : nouvelle anomalie carrosserie -->
        <div v-if="bodyAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="bodyAdd = null">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Anomalie carrosserie</h3>
                <p class="mt-0.5 text-xs text-gray-500">Vue : {{ viewLabel(bodyForm.view) }}</p>
                <div class="mt-4">
                    <InputLabel value="Description" />
                    <textarea v-model="bodyForm.description" rows="3" placeholder="ex. Rayure profonde aile avant droite" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                    <InputError :message="bodyForm.errors.description" />
                </div>
                <div class="mt-3">
                    <InputLabel value="Photo (optionnel)" />
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="bodyForm.photo = $event.target.files?.[0] ?? null" />
                    <InputError :message="bodyForm.errors.photo" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium" @click="bodyAdd = null">Annuler</button>
                    <button type="button" :disabled="bodyForm.processing || !bodyForm.description" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submitBody">Enregistrer</button>
                </div>
            </div>
        </div>
        <!-- Modale : détail anomalie carrosserie -->
        <div v-if="bodyDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="bodyDetail = null">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Anomalie carrosserie</h3>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="bodyDetail.status === 'ouverte' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">{{ bodyDetail.status === 'ouverte' ? 'Ouverte' : 'Résolue' }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-800">{{ bodyDetail.description }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ viewLabel(bodyDetail.view) }}<template v-if="bodyDetail.reporter"> · signalée par {{ bodyDetail.reporter }}</template><template v-if="bodyDetail.created_at"> · {{ bodyDetail.created_at }}</template></p>
                <a v-if="bodyDetail.photo_url" :href="bodyDetail.photo_url" target="_blank" class="mt-3 block">
                    <img :src="bodyDetail.photo_url" alt="Photo de l'anomalie" class="max-h-72 w-full rounded-xl object-contain" />
                </a>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button v-if="bodyDetail.status === 'ouverte'" type="button" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700" @click="resolveBody(bodyDetail)">Marquer résolue</button>
                    <button v-if="body.can_delete" type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50" @click="deleteBody(bodyDetail)">Supprimer</button>
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium" @click="bodyDetail = null">Fermer</button>
                </div>
            </div>
        </div>
        <!-- Popup : QR d'accès véhicule -->
        <div v-if="showQr" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showQr = false">
            <div class="relative w-full max-w-xs">
                <button type="button" class="absolute -right-3 -top-3 z-10 rounded-full bg-white p-1.5 text-gray-500 shadow hover:text-gray-800" @click="showQr = false">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
                <VehicleQr :vehicle-id="vehicle.id" :label="vehicle.callsign || vehicle.name" />
            </div>
        </div>
    </AppLayout>
</template>
