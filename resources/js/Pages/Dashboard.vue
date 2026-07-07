<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <AppLayout>
        <Head title="Tableau de bord" />
        <template #title>Tableau de bord</template>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Bienvenue, {{ user?.name }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                Vous êtes authentifié dans l’espace de votre organisation. Les cartes de suivi
                (véhicules, inventaires, anomalies, stock pharmacie…) arriveront avec les modules métier.
            </p>
        </div>
    </AppLayout>
</template>
