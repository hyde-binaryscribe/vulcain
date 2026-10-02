<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    availability: { type: Object, default: () => ({}) },
    alerts: { type: Object, default: () => ({}) },
    fleet: { type: Array, default: () => [] },
});

// Donut de disponibilité, tracé à l'échelle (cercle r=56, circonférence ≈ 351.86).
const CIRC = 2 * Math.PI * 56;
const segments = computed(() => {
    const total = props.availability.total || 0;
    const segs = [
        { key: 'available', label: 'Disponible', value: props.availability.available ?? 0, color: '#0ca30c' },
        { key: 'in_service', label: 'En service', value: props.availability.in_service ?? 0, color: '#2a78d6' },
        { key: 'unavailable', label: 'Indisponible', value: props.availability.unavailable ?? 0, color: '#d03b3b' },
    ];
    let offset = 0;
    return segs.map((s) => {
        const len = total > 0 ? (s.value / total) * CIRC : 0;
        const arc = { ...s, dash: `${len} ${CIRC - len}`, offset: -offset };
        offset += len;
        return arc;
    });
});

const alertCards = computed(() => [
    { label: 'Entretiens en retard', value: props.alerts.maintenance_overdue ?? 0, tone: 'c' },
    { label: 'Désinfections à prévoir', value: props.alerts.disinfection_soon ?? 0, tone: 'w' },
    { label: 'Désinfections en retard', value: props.alerts.disinfection_overdue ?? 0, tone: 'c' },
    { label: 'Parc à jour', value: props.alerts.up_to_date ?? 0, tone: 'g' },
]);
const toneCard = {
    c: 'border-red-200 bg-red-50 text-red-800',
    w: 'border-orange-200 bg-orange-50 text-orange-700',
    g: 'border-green-200 bg-green-50 text-green-800',
};

// 5 KPI de tête.
const kpis = computed(() => [
    { l: 'Véhicules', v: props.stats.vehicles ?? 0, d: 'parc total', color: null },
    { l: 'Disponibles', v: props.stats.available ?? 0, d: 'prêts à partir', color: '#0ca30c' },
    { l: 'En service', v: props.stats.in_service ?? 0, d: 'sortie en cours', color: '#2a78d6' },
    { l: 'Échéances en retard', v: props.stats.overdue ?? 0, d: 'entretien + désinfection', color: props.stats.overdue > 0 ? '#d03b3b' : null },
    { l: 'Documents expirés', v: props.stats.documents_expired ?? 0, d: 'carte grise / agrément', color: props.stats.documents_expired > 0 ? '#d03b3b' : null },
]);

const sevMap = {
    critical: { cls: 'bg-red-100 text-red-800', label: 'Retard' },
    warning: { cls: 'bg-orange-100 text-orange-800', label: 'À prévoir' },
    watch: { cls: 'bg-yellow-100 text-yellow-800', label: 'À surveiller' },
};
function sevChip(value) {
    return sevMap[value] || { cls: 'bg-green-100 text-green-700', label: 'OK' };
}
function consoChip(v) {
    if (!v.consumables) return { cls: 'bg-green-100 text-green-700', label: 'OK' };
    const cls = v.consumables === 'critical' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800';
    return { cls, label: `Manque ${v.consumables_missing}` };
}
const fleetIssues = computed(() => props.fleet.filter((v) => v.worst > 0).length);
</script>

<template>
    <AppLayout>
        <Head title="Tableau de bord — Parc véhicules" />
        <template #title>Parc véhicules</template>

        <!-- KPI -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div v-for="k in kpis" :key="k.l" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ k.l }}</p>
                <p class="mt-1 text-3xl font-bold" :style="k.color ? { color: k.color } : { color: '#1B2430' }">{{ k.v }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ k.d }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[360px_1fr]">
            <!-- Disponibilité -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900">Disponibilité</h2>
                <div class="mt-4 flex items-center gap-5">
                    <svg width="150" height="150" viewBox="0 0 150 150" class="shrink-0">
                        <circle cx="75" cy="75" r="56" fill="none" stroke="#eef0f3" stroke-width="20" />
                        <g transform="rotate(-90 75 75)">
                            <circle v-for="s in segments" :key="s.key" cx="75" cy="75" r="56" fill="none" stroke-width="20"
                                :stroke="s.color" :stroke-dasharray="s.dash" :stroke-dashoffset="s.offset" />
                        </g>
                        <text x="75" y="70" text-anchor="middle" font-size="26" font-weight="800" fill="#1B2430">{{ availability.total ?? 0 }}</text>
                        <text x="75" y="90" text-anchor="middle" font-size="11" fill="#6B7280">véhicules</text>
                    </svg>
                    <div class="flex flex-col gap-2.5 text-sm">
                        <div v-for="s in segments" :key="s.key" class="flex items-center gap-2.5">
                            <span class="h-3 w-3 rounded" :style="{ background: s.color }"></span>
                            <span class="text-gray-700">{{ s.label }}</span>
                            <b class="ml-auto tabular-nums text-gray-900">{{ s.value }}</b>
                        </div>
                    </div>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div v-for="a in alertCards" :key="a.label" class="rounded-xl border p-3.5" :class="toneCard[a.tone]">
                        <p class="text-2xl font-bold leading-none">{{ a.value }}</p>
                        <p class="mt-1 text-xs font-semibold">{{ a.label }}</p>
                    </div>
                </div>
            </div>

            <!-- État du parc -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900">État du parc — véhicule par véhicule</h2>
                    <Link href="/vehicles" class="text-xs font-semibold text-[var(--brand)] hover:underline">Tout voir ›</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="px-3 py-2.5">Véhicule</th>
                                <th class="px-3 py-2.5">Statut</th>
                                <th class="px-3 py-2.5">Désinfection</th>
                                <th class="px-3 py-2.5">Entretien</th>
                                <th class="px-3 py-2.5">Documents</th>
                                <th class="px-3 py-2.5">Consommables</th>
                                <th class="px-3 py-2.5">Événements</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="v in fleet" :key="v.id" :class="v.worst >= 3 ? 'bg-red-50/40' : ''">
                                <td class="px-3 py-3">
                                    <Link :href="`/vehicles/${v.id}`" class="font-semibold text-gray-900 hover:underline">{{ v.callsign || v.name }}</Link>
                                    <div class="text-xs text-gray-500">{{ v.type }}</div>
                                </td>
                                <td class="px-3 py-3 text-gray-600">{{ v.status_label }}</td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevChip(v.disinfection).cls">{{ sevChip(v.disinfection).label }}</span></td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevChip(v.maintenance).cls">{{ sevChip(v.maintenance).label }}</span></td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevChip(v.documents).cls">{{ sevChip(v.documents).label }}</span></td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="consoChip(v).cls">{{ consoChip(v).label }}</span></td>
                                <td class="px-3 py-3">
                                    <Link v-if="v.open_events > 0" href="/events" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold" :class="v.worst >= 3 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'">{{ v.open_events }}</Link>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="fleet.length === 0"><td colspan="7" class="px-3 py-8 text-center text-gray-500">Aucun véhicule.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    <template v-if="fleetIssues > 0">{{ fleetIssues }} véhicule(s) demandant une action — remontés en tête.</template>
                    <template v-else>Tout le parc est à jour.</template>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
