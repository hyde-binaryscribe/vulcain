<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    organisations: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, active: 0 }) },
});

function toggle(org) {
    router.post(`/platform/organisations/${org.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <PlatformLayout>
        <Head title="Desk — Organisations" />

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Organisations</h1>
                <p class="text-sm text-slate-500">{{ stats.total }} organisation(s) · {{ stats.active }} active(s)</p>
            </div>
            <Link
                href="/platform/organisations/create"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
            >
                + Nouvelle organisation
            </Link>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Sous-domaine</th>
                        <th class="px-4 py-3">Secteur</th>
                        <th class="px-4 py-3">Utilisateurs</th>
                        <th class="px-4 py-3">Abonnement</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="org in organisations" :key="org.id">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ org.name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ org.slug }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: org.theme }"></span>
                                {{ org.sector_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ org.users_count }}</td>
                        <td class="px-4 py-3">
                            <Link :href="`/platform/organisations/${org.id}/subscription`" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-indigo-600 hover:underline">
                                <span class="font-medium">{{ org.plan_label ?? '—' }}</span>
                                <span v-if="org.subscription_status_label" class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500">{{ org.subscription_status_label }}</span>
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="org.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ org.status === 'active' ? 'Active' : 'Suspendue' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Link :href="`/platform/organisations/${org.id}/subscription`" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Abonnement</Link>
                            <button
                                type="button"
                                class="ml-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                @click="toggle(org)"
                            >
                                {{ org.status === 'active' ? 'Suspendre' : 'Réactiver' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="organisations.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">Aucune organisation pour le moment.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PlatformLayout>
</template>
