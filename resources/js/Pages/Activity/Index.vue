<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HistoryList from '@/Components/HistoryList.vue';

defineProps({
    logs: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <Head title="Historique" />
        <template #title>Historique des actions</template>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="mb-4 text-sm text-gray-500">Journal des actions sur les véhicules, emplacements, matériels, exemplaires et lots.</p>
            <HistoryList :logs="logs" />
        </div>
    </AppLayout>
</template>
