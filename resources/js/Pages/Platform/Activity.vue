<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    logs: { type: Array, default: () => [] },
});
</script>

<template>
    <PlatformLayout>
        <Head title="Desk — Activité" />

        <div class="mb-4">
            <h1 class="font-display text-xl font-extrabold text-slate-900">Activité de la plateforme</h1>
            <p class="text-sm text-slate-500">100 dernières actions, toutes organisations confondues.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Quand</th>
                        <th class="px-4 py-3">Organisation</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Objet</th>
                        <th class="px-4 py-3">Par</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(l, i) in logs" :key="i">
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ l.at }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ l.org }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ l.action }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ l.subject_type }}<span v-if="l.description" class="text-slate-400"> — {{ l.description }}</span></td>
                        <td class="px-4 py-3 text-slate-500">{{ l.actor ?? '—' }}</td>
                    </tr>
                    <tr v-if="logs.length === 0"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Aucune activité.</td></tr>
                </tbody>
            </table>
        </div>
    </PlatformLayout>
</template>
