<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    disinfection: { type: Object, default: () => ({}) },
    maintenance: { type: Object, default: () => ({}) },
    anomalies: { type: Array, default: () => [] },
    can_report_anomaly: { type: Boolean, default: false },
});

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

// --- Kilométrage ---
const mileageForm = useForm({ mileage: props.vehicle.mileage ?? '' });
function submitMileage() {
    mileageForm.post(`/vehicles/${props.vehicle.id}/mileage`, { preserveScroll: true });
}
</script>

<template>
    <TerrainLayout>
        <Head :title="`Terrain — ${vehicle.callsign || vehicle.name}`" />

        <!-- En-tête véhicule -->
        <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <Link href="/t" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100"><Icon name="arrow-left" :size="20" /></Link>
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500"><Icon name="vehicle" :size="22" /></span>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-bold text-gray-900">{{ vehicle.callsign || vehicle.name }}</h1>
                <p class="text-xs text-gray-500">{{ vehicle.type || '—' }} · {{ vehicle.status_label }}</p>
            </div>
        </div>

        <!-- Désinfection -->
        <section class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800"><Icon name="protocol" :size="16" /> Désinfection</h2>
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
        </section>

        <!-- Entretien -->
        <section class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800"><Icon name="settings" :size="16" /> Entretien</h2>
                <span v-if="maintenance.severity" class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevBadge[maintenance.severity]">{{ maintenance.state_label }}</span>
                <span v-else class="text-xs text-gray-400">{{ maintenance.state_label }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                <template v-if="maintenance.next_due_at">Échéance : {{ maintenance.next_due_at }}</template>
                <template v-if="maintenance.next_due_mileage"> · {{ maintenance.next_due_mileage.toLocaleString('fr-FR') }} km</template>
                <template v-if="!maintenance.next_due_at && !maintenance.next_due_mileage">Pas d'échéance planifiée.</template>
            </p>
            <form v-if="maintenance.can_update" class="mt-3 flex items-end gap-2" @submit.prevent="submitMileage">
                <div class="flex-1">
                    <label class="text-xs font-medium text-gray-600">Kilométrage actuel</label>
                    <input v-model="mileageForm.mileage" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm" />
                </div>
                <button type="submit" :disabled="mileageForm.processing" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60">Mettre à jour</button>
            </form>
        </section>

        <!-- Anomalies ouvertes -->
        <section v-if="anomalies.length" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800"><Icon name="bell" :size="16" /> Anomalies en cours ({{ anomalies.length }})</h2>
            <ul class="mt-2 divide-y divide-gray-100">
                <li v-for="a in anomalies" :key="a.id" class="flex items-center justify-between gap-2 py-2 text-sm">
                    <span class="min-w-0 flex-1 truncate text-gray-700">{{ a.title }}</span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="a.priority === 'haute' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'">{{ a.priority }}</span>
                </li>
            </ul>
        </section>

        <!-- Checklist matériel -->
        <h2 class="mb-2 mt-6 flex items-center justify-between text-sm font-semibold uppercase tracking-wide text-gray-500">
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
                <input v-model="disinfForm.performed_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm" />

                <label class="mt-3 block text-xs font-medium text-gray-600">Notes (optionnel)</label>
                <textarea v-model="disinfForm.notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm"></textarea>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium" @click="showDisinf = false">Annuler</button>
                    <button type="button" :disabled="disinfForm.processing" class="flex-1 rounded-xl bg-[var(--brand,#C6362B)] py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submitDisinf">Enregistrer</button>
                </div>
            </div>
        </div>
    </TerrainLayout>
</template>
