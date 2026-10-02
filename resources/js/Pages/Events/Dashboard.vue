<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    kpis: { type: Object, default: () => ({}) },
    statusChart: { type: Array, default: () => [] },
    priority: { type: Object, default: () => ({}) },
    typeChart: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
});

const TONES = { critical: '#d03b3b', serious: '#ec835a', warning: '#fab219', s1: '#2a78d6', s2: '#eb6834', s3: '#1baf7a' };

// Donut statut, tracé à l'échelle (r=56, circonférence ≈ 351.86).
const CIRC = 2 * Math.PI * 56;
const statusTotal = computed(() => props.statusChart.reduce((s, x) => s + x.count, 0));
const statusArcs = computed(() => {
    let offset = 0;
    return props.statusChart.map((s) => {
        const len = statusTotal.value > 0 ? (s.count / statusTotal.value) * CIRC : 0;
        const arc = { ...s, dash: `${len} ${CIRC - len}`, offset: -offset };
        offset += len;
        return arc;
    });
});

const maxType = computed(() => Math.max(1, ...props.typeChart.map((t) => t.count)));
const typeColors = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];

const priorityCards = computed(() => [
    { label: 'Priorité haute', value: props.priority.haute ?? 0, cls: 'border-red-200 bg-red-50 text-red-800' },
    { label: 'Priorité normale', value: props.priority.normale ?? 0, cls: 'border-orange-200 bg-orange-50 text-orange-700' },
    { label: 'Priorité basse', value: props.priority.basse ?? 0, cls: 'border-yellow-200 bg-yellow-50 text-yellow-800' },
]);

const prioChip = {
    haute: 'bg-red-100 text-red-800',
    normale: 'bg-orange-100 text-orange-700',
    basse: 'bg-gray-100 text-gray-600',
};
const statusChip = {
    a_traiter: 'bg-orange-100 text-orange-700',
    en_cours: 'bg-blue-100 text-blue-700',
    resolu: 'bg-green-100 text-green-700',
    ferme: 'bg-gray-100 text-gray-600',
};
</script>

<template>
    <AppLayout>
        <Head title="Tableau de bord — Événements" />
        <template #title>Événements</template>

        <!-- Onglets -->
        <div class="mb-6 flex gap-1 border-b border-gray-200">
            <Link href="/events/tableau-de-bord" class="border-b-2 border-[var(--brand)] px-4 py-2 text-sm font-semibold text-gray-900">Tableau de bord</Link>
            <Link href="/events" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-800">Kanban</Link>
        </div>

        <!-- KPI -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">À traiter</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.a_traiter > 0 ? TONES.serious : '#1B2430' }">{{ kpis.a_traiter ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">non pris en charge</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">En cours</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: TONES.s1 }">{{ kpis.en_cours ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">en traitement</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Haute priorité</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.high_priority > 0 ? TONES.critical : '#1B2430' }">{{ kpis.high_priority ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">à arbitrer</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Résolus (30 j)</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: '#0ca30c' }">{{ kpis.resolved_30d ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">clôturés</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Délai moyen</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ kpis.avg_days ?? '—' }}</p>
                <p class="mt-1 text-xs text-gray-500">jours / résolution</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[360px_1fr]">
            <!-- Répartition par statut -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900">Répartition par statut</h2>
                <div class="mt-4 flex items-center gap-5">
                    <svg width="150" height="150" viewBox="0 0 150 150" class="shrink-0">
                        <circle cx="75" cy="75" r="56" fill="none" stroke="#eef0f3" stroke-width="20" />
                        <g transform="rotate(-90 75 75)">
                            <circle v-for="s in statusArcs" :key="s.key" cx="75" cy="75" r="56" fill="none" stroke-width="20"
                                :stroke="s.color" :stroke-dasharray="s.dash" :stroke-dashoffset="s.offset" />
                        </g>
                        <text x="75" y="70" text-anchor="middle" font-size="26" font-weight="800" fill="#1B2430">{{ statusTotal }}</text>
                        <text x="75" y="90" text-anchor="middle" font-size="11" fill="#6B7280">événements</text>
                    </svg>
                    <div class="flex flex-col gap-2.5 text-sm">
                        <div v-for="s in statusChart" :key="s.key" class="flex items-center gap-2.5">
                            <span class="h-3 w-3 rounded" :style="{ background: s.color }"></span>
                            <span class="text-gray-700">{{ s.label }}</span>
                            <b class="ml-auto tabular-nums text-gray-900">{{ s.count }}</b>
                        </div>
                    </div>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-3">
                    <div v-for="p in priorityCards" :key="p.label" class="rounded-xl border p-3" :class="p.cls">
                        <p class="text-2xl font-bold leading-none">{{ p.value }}</p>
                        <p class="mt-1 text-[11px] font-semibold">{{ p.label }}</p>
                    </div>
                </div>
            </div>

            <!-- Derniers événements -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900">Derniers événements</h2>
                    <Link href="/events" class="text-xs font-semibold text-[var(--brand)] hover:underline">Ouvrir le Kanban ›</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="px-2 py-2">Événement</th>
                                <th class="px-2 py-2">Véhicule</th>
                                <th class="px-2 py-2">Priorité</th>
                                <th class="px-2 py-2">Statut</th>
                                <th class="px-2 py-2">Signalé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="e in recent" :key="e.id">
                                <td class="px-2 py-2.5">
                                    <div class="font-semibold text-gray-900">{{ e.title }}</div>
                                    <div class="text-xs text-gray-400">{{ e.type }}</div>
                                </td>
                                <td class="px-2 py-2.5 text-gray-700">{{ e.vehicle ?? '—' }}</td>
                                <td class="px-2 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="prioChip[e.priority] || 'bg-gray-100 text-gray-600'">{{ e.priority }}</span></td>
                                <td class="px-2 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusChip[e.status] || 'bg-gray-100 text-gray-600'">{{ e.status_label }}</span></td>
                                <td class="px-2 py-2.5 text-xs text-gray-500">{{ e.created_at }}<template v-if="e.author"> · {{ e.author }}</template></td>
                            </tr>
                            <tr v-if="recent.length === 0"><td colspan="5" class="py-6 text-center text-gray-500">Aucun événement.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Événements par type -->
        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-gray-900">Événements par type (30 derniers jours)</h2>
            <div class="mt-5 flex items-end gap-10 border-b border-gray-200 px-4" style="height: 170px">
                <div v-for="(t, i) in typeChart" :key="t.label" class="flex flex-1 flex-col items-center justify-end" style="height: 100%">
                    <span class="mb-1 text-xs font-bold text-gray-600">{{ t.count }}</span>
                    <div class="w-12 rounded-t" :style="{ height: Math.round((t.count / maxType) * 150) + 'px', background: typeColors[i % typeColors.length] }"></div>
                </div>
            </div>
            <div class="flex gap-10 px-4 pt-2">
                <span v-for="t in typeChart" :key="t.label" class="flex-1 text-center text-xs font-semibold text-gray-500">{{ t.label }}</span>
            </div>
            <p class="mt-3 text-xs text-gray-400">Les anomalies de carrosserie sont signalées par les vérificateurs ; leur traitement reste réservé à l'administrateur.</p>
        </div>
    </AppLayout>
</template>
