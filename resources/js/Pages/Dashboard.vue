<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({ vehicles: 0, vehicles_available: 0, users: 0 }) },
    alerts: { type: Object, default: () => ({}) },
});

// Échelle de gravité unifiée : rouge (critique) / orange (important) / jaune (à surveiller).
const alertCards = computed(() => [
    { label: 'Désinfections en retard', value: props.alerts.disinfection_overdue ?? 0, tone: 'red', href: '/parc' },
    { label: 'Périmés', value: props.alerts.expired ?? 0, tone: 'red', href: '/pharmacy/tableau-de-bord' },
    { label: 'Entretiens en retard', value: props.alerts.maintenance_overdue ?? 0, tone: 'red', href: '/parc' },
    { label: 'Désinfections à prévoir', value: props.alerts.disinfection_soon ?? 0, tone: 'orange', href: '/parc' },
    { label: 'Entretiens à prévoir', value: props.alerts.maintenance_soon ?? 0, tone: 'orange', href: '/parc' },
    { label: 'Péremption < 30 j', value: props.alerts.expiring_soon ?? 0, tone: 'orange', href: '/pharmacy/tableau-de-bord' },
    { label: 'Stock bas', value: props.alerts.low_stock ?? 0, tone: 'orange', href: '/pharmacy/tableau-de-bord' },
    { label: 'Documents expirés', value: props.alerts.documents_expired ?? 0, tone: 'red', href: '/parc' },
    { label: 'Documents à renouveler', value: props.alerts.documents_soon ?? 0, tone: 'orange', href: '/parc' },
    { label: 'Événements ouverts', value: props.alerts.open_events ?? 0, tone: 'yellow', href: '/events' },
]);

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
            <Link href="/parc" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:brightness-[0.98]">
                <p class="text-xs uppercase tracking-wide text-gray-500">Véhicules</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: tenant?.profile?.theme }">{{ stats.vehicles }}</p>
                <p class="text-xs text-gray-500">{{ stats.vehicles_available }} disponible(s) · voir le parc ›</p>
            </Link>
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

        <!-- Alertes (synthèse multi-domaines) -->
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
    </AppLayout>
</template>
