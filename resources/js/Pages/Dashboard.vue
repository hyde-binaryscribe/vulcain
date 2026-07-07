<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    stats: { type: Object, default: () => ({ vehicles: 0, vehicles_available: 0, users: 0 }) },
});

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
                <p class="text-xs uppercase tracking-wide text-gray-500">Inventaires</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ stats.inventories_total ?? 0 }}</p>
                <p class="text-xs text-gray-500">{{ stats.inventories_draft ?? 0 }} en cours</p>
            </div>
        </div>
    </AppLayout>
</template>
