<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    organisations: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    viewer: { type: Object, default: () => ({}) },
});

const search = ref('');
const statusFilter = ref('all');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.organisations.filter((o) => {
        const matchQ = !q || o.name.toLowerCase().includes(q) || o.slug.toLowerCase().includes(q);
        const matchS = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && o.status === 'active')
            || (statusFilter.value === 'suspended' && o.status === 'suspended')
            || (statusFilter.value === o.subscription_status);
        return matchQ && matchS;
    });
});

// Répartition par plan (barres), triée par volume.
const planBars = computed(() => {
    const bp = props.stats.by_plan || {};
    const max = Math.max(1, ...Object.values(bp));
    const labels = { decouverte: 'Découverte', standard: 'Standard', pro: 'Pro' };
    return Object.entries(bp)
        .map(([k, n]) => ({ key: k, label: labels[k] || k, n, pct: Math.round((n / max) * 100) }))
        .sort((a, b) => b.n - a.n);
});

const eur = (n) => new Intl.NumberFormat('fr-FR').format(n || 0);

function toggle(org) {
    router.post(`/platform/organisations/${org.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <PlatformLayout>
        <Head title="Desk — Tableau de bord" />

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-xl font-extrabold text-slate-900">Tableau de bord</h1>
                <p class="text-sm text-slate-500">
                    Vue d'ensemble de la plateforme<span v-if="viewer.is_group_manager"> · groupe {{ viewer.group }}</span>
                </p>
            </div>
            <Link href="/platform/organisations/create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                + Nouvelle organisation
            </Link>
        </div>

        <!-- KPIs -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="font-display text-2xl font-extrabold text-slate-900" style="font-variant-numeric:tabular-nums">{{ stats.total }}</p>
                <p class="text-xs text-slate-500">Organisations</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="font-display text-2xl font-extrabold text-green-600" style="font-variant-numeric:tabular-nums">{{ stats.active }}</p>
                <p class="text-xs text-slate-500">Actives</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="font-display text-2xl font-extrabold text-sky-600" style="font-variant-numeric:tabular-nums">{{ stats.trial }}</p>
                <p class="text-xs text-slate-500">En essai</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="font-display text-2xl font-extrabold text-slate-900" style="font-variant-numeric:tabular-nums">{{ eur(stats.mrr) }} €</p>
                <p class="text-xs text-slate-500">MRR estimé</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="font-display text-2xl font-extrabold text-slate-900" style="font-variant-numeric:tabular-nums">{{ stats.signups_30d }}</p>
                <p class="text-xs text-slate-500">Inscriptions 30 j</p>
            </div>
            <div class="rounded-xl border p-4" :class="stats.past_due > 0 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white'">
                <p class="font-display text-2xl font-extrabold" :class="stats.past_due > 0 ? 'text-red-600' : 'text-slate-900'" style="font-variant-numeric:tabular-nums">{{ stats.past_due }}</p>
                <p class="text-xs text-slate-500">Impayés</p>
            </div>
        </div>

        <!-- Répartition par plan -->
        <div v-if="planBars.length" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Répartition par formule</p>
            <div class="space-y-2.5">
                <div v-for="b in planBars" :key="b.key" class="flex items-center gap-3">
                    <span class="w-24 text-sm text-slate-600">{{ b.label }}</span>
                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-indigo-500" :style="{ width: b.pct + '%' }"></div>
                    </div>
                    <span class="w-8 text-right text-sm font-medium text-slate-700" style="font-variant-numeric:tabular-nums">{{ b.n }}</span>
                </div>
            </div>
        </div>

        <!-- Recherche + filtre -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <input v-model="search" type="search" placeholder="Rechercher une organisation…"
                class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" style="min-width:200px" />
            <select v-model="statusFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="all">Tous les statuts</option>
                <option value="active">Actives</option>
                <option value="suspended">Suspendues</option>
                <option value="trial">En essai</option>
                <option value="past_due">Impayées</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Secteur</th>
                        <th class="px-4 py-3">Utilisateurs</th>
                        <th class="px-4 py-3">Abonnement</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="org in filtered" :key="org.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3">
                            <Link :href="`/platform/organisations/${org.id}`" class="font-medium text-slate-900 hover:text-indigo-600 hover:underline">{{ org.name }}</Link>
                            <div class="text-xs text-slate-400">{{ org.slug }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: org.theme }"></span>{{ org.sector_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600" style="font-variant-numeric:tabular-nums">{{ org.users_count }}</td>
                        <td class="px-4 py-3">
                            <Link :href="`/platform/organisations/${org.id}/subscription`" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-indigo-600 hover:underline">
                                <span class="font-medium">{{ org.plan_label ?? '—' }}</span>
                                <span v-if="org.subscription_status_label" class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500">{{ org.subscription_status_label }}</span>
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="org.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                                {{ org.status === 'active' ? 'Active' : 'Suspendue' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Link :href="`/platform/organisations/${org.id}`" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Ouvrir</Link>
                            <button type="button" class="ml-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50" @click="toggle(org)">
                                {{ org.status === 'active' ? 'Suspendre' : 'Réactiver' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="filtered.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Aucune organisation ne correspond.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PlatformLayout>
</template>
