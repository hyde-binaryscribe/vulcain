<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';
import VehicleBodyMap from '@/Components/VehicleBodyMap.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    session: { type: Object, default: null },
    locations: { type: Array, default: () => [] },
    disinfection: { type: Object, default: () => ({}) },
    maintenance: { type: Object, default: () => ({}) },
    tasks: { type: Array, default: () => [] },
    documents: { type: Object, default: () => ({ vehicle: [], mine: [], consent: false }) },
    fuel: { type: Object, default: null },
    anomalies: { type: Array, default: () => [] },
    can_report_anomaly: { type: Boolean, default: false },
    body: { type: Object, default: () => ({ enabled: false, damages: [], schematics: {}, can_delete: false }) },
    // Section ouverte (page dédiée) ; null = menu du véhicule.
    section: { type: String, default: null },
});

// Navigation par page : chaque tuile ouvre une route dédiée.
const secUrl = (key) => `/t/vehicules/${props.vehicle.id}/s/${key}`;
const vehicleUrl = computed(() => `/t/vehicules/${props.vehicle.id}`);
const sectionLabels = {
    material: 'Matériel', body: 'Carrosserie', disinfection: 'Désinfection',
    fuel: 'Carburant', maintenance: 'Entretien', docs: 'Documents',
    mydocs: 'Mes documents', anomalies: 'Anomalies',
};
const sectionTitle = computed(() => sectionLabels[props.section] ?? '');

// Binôme : changement en cours de service.
const showPartner = ref(false);
const partnerForm = useForm({ partner_user_id: props.session?.partner_id ?? '' });
function savePartner() {
    partnerForm.post(`/t/vehicules/${props.vehicle.id}/binome`, {
        preserveScroll: true,
        onSuccess: () => { showPartner.value = false; },
    });
}

const viewLabels = { avant: 'Avant', arriere: 'Arrière', gauche: 'Côté gauche', droite: 'Côté droit', dessus: 'Dessus' };
const viewLabel = (v) => viewLabels[v] ?? v;

const openDamages = computed(() => props.body.damages.filter((d) => d.status === 'ouverte').length);

// Pastille de statut (point + couleur de texte) selon la gravité d'une dimension.
function sevClasses(sev) {
    if (sev === 'critical') return { dot: 'bg-red-500', text: 'text-red-700' };
    if (sev === 'warning') return { dot: 'bg-orange-500', text: 'text-orange-700' };
    if (sev === 'watch') return { dot: 'bg-yellow-400', text: 'text-yellow-800' };
    return null;
}
const okClasses = { dot: 'bg-green-500', text: 'text-green-700' };
const neutralClasses = { dot: 'bg-gray-300', text: 'text-gray-500' };
// Pastille verte (Disponible) sinon grise, pour l'état du véhicule.
const vehiclePill = computed(() => props.vehicle.status === 'disponible'
    ? 'bg-green-100 text-green-700'
    : 'bg-gray-200 text-gray-600');

const sevBadge = {
    critical: 'bg-red-100 text-red-800',
    warning: 'bg-orange-100 text-orange-800',
    watch: 'bg-yellow-100 text-yellow-800',
};
const statusChip = {
    conforme: 'bg-green-100 text-green-700',
    a_verifier: 'bg-yellow-100 text-yellow-800',
    anomalie: 'bg-red-100 text-red-700',
    hs: 'bg-red-100 text-red-700',
    absent: 'bg-gray-200 text-gray-600',
};

// --- Désinfection ---
function nowLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}
const showDisinf = ref(false);
const disinfForm = useForm({ type: props.disinfection.types?.[0]?.value ?? 'nettoyage', performed_at: nowLocal(), disinfection_protocol_id: '', steps: [], notes: '' });

function chooseProtocol(id) {
    disinfForm.disinfection_protocol_id = id;
    const proto = props.disinfection.protocols.find((p) => p.id === id);
    disinfForm.steps = proto ? proto.steps.map((label) => ({ label, done: false })) : [];
    if (proto) disinfForm.type = proto.type;
}
function submitDisinf() {
    disinfForm.transform((d) => ({ ...d, disinfection_protocol_id: d.disinfection_protocol_id || null }))
        .post(`/vehicles/${props.vehicle.id}/disinfections`, {
            preserveScroll: true,
            onSuccess: () => { showDisinf.value = false; disinfForm.reset(); disinfForm.performed_at = nowLocal(); },
        });
}


// --- Carburant (plein) ---
const showFuel = ref(false);
const fuelForm = useForm({
    filled_at: nowLocal(),
    mileage: props.vehicle.mileage ?? '',
    liters: '',
    price_per_liter: '',
    full_tank: true,
});
// Normalise un nombre saisi au clavier FR (virgule décimale) → point.
const num = (v) => String(v ?? '').replace(',', '.').trim();
// Coût total calculé depuis le prix au litre.
const fuelTotal = computed(() => {
    const l = parseFloat(num(fuelForm.liters));
    const p = parseFloat(num(fuelForm.price_per_liter));
    return l > 0 && p > 0 ? (l * p).toFixed(2) : null;
});
function submitFuel() {
    fuelForm.transform((d) => ({
        ...d,
        liters: num(d.liters),
        mileage: String(d.mileage ?? '').replace(/[^0-9]/g, ''),
        price_per_liter: d.price_per_liter ? num(d.price_per_liter) : null,
    })).post(`/vehicles/${props.vehicle.id}/fuel`, {
        preserveScroll: true,
        onSuccess: () => { showFuel.value = false; fuelForm.reset(); fuelForm.filled_at = nowLocal(); fuelForm.mileage = props.vehicle.mileage ?? ''; },
    });
}
function deleteFuel(id) {
    if (confirm('Supprimer ce plein ?')) {
        router.delete(`/vehicles/${props.vehicle.id}/fuel/${id}`, { preserveScroll: true });
    }
}

// --- Carrosserie ---
const bodyAdd = ref(null);        // { view } en cours de saisie
const bodyDetail = ref(null);     // point sélectionné
const bodyForm = useForm({ view: '', pos_x: 0, pos_y: 0, description: '', photo: null });
function onBodyAdd({ view, x, y }) {
    bodyForm.reset();
    bodyForm.clearErrors();
    bodyForm.view = view;
    bodyForm.pos_x = x;
    bodyForm.pos_y = y;
    bodyAdd.value = { view };
}
function onBodyPhoto(e) {
    bodyForm.photo = e.target.files?.[0] ?? null;
}
function submitBody() {
    bodyForm.post(`/vehicles/${props.vehicle.id}/body-damages`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { bodyAdd.value = null; bodyForm.reset(); },
    });
}
function resolveBody(d) {
    router.post(`/vehicles/${props.vehicle.id}/body-damages/${d.id}/resolve`, {}, {
        preserveScroll: true,
        onSuccess: () => { bodyDetail.value = null; },
    });
}
function deleteBody(d) {
    if (confirm('Supprimer cette anomalie carrosserie ?')) {
        router.delete(`/vehicles/${props.vehicle.id}/body-damages/${d.id}`, {
            preserveScroll: true,
            onSuccess: () => { bodyDetail.value = null; },
        });
    }
}

// --- Tâches ---
function completeTask(id) {
    router.post(`/vehicles/${props.vehicle.id}/tasks/${id}/complete`, {}, { preserveScroll: true });
}

// --- Documents (consultation avec motif) ---
const consulting = ref(null);
const reason = ref('Contrôle routier');
const reasonPresets = ['Contrôle routier', 'Contrôle ARS', 'Contrôle interne', 'Autre'];
function openConsult(doc) { consulting.value = doc; reason.value = 'Contrôle routier'; }
function confirmConsult() {
    const doc = consulting.value;
    if (!doc || !reason.value) return;
    router.post(`/documents/${doc.id}/consult`, { reason: reason.value }, {
        preserveScroll: true,
        onSuccess: () => { consulting.value = null; window.open(`/documents/${doc.id}/file`, '_blank'); },
    });
}
function setConsent(v) {
    router.post('/documents/consent', { consent: v }, { preserveScroll: true });
}
</script>

<template>
    <TerrainLayout>
        <Head :title="`Terrain — ${vehicle.callsign || vehicle.name}`" />

        <!-- Barre de retour (pages de section) -->
        <div v-if="section" class="mb-3 flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
            <Link :href="vehicleUrl" class="rounded-lg p-1 text-gray-500 hover:bg-gray-100"><Icon name="arrow-left" :size="22" /></Link>
            <div class="min-w-0">
                <p class="truncate text-lg font-bold text-gray-900">{{ sectionTitle }}</p>
                <p class="truncate text-xs text-gray-500">{{ vehicle.callsign || vehicle.name }}</p>
            </div>
        </div>

        <!-- Menu du véhicule -->
        <template v-if="!section">
        <!-- En-tête véhicule -->
        <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <Link href="/t" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100"><Icon name="arrow-left" :size="22" /></Link>
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gray-100 text-gray-600"><Icon name="vehicle" :size="28" /></span>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-3xl font-extrabold leading-none tracking-tight text-gray-900">{{ vehicle.callsign || vehicle.name }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <span>{{ vehicle.type || '—' }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold" :class="vehiclePill">
                        <span class="h-1.5 w-1.5 rounded-full" :class="vehicle.status === 'disponible' ? 'bg-green-600' : 'bg-gray-500'"></span>
                        {{ vehicle.status_label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Session de service -->
        <div v-if="session?.is_mine" class="mt-3 rounded-2xl border border-green-200 bg-green-50 p-3.5">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-sm text-green-800">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                    <span>Service ouvert<template v-if="session.opened_at"> depuis {{ session.opened_at }}</template></span>
                </div>
                <Link :href="`/t/vehicules/${vehicle.id}/fin-de-service`" class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50">
                    Clôturer le service
                </Link>
            </div>

            <!-- Binôme -->
            <div class="mt-3 border-t border-green-200 pt-3">
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="flex items-center gap-2 text-green-900">
                        <Icon name="users" :size="16" class="text-green-700" />
                        Binôme : <strong>{{ session.partner || 'aucun' }}</strong>
                    </span>
                    <button v-if="session.can_change_partner" type="button" class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-green-700 shadow-sm ring-1 ring-green-200 hover:bg-green-100" @click="showPartner = !showPartner">
                        Changer
                    </button>
                </div>
                <div v-if="showPartner" class="mt-2 flex gap-2">
                    <select v-model="partnerForm.partner_user_id" class="min-w-0 flex-1 rounded-lg border-gray-300 bg-white px-3 py-2 text-sm">
                        <option value="">— Aucun —</option>
                        <option v-for="c in (session.crew || [])" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <button type="button" :disabled="partnerForm.processing" class="shrink-0 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-60" @click="savePartner">Valider</button>
                </div>
            </div>
        </div>
        <div v-else class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-3.5 shadow-sm">
            <div class="flex min-w-0 items-center gap-2 text-sm">
                <template v-if="session">
                    <Icon name="user" :size="16" class="shrink-0 text-amber-600" />
                    <span class="truncate text-amber-800">En service : <strong>{{ session.holder }}</strong><template v-if="session.opened_at"> depuis {{ session.opened_at }}</template></span>
                </template>
                <template v-else>
                    <span class="h-2 w-2 shrink-0 rounded-full bg-gray-300"></span>
                    <span class="text-gray-600">Aucun service en cours</span>
                </template>
            </div>
            <Link :href="`/t/vehicules/${vehicle.id}/prise-de-service`" class="shrink-0 rounded-lg bg-[var(--brand,#C6362B)] px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110">
                {{ session ? 'Prendre (passation)' : 'Prendre le service' }}
            </Link>
        </div>

        <!-- Tâches à faire -->
        <section v-if="tasks.length" class="mt-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-4 shadow-sm">
            <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><Icon name="check" :size="17" /></span>
                Tâches à faire ({{ tasks.length }})
            </h2>
            <ul class="mt-2 space-y-2">
                <li v-for="t in tasks" :key="t.id" class="flex items-start gap-3 rounded-xl bg-white p-3 shadow-sm">
                    <button type="button" class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 text-transparent hover:border-green-500 hover:text-green-500" title="Marquer fait" @click="completeTask(t.id)">
                        <Icon name="check" :size="14" />
                    </button>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900">{{ t.title }}</span>
                        <span v-if="t.notes" class="block text-xs text-gray-500">{{ t.notes }}</span>
                        <span v-if="t.by" class="block text-[11px] text-gray-400">Demandé par {{ t.by }}</span>
                    </span>
                </li>
            </ul>
        </section>

        <!-- Carrosserie : information consultable (lecture + signalement). -->
        <button v-if="body.enabled" type="button" class="mt-3 flex w-full items-center gap-4 rounded-2xl border-l-4 border-amber-400 bg-white p-4 text-left shadow-sm" :class="section === 'body' ? 'ring-1 ring-amber-300' : ''" @click="router.visit(secUrl('body'))">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-600"><Icon name="vehicle" :size="26" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-lg font-bold text-gray-900">Carrosserie</span>
                <span class="block text-sm font-semibold" :class="openDamages ? 'text-amber-700' : 'text-green-700'">
                    {{ openDamages ? openDamages + ' signalement(s) · consulter / signaler' : 'Aucun signalement · signaler si besoin' }}
                </span>
            </span>
            <Icon name="chevron-right" :size="22" class="shrink-0 text-gray-300" />
        </button>

        <!-- Menu en tuiles (2 colonnes) -->
        <div class="mt-3 grid grid-cols-2 gap-3">
            <!-- Matériel -->
            <button type="button" class="relative flex flex-col rounded-2xl border p-4 text-left shadow-sm" :class="section === 'material' ? 'border-[var(--brand,#C6362B)] ring-1 ring-[var(--brand,#C6362B)] bg-white' : 'border-gray-200 bg-white'" @click="router.visit(secUrl('material'))">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-600"><Icon name="materials" :size="24" /></span>
                <span class="text-base font-bold leading-tight text-gray-900">Matériel</span>
                <span class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold" :class="neutralClasses.text"><span class="h-2 w-2 rounded-full" :class="neutralClasses.dot"></span>{{ locations.length }} emplacement(s)</span>
            </button>

            <!-- Désinfection -->
            <button type="button" class="relative flex flex-col rounded-2xl border p-4 text-left shadow-sm" :class="section === 'disinfection' ? 'border-[var(--brand,#C6362B)] ring-1 ring-[var(--brand,#C6362B)] bg-white' : 'border-gray-200 bg-white'" @click="router.visit(secUrl('disinfection'))">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-600"><Icon name="protocol" :size="24" /></span>
                <span class="text-base font-bold leading-tight text-gray-900">Désinfection</span>
                <span class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold" :class="(sevClasses(disinfection.severity) || okClasses).text"><span class="h-2 w-2 rounded-full" :class="(sevClasses(disinfection.severity) || okClasses).dot"></span>{{ disinfection.state_label || 'À jour' }}</span>
            </button>

            <!-- Entretien -->
            <button type="button" class="relative flex flex-col rounded-2xl border p-4 text-left shadow-sm" :class="section === 'maintenance' ? 'border-[var(--brand,#C6362B)] ring-1 ring-[var(--brand,#C6362B)] bg-white' : 'border-gray-200 bg-white'" @click="router.visit(secUrl('maintenance'))">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"><Icon name="settings" :size="24" /></span>
                <span class="text-base font-bold leading-tight text-gray-900">Entretien</span>
                <span v-if="maintenance.severity" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold" :class="sevClasses(maintenance.severity).text"><span class="h-2 w-2 rounded-full" :class="sevClasses(maintenance.severity).dot"></span>{{ maintenance.state_label }}</span>
                <span v-else class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold" :class="neutralClasses.text"><span class="h-2 w-2 rounded-full" :class="neutralClasses.dot"></span>{{ vehicle.mileage != null ? Number(vehicle.mileage).toLocaleString('fr-FR') + ' km' : '—' }}</span>
            </button>

            <!-- Carburant -->
            <button v-if="fuel" type="button" class="relative flex flex-col rounded-2xl border p-4 text-left shadow-sm" :class="section === 'fuel' ? 'border-[var(--brand,#C6362B)] ring-1 ring-[var(--brand,#C6362B)] bg-white' : 'border-gray-200 bg-white'" @click="router.visit(secUrl('fuel'))">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600"><Icon name="materials" :size="24" /></span>
                <span class="text-base font-bold leading-tight text-gray-900">Carburant</span>
                <span class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold" :class="fuel.average ? neutralClasses.text : okClasses.text"><span class="h-2 w-2 rounded-full" :class="fuel.average ? neutralClasses.dot : okClasses.dot"></span>{{ fuel.average ? fuel.average + ' L/100' : 'Plein' }}</span>
            </button>

            <!-- Documents -->
            <button v-if="documents.vehicle.length" type="button" class="relative flex flex-col rounded-2xl border p-4 text-left shadow-sm" :class="section === 'docs' ? 'border-[var(--brand,#C6362B)] ring-1 ring-[var(--brand,#C6362B)] bg-white' : 'border-gray-200 bg-white'" @click="router.visit(secUrl('docs'))">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600"><Icon name="template" :size="24" /></span>
                <span class="text-base font-bold leading-tight text-gray-900">Documents</span>
                <span class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold" :class="neutralClasses.text"><span class="h-2 w-2 rounded-full" :class="neutralClasses.dot"></span>{{ documents.vehicle.length }} document(s)</span>
            </button>

            <!-- Mes documents -->
            <button type="button" class="relative flex flex-col rounded-2xl border p-4 text-left shadow-sm" :class="section === 'mydocs' ? 'border-[var(--brand,#C6362B)] ring-1 ring-[var(--brand,#C6362B)] bg-white' : 'border-gray-200 bg-white'" @click="router.visit(secUrl('mydocs'))">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600"><Icon name="user" :size="24" /></span>
                <span class="text-base font-bold leading-tight text-gray-900">Mes documents</span>
                <span class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold" :class="documents.consent ? okClasses.text : neutralClasses.text"><span class="h-2 w-2 rounded-full" :class="documents.consent ? okClasses.dot : neutralClasses.dot"></span>{{ documents.consent ? 'Autorisé' : 'À autoriser' }}</span>
            </button>

            <!-- Anomalies (si présentes) -->
            <button v-if="anomalies.length" type="button" class="relative flex flex-col rounded-2xl border p-4 text-left shadow-sm" :class="section === 'anomalies' ? 'border-[var(--brand,#C6362B)] ring-1 ring-[var(--brand,#C6362B)] bg-white' : 'border-gray-200 bg-white'" @click="router.visit(secUrl('anomalies'))">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600"><Icon name="bell" :size="24" /></span>
                <span class="text-base font-bold leading-tight text-gray-900">Anomalies</span>
                <span class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-red-700"><span class="h-2 w-2 rounded-full bg-red-500"></span>{{ anomalies.length }} en cours</span>
                <span class="absolute right-2.5 top-2.5 flex h-6 min-w-6 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white">{{ anomalies.length }}</span>
            </button>
        </div>
        </template>

        <!-- Documents du véhicule -->
        <section v-if="section === 'docs' && documents.vehicle.length" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><Icon name="template" :size="17" /></span>
                Documents du véhicule
            </h2>
            <p class="mt-1 text-xs text-gray-500">Consultation tracée (motif demandé) — utile en cas de contrôle.</p>
            <ul class="mt-2 divide-y divide-gray-100">
                <li v-for="d in documents.vehicle" :key="d.id">
                    <button type="button" class="flex w-full items-center gap-3 py-2.5 text-left" @click="openConsult(d)">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500"><Icon name="template" :size="16" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-900">{{ d.title }}</span>
                            <span class="block text-xs text-gray-400">{{ d.category }}<template v-if="d.expires_at"> · exp. {{ d.expires_at }}</template></span>
                        </span>
                        <Icon name="eye" :size="18" class="shrink-0 text-gray-300" />
                    </button>
                </li>
            </ul>
        </section>

        <!-- Mes documents -->
        <section v-if="section === 'mydocs'" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><Icon name="user" :size="17" /></span>
                Mes documents
            </h2>
            <template v-if="documents.consent">
                <ul v-if="documents.mine.length" class="mt-2 divide-y divide-gray-100">
                    <li v-for="d in documents.mine" :key="d.id">
                        <button type="button" class="flex w-full items-center gap-3 py-2.5 text-left" @click="openConsult(d)">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500"><Icon name="template" :size="16" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-gray-900">{{ d.title }}</span>
                                <span class="block text-xs text-gray-400">{{ d.category }}<template v-if="d.expires_at"> · exp. {{ d.expires_at }}</template></span>
                            </span>
                            <Icon name="eye" :size="18" class="shrink-0 text-gray-300" />
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-2 text-xs text-gray-500">Aucun document personnel enregistré par l'administration.</p>
                <button type="button" class="mt-2 text-xs font-medium text-gray-400 hover:text-red-600" @click="setConsent(false)">Retirer mon autorisation</button>
            </template>
            <div v-else class="mt-2">
                <p class="text-xs text-gray-500">Pour présenter vos documents (diplômes, ARS, permis…) lors d'un contrôle, autorisez leur consultation dans l'application.</p>
                <button type="button" class="mt-2 w-full rounded-xl bg-[var(--brand,#C6362B)] py-2.5 text-sm font-semibold text-white hover:brightness-110" @click="setConsent(true)">
                    Autoriser la consultation de mes documents
                </button>
            </div>
        </section>

        <!-- Désinfection -->
        <section v-if="section === 'disinfection'" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600"><Icon name="protocol" :size="17" /></span>
                    Désinfection
                </h2>
                <span v-if="disinfection.severity" class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevBadge[disinfection.severity]">{{ disinfection.state_label }}</span>
                <span v-else class="text-xs text-gray-400">{{ disinfection.state_label }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                <template v-if="disinfection.last_at">Dernière : {{ disinfection.last_at }}</template>
                <template v-else>Aucune désinfection enregistrée.</template>
                <template v-if="disinfection.due_at"> · Prochaine : {{ disinfection.due_at }}</template>
            </p>
            <button v-if="disinfection.can_record" class="mt-3 w-full rounded-xl bg-[var(--brand,#C6362B)] py-2.5 text-sm font-semibold text-white hover:brightness-110" @click="showDisinf = true">
                Réaliser une désinfection
            </button>

            <!-- Historique des désinfections enregistrées -->
            <div v-if="disinfection.records && disinfection.records.length" class="mt-4">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Historique</h3>
                <ul class="divide-y divide-gray-100">
                    <li v-for="(d, i) in disinfection.records" :key="i" class="flex items-start gap-3 py-2.5">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600"><Icon name="check" :size="15" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-gray-900">{{ d.protocol || d.type_label }}</span>
                            <span class="block text-xs text-gray-500">{{ d.performed_at }}<template v-if="d.user"> · {{ d.user }}</template></span>
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Entretien -->
        <section v-if="section === 'maintenance'" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><Icon name="settings" :size="17" /></span>
                    Entretien
                </h2>
                <span v-if="maintenance.severity" class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevBadge[maintenance.severity]">{{ maintenance.state_label }}</span>
                <span v-else class="text-xs text-gray-400">{{ maintenance.state_label }}</span>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                <template v-if="maintenance.next_due_at">Échéance : {{ maintenance.next_due_at }}</template>
                <template v-if="maintenance.next_due_mileage"> · {{ maintenance.next_due_mileage.toLocaleString('fr-FR') }} km</template>
                <template v-if="!maintenance.next_due_at && !maintenance.next_due_mileage">Pas d'échéance planifiée.</template>
            </p>
            <!-- Lecture seule côté terrain : le kilométrage se met à jour à la prise / fin de service. -->
            <div class="mt-3 flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2.5">
                <span class="text-sm text-gray-500">Kilométrage actuel</span>
                <span class="text-base font-semibold text-gray-900">{{ vehicle.mileage != null ? Number(vehicle.mileage).toLocaleString('fr-FR') + ' km' : '—' }}</span>
            </div>
        </section>

        <!-- Carburant (si le suivi est activé) -->
        <section v-if="section === 'fuel' && fuel" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><Icon name="materials" :size="17" /></span>
                    Carburant
                </h2>
                <span v-if="fuel.last" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ fuel.last }} L/100</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                <template v-if="fuel.average">Conso moyenne : <strong>{{ fuel.average }} L/100 km</strong></template>
                <template v-else>Enregistrez deux pleins « à ras » pour calculer la consommation.</template>
                <template v-if="fuel.total_cost"> · Coût cumulé : {{ fuel.total_cost }} €</template>
            </p>

            <ul v-if="fuel.records && fuel.records.length" class="mt-2 divide-y divide-gray-100">
                <li v-for="r in fuel.records" :key="r.id" class="flex items-center gap-2 py-2 text-sm">
                    <span class="min-w-0 flex-1">
                        <span class="text-gray-900">{{ r.liters }} L<template v-if="!r.full_tank"> (partiel)</template></span>
                        <span class="ml-2 text-xs text-gray-400">{{ r.filled_at }} · {{ r.mileage != null ? r.mileage.toLocaleString('fr-FR') : '—' }} km</span>
                    </span>
                    <span v-if="r.consumption" class="shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-600">{{ r.consumption }} L/100</span>
                    <span v-if="r.cost" class="shrink-0 text-xs text-gray-500">{{ r.cost }} €</span>
                    <button v-if="fuel.can_delete" class="shrink-0 rounded p-1 text-gray-300 hover:bg-red-50 hover:text-red-600" title="Supprimer" @click="deleteFuel(r.id)"><Icon name="x" :size="13" /></button>
                </li>
            </ul>

            <button type="button" class="mt-3 w-full rounded-xl border border-gray-300 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="showFuel = true">
                Enregistrer un plein
            </button>
        </section>

        <!-- Carrosserie -->
        <section v-if="section === 'body' && body.enabled" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-[var(--brand,#C6362B)]"><Icon name="vehicle" :size="17" /></span>
                Carrosserie
            </h2>
            <p class="mt-0.5 text-xs text-gray-500">Contrôle à la prise et à la fin de service. Touchez le schéma pour signaler un choc, une rayure, un bris.</p>
            <div class="mt-3">
                <VehicleBodyMap :damages="body.damages" :schematics="body.schematics || {}" editable @add="onBodyAdd" @select="bodyDetail = $event" />
            </div>
            <ul v-if="body.damages.length" class="mt-3 space-y-1.5">
                <li v-for="(d, i) in body.damages" :key="d.id" class="flex items-center gap-2 text-sm">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white" :class="d.status === 'ouverte' ? 'bg-red-600' : 'bg-green-600'">{{ i + 1 }}</span>
                    <button class="min-w-0 flex-1 truncate text-left text-gray-700" @click="bodyDetail = d">{{ d.description }}</button>
                    <span class="shrink-0 text-[11px]" :class="d.status === 'ouverte' ? 'text-gray-400' : 'text-green-600'">{{ viewLabel(d.view) }}</span>
                </li>
            </ul>
            <p v-else class="mt-2 text-xs text-gray-400">Aucune anomalie carrosserie enregistrée.</p>
        </section>

        <!-- Anomalies ouvertes -->
        <section v-if="section === 'anomalies' && anomalies.length" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="flex items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-600"><Icon name="bell" :size="17" /></span>
                Anomalies en cours ({{ anomalies.length }})
            </h2>
            <ul class="mt-2 divide-y divide-gray-100">
                <li v-for="a in anomalies" :key="a.id" class="flex items-center gap-2 py-2 text-sm">
                    <a v-if="a.photo_url" :href="a.photo_url" target="_blank" class="shrink-0">
                        <img :src="a.photo_url" alt="" class="h-9 w-9 rounded-md object-cover" />
                    </a>
                    <span class="min-w-0 flex-1 truncate text-gray-700">{{ a.title }}</span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="a.priority === 'haute' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'">{{ a.priority }}</span>
                </li>
            </ul>
        </section>

        <!-- Checklist matériel -->
        <template v-if="section === 'material'">
        <h2 class="mb-2 mt-3 flex items-center justify-between text-sm font-semibold uppercase tracking-wide text-gray-500">
            Vérification matériel
            <Link v-if="can_report_anomaly" :href="`/t/anomalie?vehicle_id=${vehicle.id}`" class="inline-flex items-center gap-1 rounded-lg bg-white px-2 py-1 text-xs font-medium normal-case text-[var(--brand,#C6362B)] shadow-sm">
                <Icon name="plus" :size="13" /> Anomalie
            </Link>
        </h2>
        <div class="space-y-3">
            <div v-for="loc in locations" :key="loc.id" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <p class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">{{ loc.name }}</p>
                <ul class="divide-y divide-gray-100">
                    <li v-for="m in loc.materials" :key="m.id" class="flex items-center gap-2 px-4 py-2.5 text-sm">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-gray-900">{{ m.name }}</span>
                            <span v-if="m.reference" class="block truncate text-xs text-gray-400">{{ m.reference }}</span>
                        </span>
                        <span v-if="m.below_threshold" class="shrink-0 rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">stock bas</span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusChip[m.status] || 'bg-gray-100 text-gray-600'">{{ m.status_label }}</span>
                    </li>
                    <li v-if="loc.materials.length === 0" class="px-4 py-3 text-xs text-gray-400">Aucun matériel.</li>
                </ul>
            </div>
            <p v-if="locations.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Aucun emplacement configuré.
            </p>
        </div>
        </template>

        <!-- Modale désinfection -->
        <div v-if="showDisinf" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40" @click.self="showDisinf = false">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px))">
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-gray-300"></div>
                <h3 class="text-lg font-bold text-gray-900">Réaliser une désinfection</h3>

                <label class="mt-4 block text-xs font-medium text-gray-600">Protocole</label>
                <div class="mt-1 flex flex-wrap gap-2">
                    <button
                        v-for="p in disinfection.protocols" :key="p.id" type="button"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium"
                        :class="disinfForm.disinfection_protocol_id === p.id ? 'border-[var(--brand,#C6362B)] bg-[var(--brand,#C6362B)]/10 text-[var(--brand,#C6362B)]' : 'border-gray-300 text-gray-600'"
                        @click="chooseProtocol(p.id)"
                    >{{ p.name }}</button>
                </div>

                <div v-if="disinfForm.steps.length" class="mt-4 space-y-1.5">
                    <label class="block text-xs font-medium text-gray-600">Checklist</label>
                    <label v-for="(s, i) in disinfForm.steps" :key="i" class="flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                        <input v-model="s.done" type="checkbox" class="mt-0.5 rounded border-gray-300" />
                        <span :class="s.done ? 'text-gray-400 line-through' : 'text-gray-700'">{{ s.label }}</span>
                    </label>
                </div>

                <label class="mt-4 block text-xs font-medium text-gray-600">Date</label>
                <input v-model="disinfForm.performed_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white" />
                <p v-if="disinfForm.errors.performed_at" class="mt-1 text-xs text-red-600">{{ disinfForm.errors.performed_at }}</p>

                <label class="mt-3 block text-xs font-medium text-gray-600">Notes (optionnel)</label>
                <textarea v-model="disinfForm.notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white"></textarea>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium" @click="showDisinf = false">Annuler</button>
                    <button type="button" :disabled="disinfForm.processing" class="flex-1 rounded-xl bg-[var(--brand,#C6362B)] py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submitDisinf">Enregistrer</button>
                </div>
            </div>
        </div>
        <!-- Modale plein de carburant -->
        <div v-if="showFuel" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40" @click.self="showFuel = false">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px))">
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-gray-300"></div>
                <h3 class="text-lg font-bold text-gray-900">Enregistrer un plein</h3>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Litres</label>
                        <input v-model="fuelForm.liters" type="text" inputmode="decimal" placeholder="ex. 48,5" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                        <p v-if="fuelForm.errors.liters" class="mt-1 text-xs text-red-600">{{ fuelForm.errors.liters }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Kilométrage</label>
                        <input v-model="fuelForm.mileage" type="text" inputmode="numeric" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                        <p v-if="fuelForm.errors.mileage" class="mt-1 text-xs text-red-600">{{ fuelForm.errors.mileage }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Prix au litre (€, optionnel)</label>
                        <input v-model="fuelForm.price_per_liter" type="text" inputmode="decimal" placeholder="ex. 1,859" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Date</label>
                        <input v-model="fuelForm.filled_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                        <p v-if="fuelForm.errors.filled_at" class="mt-1 text-xs text-red-600">{{ fuelForm.errors.filled_at }}</p>
                    </div>
                </div>

                <p v-if="fuelForm.errors.cost || fuelForm.errors.price_per_liter" class="mt-2 text-xs text-red-600">{{ fuelForm.errors.cost || fuelForm.errors.price_per_liter }}</p>

                <p v-if="fuelTotal" class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600">
                    Coût total : <span class="font-semibold text-gray-900">{{ fuelTotal }} €</span>
                </p>

                <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="fuelForm.full_tank" type="checkbox" class="rounded border-gray-300" />
                    Plein complet (à ras) — nécessaire au calcul de la conso
                </label>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium" @click="showFuel = false">Annuler</button>
                    <button type="button" :disabled="fuelForm.processing || !fuelForm.liters || !fuelForm.mileage" class="flex-1 rounded-xl bg-[var(--brand,#C6362B)] py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submitFuel">Enregistrer</button>
                </div>
            </div>
        </div>
        <!-- Modale : nouvelle anomalie carrosserie -->
        <div v-if="bodyAdd" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40" @click.self="bodyAdd = null">
            <div class="w-full rounded-t-3xl bg-white p-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px))">
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-gray-300"></div>
                <h3 class="text-lg font-bold text-gray-900">Anomalie carrosserie</h3>
                <p class="mt-0.5 text-xs text-gray-500">Vue : {{ viewLabel(bodyForm.view) }}</p>
                <label class="mt-3 block text-xs font-medium text-gray-600">Description</label>
                <textarea v-model="bodyForm.description" rows="3" placeholder="ex. Rayure profonde aile avant droite" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white"></textarea>
                <p v-if="bodyForm.errors.description" class="mt-1 text-xs text-red-600">{{ bodyForm.errors.description }}</p>
                <label class="mt-3 flex items-center gap-1.5 text-xs font-medium text-gray-600"><Icon name="camera" :size="14" /> Photo (appareil ou galerie, optionnel)</label>
                <input type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium" @change="onBodyPhoto" />
                <p v-if="bodyForm.errors.photo" class="mt-1 text-xs text-red-600">{{ bodyForm.errors.photo }}</p>
                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium" @click="bodyAdd = null">Annuler</button>
                    <button type="button" :disabled="bodyForm.processing || !bodyForm.description" class="flex-1 rounded-xl bg-[var(--brand,#C6362B)] py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submitBody">Enregistrer</button>
                </div>
            </div>
        </div>
        <!-- Modale : détail anomalie carrosserie -->
        <div v-if="bodyDetail" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40" @click.self="bodyDetail = null">
            <div class="w-full rounded-t-3xl bg-white p-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px))">
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-gray-300"></div>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Anomalie carrosserie</h3>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="bodyDetail.status === 'ouverte' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">{{ bodyDetail.status === 'ouverte' ? 'Ouverte' : 'Résolue' }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-800">{{ bodyDetail.description }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ viewLabel(bodyDetail.view) }}<template v-if="bodyDetail.reporter"> · signalée par {{ bodyDetail.reporter }}</template><template v-if="bodyDetail.created_at"> · {{ bodyDetail.created_at }}</template></p>
                <a v-if="bodyDetail.photo_url" :href="bodyDetail.photo_url" target="_blank" class="mt-3 block">
                    <img :src="bodyDetail.photo_url" alt="Photo de l'anomalie" class="max-h-64 w-full rounded-xl object-cover" />
                </a>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button v-if="bodyDetail.status === 'ouverte'" type="button" class="flex-1 rounded-xl bg-green-600 py-2.5 text-sm font-semibold text-white hover:bg-green-700" @click="resolveBody(bodyDetail)">Marquer résolue</button>
                    <button v-if="body.can_delete" type="button" class="rounded-xl border border-red-300 px-4 py-2.5 text-sm font-medium text-red-600" @click="deleteBody(bodyDetail)">Supprimer</button>
                    <button type="button" class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium" @click="bodyDetail = null">Fermer</button>
                </div>
            </div>
        </div>
        <!-- Modale motif de consultation -->
        <div v-if="consulting" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40" @click.self="consulting = null">
            <div class="w-full rounded-t-3xl bg-white p-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px))">
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-gray-300"></div>
                <h3 class="text-lg font-bold text-gray-900">Consulter « {{ consulting.title }} »</h3>
                <p class="mt-1 text-sm text-gray-500">Indiquez le motif — la consultation est tracée.</p>
                <div class="mt-3 space-y-2">
                    <button
                        v-for="r in reasonPresets" :key="r" type="button"
                        class="w-full rounded-xl border px-4 py-3 text-left text-sm font-medium"
                        :class="reason === r ? 'border-[var(--brand,#C6362B)] bg-[var(--brand,#C6362B)]/10 text-[var(--brand,#C6362B)]' : 'border-gray-300 text-gray-700'"
                        @click="reason = r"
                    >{{ r }}</button>
                </div>
                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium" @click="consulting = null">Annuler</button>
                    <button type="button" class="flex-1 rounded-xl bg-[var(--brand,#C6362B)] py-2.5 text-sm font-semibold text-white hover:brightness-110" @click="confirmConsult">Ouvrir le document</button>
                </div>
            </div>
        </div>
    </TerrainLayout>
</template>
