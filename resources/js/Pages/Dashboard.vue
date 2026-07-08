<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({ vehicles: 0, vehicles_available: 0, users: 0 }) },
    alerts: { type: Object, default: () => ({ expired: 0, expiring_soon: 0, low_stock: 0, open_events: 0 }) },
});

const alertCards = computed(() => [
    { label: 'Périmés', value: props.alerts.expired, tone: 'red', href: '/pharmacy' },
    { label: 'Péremption < 30 j', value: props.alerts.expiring_soon, tone: 'amber', href: '/pharmacy' },
    { label: 'Stock bas', value: props.alerts.low_stock, tone: 'amber', href: '/pharmacy' },
    { label: 'Événements ouverts', value: props.alerts.open_events, tone: 'blue', href: '/events' },
]);

const toneStyles = {
    red: 'border-red-200 bg-red-50 text-red-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
};

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

        <!-- Alertes -->
        <h2 class="mt-8 mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Alertes</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="a in alertCards"
                :key="a.label"
                :href="a.href"
                class="rounded-2xl border p-5 shadow-sm transition hover:brightness-[0.98]"
                :class="a.value > 0 ? toneStyles[a.tone] : 'border-gray-200 bg-white text-gray-400'"
            >
                <p class="text-3xl font-bold">{{ a.value }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide">{{ a.label }}</p>
            </Link>
        </div>
    </AppLayout>
</template>
