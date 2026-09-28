<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HistoryList from '@/Components/HistoryList.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    assigned: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    alerts: { type: Object, default: () => ({ expired: 0, expiring_soon: 0, below_threshold: 0, anomalies: 0 }) },
    disinfection: { type: Object, default: () => ({ records: [], types: [], can_record: false, state: 'none' }) },
    history: { type: Array, default: () => [] },
});

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
    performed_at: nowLocal(),
    notes: '',
});
function submitDisinfection() {
    disinfectionForm.post(`/vehicles/${props.vehicle.id}/disinfections`, {
        preserveScroll: true,
        onSuccess: () => {
            disinfectionForm.reset('notes');
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
                <span class="shrink-0 rounded-full px-3 py-1 text-sm font-medium" :class="vehicleStatusStyles[vehicle.status] || 'bg-gray-100 text-gray-700'">{{ vehicle.status_label }}</span>
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
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Type</label>
                        <select v-model="disinfectionForm.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="t in disinfection.types" :key="t.value" :value="t.value">{{ t.label }}</option>
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
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-white" @click="showDisinfectionForm = false">Annuler</button>
                    <button type="submit" :disabled="disinfectionForm.processing" class="rounded-lg bg-[var(--brand)] px-4 py-1.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Enregistrer</button>
                </div>
            </form>

            <!-- Journal -->
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-6 py-2">Date</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Par</th><th class="px-4 py-2">Note</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="d in disinfection.records" :key="d.id">
                            <td class="px-6 py-2 whitespace-nowrap font-medium text-gray-900">{{ d.performed_at }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ d.type_label }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ d.user || '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ d.notes || '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <button v-if="disinfection.can_record" type="button" class="text-gray-300 hover:text-red-600" title="Supprimer" @click="deleteDisinfection(d.id)"><Icon name="x" :size="15" /></button>
                            </td>
                        </tr>
                        <tr v-if="disinfection.records.length === 0"><td colspan="5" class="px-6 py-6 text-center text-gray-400">Aucune désinfection enregistrée.</td></tr>
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
    </AppLayout>
</template>
