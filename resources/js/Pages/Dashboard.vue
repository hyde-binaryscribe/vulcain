<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({ vehicles: 0, vehicles_available: 0, users: 0 }) },
    alerts: { type: Object, default: () => ({}) },
    fleet: { type: Array, default: () => [] },
});

// Donut de disponibilité : trois arcs (disponible / en service / indisponible),
// tracés à l'échelle sur un cercle de rayon 56 (circonférence ≈ 351.86).
const CIRC = 2 * Math.PI * 56;
const availabilitySegments = computed(() => {
    const total = props.stats.vehicles || 0;
    const segs = [
        { key: 'available', label: 'Disponible', value: props.stats.vehicles_available ?? 0, color: '#0ca30c' },
        { key: 'in_service', label: 'En service', value: props.stats.vehicles_in_service ?? 0, color: '#2a78d6' },
        { key: 'unavailable', label: 'Indisponible', value: props.stats.vehicles_unavailable ?? 0, color: '#d03b3b' },
    ];
    let offset = 0;
    return segs.map((s) => {
        const len = total > 0 ? (s.value / total) * CIRC : 0;
        const arc = { ...s, dash: `${len} ${CIRC - len}`, offset: -offset };
        offset += len;
        return arc;
    });
});

// Pastille « Consommables » : manque N (rouge si critique, orange si à prévoir).
function consoChip(v) {
    if (!v.consumables) return { cls: 'bg-green-100 text-green-700', label: 'OK' };
    const cls = v.consumables === 'critical' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800';
    return { cls, label: `Manque ${v.consumables_missing}` };
}

// Échelle de gravité unifiée : rouge (critique) / orange (important) / jaune (à surveiller).
const alertCards = computed(() => [
    { label: 'Désinfections en retard', value: props.alerts.disinfection_overdue ?? 0, tone: 'red', href: '/vehicles' },
    { label: 'Périmés', value: props.alerts.expired ?? 0, tone: 'red', href: '/pharmacy' },
    { label: 'Entretiens en retard', value: props.alerts.maintenance_overdue ?? 0, tone: 'red', href: '/vehicles' },
    { label: 'Désinfections à prévoir', value: props.alerts.disinfection_soon ?? 0, tone: 'orange', href: '/vehicles' },
    { label: 'Entretiens à prévoir', value: props.alerts.maintenance_soon ?? 0, tone: 'orange', href: '/vehicles' },
    { label: 'Péremption < 30 j', value: props.alerts.expiring_soon ?? 0, tone: 'orange', href: '/pharmacy' },
    { label: 'Stock bas', value: props.alerts.low_stock ?? 0, tone: 'orange', href: '/pharmacy' },
    { label: 'Documents expirés', value: props.alerts.documents_expired ?? 0, tone: 'red', href: '/vehicles' },
    { label: 'Documents à renouveler', value: props.alerts.documents_soon ?? 0, tone: 'orange', href: '/vehicles' },
    { label: 'Événements ouverts', value: props.alerts.open_events ?? 0, tone: 'yellow', href: '/events' },
]);

// Pastille de gravité pour une dimension du parc (désinfection/entretien/docs).
const sevMap = {
    critical: { cls: 'bg-red-100 text-red-800', label: 'Retard' },
    warning: { cls: 'bg-orange-100 text-orange-800', label: 'À prévoir' },
    watch: { cls: 'bg-yellow-100 text-yellow-800', label: 'À surveiller' },
};
function sevChip(value) {
    return sevMap[value] || { cls: 'bg-green-100 text-green-700', label: 'OK' };
}
const fleetIssues = computed(() => props.fleet.filter((v) => v.worst > 0).length);

const toneStyles = {
    red: 'border-red-200 bg-red-50 text-red-700',
    orange: 'border-orange-200 bg-orange-50 text-orange-700',
    yellow: 'border-yellow-200 bg-yellow-50 text-yellow-800',
};
const dotStyles = { red: 'bg-red-500', orange: 'bg-orange-500', yellow: 'bg-yellow-400' };

const page = usePage();
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
</script>

<template>
    <AppLayout>
        <Head title="Tableau de bord" />
        <template #title>Tableau de bord</template>

        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Bonjour {{ user?.name }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ tenant?.name }} — {{ tenant?.profile?.tagline }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Véhicules</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: tenant?.profile?.theme }">{{ stats.vehicles }}</p>
                <p class="text-xs text-gray-500">{{ stats.vehicles_available }} disponible(s)</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Utilisateurs actifs</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ stats.users }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Protocoles</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ stats.protocols_total ?? 0 }}</p>
                <p class="text-xs text-gray-500">{{ stats.protocols_draft ?? 0 }} en cours</p>
            </div>
        </div>

        <!-- Disponibilité du parc -->
        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Disponibilité du parc</h2>
            <div class="mt-4 flex flex-wrap items-center gap-8">
                <svg width="150" height="150" viewBox="0 0 150 150" class="shrink-0">
                    <circle cx="75" cy="75" r="56" fill="none" stroke="#eef0f3" stroke-width="20" />
                    <g transform="rotate(-90 75 75)">
                        <circle
                            v-for="s in availabilitySegments"
                            :key="s.key"
                            cx="75" cy="75" r="56" fill="none" stroke-width="20"
                            :stroke="s.color"
                            :stroke-dasharray="s.dash"
                            :stroke-dashoffset="s.offset"
                        />
                    </g>
                    <text x="75" y="70" text-anchor="middle" font-size="26" font-weight="800" fill="#1B2430">{{ stats.vehicles }}</text>
                    <text x="75" y="90" text-anchor="middle" font-size="11" fill="#6B7280">véhicules</text>
                </svg>
                <div class="flex flex-col gap-3 text-sm">
                    <div v-for="s in availabilitySegments" :key="s.key" class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded" :style="{ background: s.color }"></span>
                        <span class="text-gray-700">{{ s.label }}</span>
                        <b class="ml-auto tabular-nums text-gray-900">{{ s.value }}</b>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertes -->
        <div class="mt-8 mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Alertes</h2>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> Critique</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span> Important</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span> À surveiller</span>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="a in alertCards"
                :key="a.label"
                :href="a.href"
                class="flex items-center justify-between rounded-2xl border p-5 shadow-sm transition hover:brightness-[0.98]"
                :class="a.value > 0 ? toneStyles[a.tone] : 'border-gray-200 bg-white text-gray-400'"
            >
                <div>
                    <p class="text-3xl font-bold">{{ a.value }}</p>
                    <p class="mt-1 text-xs font-medium uppercase tracking-wide">{{ a.label }}</p>
                </div>
                <span v-if="a.value > 0" class="h-3 w-3 rounded-full" :class="dotStyles[a.tone]"></span>
            </Link>
        </div>

        <!-- État du parc -->
        <div class="mt-8 mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">État du parc</h2>
            <span class="text-xs text-gray-500">
                <template v-if="fleetIssues > 0">{{ fleetIssues }} véhicule(s) demandant une action</template>
                <template v-else>Tout est à jour</template>
            </span>
        </div>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Véhicule</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3">Désinfection</th>
                            <th class="px-4 py-3">Entretien</th>
                            <th class="px-4 py-3">Documents</th>
                            <th class="px-4 py-3">Consommables</th>
                            <th class="px-4 py-3">Événements</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="v in fleet" :key="v.id" :class="v.worst >= 3 ? 'bg-red-50/40' : ''">
                            <td class="px-4 py-3">
                                <Link :href="`/vehicles/${v.id}`" class="font-medium text-gray-900 hover:underline">{{ v.callsign || v.name }}</Link>
                                <div class="text-xs text-gray-500">{{ v.type }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ v.status_label }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevChip(v.disinfection).cls">{{ sevChip(v.disinfection).label }}</span></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevChip(v.maintenance).cls">{{ sevChip(v.maintenance).label }}</span></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sevChip(v.documents).cls">{{ sevChip(v.documents).label }}</span></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="consoChip(v).cls">{{ consoChip(v).label }}</span></td>
                            <td class="px-4 py-3">
                                <Link v-if="v.open_events > 0" href="/events" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">{{ v.open_events }}</Link>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="fleet.length === 0"><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucun véhicule.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
