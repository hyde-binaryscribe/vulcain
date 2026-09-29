<script setup>
import { Head, Link } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    greeting_name: { type: String, default: '' },
    vehicles: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    qr_only: { type: Boolean, default: false },
    can_report_anomaly: { type: Boolean, default: false },
});

const statusStyles = {
    disponible: 'bg-green-100 text-green-800',
    indisponible: 'bg-gray-200 text-gray-700',
    maintenance: 'bg-amber-100 text-amber-800',
    reparation: 'bg-orange-100 text-orange-800',
    reforme: 'bg-red-100 text-red-800',
};
const sevDot = { critical: 'bg-red-500', warning: 'bg-orange-500', watch: 'bg-yellow-400' };
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Accueil" />
        <template #title>Bonjour {{ greeting_name?.split(' ')[0] }}</template>

        <!-- Mode « accès par QR uniquement » : on oriente vers le scan. -->
        <template v-if="qr_only">
            <Link href="/t/scanner" class="flex flex-col items-center gap-3 rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                <span class="flex h-20 w-20 items-center justify-center rounded-3xl bg-[var(--brand,#C6362B)]/10 text-[var(--brand,#C6362B)]"><Icon name="camera" :size="38" /></span>
                <span class="text-base font-semibold text-gray-900">Scanner un véhicule</span>
                <span class="text-sm text-gray-500">Scannez le QR code présent à bord pour ouvrir sa fiche.</span>
            </Link>
            <Link v-if="can_report_anomaly" href="/t/anomalie" class="mt-3 flex items-center gap-2 rounded-2xl border border-gray-200 bg-white p-3.5 text-sm font-medium text-gray-800 shadow-sm">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[var(--brand,#C6362B)]/10 text-[var(--brand,#C6362B)]"><Icon name="bell" :size="18" /></span>
                Signaler une anomalie
            </Link>
        </template>

        <template v-else>
        <!-- Synthèse alertes -->
        <div class="grid grid-cols-3 gap-2">
            <Link href="/t/anomalie" class="rounded-2xl border border-gray-200 bg-white p-3 text-center shadow-sm">
                <p class="text-2xl font-bold" :class="totals.anomalies ? 'text-red-600' : 'text-gray-300'">{{ totals.anomalies || 0 }}</p>
                <p class="mt-0.5 text-[11px] leading-tight text-gray-500">Anomalies</p>
            </Link>
            <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center shadow-sm">
                <p class="text-2xl font-bold" :class="totals.disinfection_due ? 'text-orange-600' : 'text-gray-300'">{{ totals.disinfection_due || 0 }}</p>
                <p class="mt-0.5 text-[11px] leading-tight text-gray-500">Désinf. à prévoir</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center shadow-sm">
                <p class="text-2xl font-bold" :class="totals.maintenance_due ? 'text-orange-600' : 'text-gray-300'">{{ totals.maintenance_due || 0 }}</p>
                <p class="mt-0.5 text-[11px] leading-tight text-gray-500">Entretien à prévoir</p>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="mt-4 grid grid-cols-2 gap-2">
            <Link href="/t/scanner" class="flex items-center gap-2 rounded-2xl border border-gray-200 bg-white p-3.5 text-sm font-medium text-gray-800 shadow-sm">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100"><Icon name="camera" :size="18" /></span>
                Scanner un véhicule
            </Link>
            <Link v-if="can_report_anomaly" href="/t/anomalie" class="flex items-center gap-2 rounded-2xl border border-gray-200 bg-white p-3.5 text-sm font-medium text-gray-800 shadow-sm">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[var(--brand,#C6362B)]/10 text-[var(--brand,#C6362B)]"><Icon name="bell" :size="18" /></span>
                Signaler une anomalie
            </Link>
        </div>

        <!-- Mes véhicules -->
        <h2 class="mb-2 mt-6 text-sm font-semibold uppercase tracking-wide text-gray-500">Mes véhicules</h2>
        <div class="space-y-2.5">
            <Link
                v-for="v in vehicles"
                :key="v.id"
                :href="`/t/vehicules/${v.id}`"
                class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm active:scale-[0.99]"
            >
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500"><Icon name="vehicle" :size="22" /></span>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-semibold text-gray-900">
                        <span class="truncate">{{ v.callsign || v.name }}</span>
                        <span v-if="v.disinfection_severity" class="h-2 w-2 shrink-0 rounded-full" :class="sevDot[v.disinfection_severity]" title="Désinfection"></span>
                        <span v-if="v.maintenance_severity" class="h-2 w-2 shrink-0 rounded-full" :class="sevDot[v.maintenance_severity]" title="Entretien"></span>
                    </p>
                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                        <span v-if="v.session_is_mine" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-1.5 py-0.5 text-[11px] font-medium text-green-700"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> En service</span>
                        <span v-else-if="v.session_holder" class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">Pris · {{ v.session_holder }}</span>
                        <span class="rounded-full px-1.5 py-0.5 text-[11px] font-medium" :class="statusStyles[v.status] || 'bg-gray-100 text-gray-600'">{{ v.status_label }}</span>
                        <span v-if="v.type" class="truncate">{{ v.type }}</span>
                    </p>
                </div>
                <span v-if="v.open_anomalies" class="flex h-6 min-w-6 items-center justify-center rounded-full bg-red-100 px-1.5 text-xs font-bold text-red-700">{{ v.open_anomalies }}</span>
                <Icon name="chevron-right" :size="18" class="shrink-0 text-gray-300" />
            </Link>

            <p v-if="vehicles.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Aucun véhicule affecté.
            </p>
        </div>
        </template>
    </TerrainLayout>
</template>
