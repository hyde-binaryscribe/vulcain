<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HistoryList from '@/Components/HistoryList.vue';

defineProps({
    vehicle: { type: Object, required: true },
    assigned: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    alerts: { type: Object, default: () => ({ expired: 0, expiring_soon: 0, below_threshold: 0, anomalies: 0 }) },
    history: { type: Array, default: () => [] },
});

const statusStyles = {
    conforme: 'bg-green-100 text-green-800', manquant: 'bg-amber-100 text-amber-800',
    hs: 'bg-red-100 text-red-800', a_remplacer: 'bg-orange-100 text-orange-800',
    en_reparation: 'bg-blue-100 text-blue-800', indisponible: 'bg-gray-200 text-gray-700',
};
const modeLabels = { quantity: 'Quantité', serial: 'Unitaire', lot: 'Lot' };
</script>

<template>
    <AppLayout>
        <Head :title="vehicle.name" />
        <template #title>{{ vehicle.name }}</template>

        <Link href="/vehicles" class="text-sm text-[var(--brand)] hover:underline">← Véhicules</Link>

        <!-- En-tête -->
        <div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between bg-[var(--brand)] px-6 py-4 text-white">
                <div>
                    <p class="text-2xl font-bold tracking-wide">{{ vehicle.type || 'ENGIN' }}</p>
                    <p class="text-sm text-white/80">{{ vehicle.name }} · {{ vehicle.callsign || '—' }} · {{ vehicle.registration || '—' }}</p>
                </div>
                <span class="rounded-full bg-white/20 px-3 py-1 text-sm">{{ vehicle.status_label }}</span>
            </div>
            <div class="flex flex-wrap gap-6 px-6 py-4 text-sm">
                <div><span class="text-gray-500">Centre :</span> <span class="font-medium">{{ vehicle.center || '—' }}</span></div>
                <div><span class="text-gray-500">Kilométrage :</span> <span class="font-medium">{{ vehicle.mileage ?? '—' }}</span></div>
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
