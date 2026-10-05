<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SubTabs from '@/Components/SubTabs.vue';

const props = defineProps({
    kpis: { type: Object, default: () => ({}) },
    presence: { type: Object, default: () => ({}) },
    pending: { type: Array, default: () => [] },
    balances: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    period_label: { type: String, default: '' },
    reliquat_deadline: { type: String, default: '' },
});

const section = ref('presence');
const sections = computed(() => [
    { key: 'presence', label: 'Présence & demandes', badge: props.kpis.pending || null },
    { key: 'balances', label: 'Soldes par période' },
]);

const TONES = { good: '#0ca30c', s1: '#2a78d6', crit: '#d03b3b', warn: '#fab219' };

// Donut présence (présents / en congé), tracé à l'échelle (r=56).
const CIRC = 2 * Math.PI * 56;
const presenceArcs = computed(() => {
    const total = (props.presence.present ?? 0) + (props.presence.on_leave ?? 0);
    const segs = [
        { key: 'present', label: 'Présents', value: props.presence.present ?? 0, color: TONES.good },
        { key: 'on_leave', label: 'En congé', value: props.presence.on_leave ?? 0, color: TONES.s1 },
    ];
    let offset = 0;
    return segs.map((s) => {
        const len = total > 0 ? (s.value / total) * CIRC : 0;
        const arc = { ...s, dash: `${len} ${CIRC - len}`, offset: -offset };
        offset += len;
        return arc;
    });
});
const presenceTotal = computed(() => (props.presence.present ?? 0) + (props.presence.on_leave ?? 0));

const typeChip = {
    conge_paye: 'bg-blue-100 text-blue-700',
    rtt: 'bg-teal-100 text-teal-700',
    maladie: 'bg-red-100 text-red-700',
    formation: 'bg-purple-100 text-purple-700',
};

// Pastille de solde N-1 : orange si reliquat, rouge si élevé (risque de perte).
function n1Cls(v) {
    if (v <= 0) return 'text-gray-300';
    if (v >= 5) return 'rounded-lg bg-red-100 px-2 py-0.5 text-red-800';
    return 'rounded-lg bg-orange-100 px-2 py-0.5 text-orange-700';
}
</script>

<template>
    <AppLayout>
        <Head title="Tableau de bord — RH / Congés" />
        <template #title>RH / Congés</template>

        <!-- Onglets -->
        <div class="mb-6 flex gap-1 border-b border-gray-200">
            <Link href="/leave/tableau-de-bord" class="border-b-2 border-[var(--brand)] px-4 py-2 text-sm font-semibold text-gray-900">Tableau de bord</Link>
            <Link href="/leave" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-800">Demandes & calendrier</Link>
        </div>

        <!-- KPI -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Effectif actif</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ kpis.active ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">agents</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">En congé aujourd'hui</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: TONES.s1 }">{{ kpis.on_leave ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">absences validées</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Demandes en attente</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.pending > 0 ? TONES.warn : '#1B2430' }">{{ kpis.pending ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">à valider</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reliquat N-1 à solder</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.reliquat_n1 > 0 ? TONES.crit : '#1B2430' }">{{ kpis.reliquat_n1 ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">jours · échéance {{ reliquat_deadline }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Solde dispo moyen</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ kpis.avg_available ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">N-1 + N / agent</p>
            </div>
        </div>

        <SubTabs v-model="section" :tabs="sections" class="mt-5" />

        <div v-show="section === 'presence'" class="mt-4 grid gap-4 lg:grid-cols-[360px_1fr]">
            <!-- Présence -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900">Présence aujourd'hui</h2>
                <div class="mt-4 flex items-center gap-5">
                    <svg width="150" height="150" viewBox="0 0 150 150" class="shrink-0">
                        <circle cx="75" cy="75" r="56" fill="none" stroke="#eef0f3" stroke-width="20" />
                        <g transform="rotate(-90 75 75)">
                            <circle v-for="s in presenceArcs" :key="s.key" cx="75" cy="75" r="56" fill="none" stroke-width="20"
                                :stroke="s.color" :stroke-dasharray="s.dash" :stroke-dashoffset="s.offset" />
                        </g>
                        <text x="75" y="70" text-anchor="middle" font-size="26" font-weight="800" fill="#1B2430">{{ presenceTotal }}</text>
                        <text x="75" y="90" text-anchor="middle" font-size="11" fill="#6B7280">agents</text>
                    </svg>
                    <div class="flex flex-col gap-2.5 text-sm">
                        <div v-for="s in presenceArcs" :key="s.key" class="flex items-center gap-2.5">
                            <span class="h-3 w-3 rounded" :style="{ background: s.color }"></span>
                            <span class="text-gray-700">{{ s.label }}</span>
                            <b class="ml-auto tabular-nums text-gray-900">{{ s.value }}</b>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demandes en attente -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900">Demandes en attente de validation</h2>
                    <Link href="/leave" class="text-xs font-semibold text-[var(--brand)] hover:underline">Toutes ›</Link>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2">Agent</th>
                            <th class="py-2">Type</th>
                            <th class="py-2">Période</th>
                            <th class="py-2">Jours</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in pending" :key="p.id">
                            <td class="py-2.5 font-semibold text-gray-900">{{ p.user }}</td>
                            <td class="py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="typeChip[p.type_key] || 'bg-gray-100 text-gray-600'">{{ p.type }}</span></td>
                            <td class="py-2.5 text-gray-700">{{ p.start }}<template v-if="p.end"> → {{ p.end }}</template></td>
                            <td class="py-2.5 font-bold tabular-nums">{{ p.days }}</td>
                        </tr>
                        <tr v-if="pending.length === 0"><td colspan="4" class="py-6 text-center text-gray-500">Aucune demande en attente.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Soldes par période de référence -->
        <div v-show="section === 'balances'" class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900">Soldes de congés par période de référence</h2>
                <span class="text-xs text-gray-500">Période {{ period_label }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Agent</th>
                            <th class="px-3 py-2 text-center">N-1 <span class="block text-[10px] font-normal normal-case text-gray-400">reliquat à solder</span></th>
                            <th class="px-3 py-2 text-center">N <span class="block text-[10px] font-normal normal-case text-gray-400">année en cours</span></th>
                            <th class="px-3 py-2 text-center">N+1 <span class="block text-[10px] font-normal normal-case text-gray-400">en acquisition</span></th>
                            <th class="px-3 py-2 text-center">Disponible <span class="block text-[10px] font-normal normal-case text-gray-400">N-1 + N</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="b in balances" :key="b.id">
                            <td class="px-3 py-2.5 font-semibold text-gray-900">{{ b.name }}</td>
                            <td class="px-3 py-2.5 text-center font-bold tabular-nums"><span :class="n1Cls(b.n_minus_1)">{{ b.n_minus_1 }}</span></td>
                            <td class="px-3 py-2.5 text-center"><span class="rounded-lg bg-blue-50 px-2 py-0.5 font-bold tabular-nums text-blue-700">{{ b.n }}</span></td>
                            <td class="px-3 py-2.5 text-center"><span class="rounded-lg bg-gray-100 px-2 py-0.5 font-bold tabular-nums text-gray-500">{{ b.n_plus_1 }}</span></td>
                            <td class="px-3 py-2.5 text-center font-extrabold tabular-nums text-gray-900">{{ b.available }} j</td>
                        </tr>
                        <tr v-if="balances.length === 0"><td colspan="5" class="py-6 text-center text-gray-500">Aucun agent actif.</td></tr>
                    </tbody>
                    <tfoot v-if="balances.length > 0">
                        <tr class="border-t-2 border-gray-200 font-extrabold">
                            <td class="px-3 pt-3">Total équipe</td>
                            <td class="px-3 pt-3 text-center tabular-nums" :style="{ color: totals.n_minus_1 > 0 ? TONES.crit : undefined }">{{ totals.n_minus_1 }} j</td>
                            <td class="px-3 pt-3 text-center tabular-nums">{{ totals.n }} j</td>
                            <td class="px-3 pt-3 text-center tabular-nums">{{ totals.n_plus_1 }} j</td>
                            <td class="px-3 pt-3 text-center tabular-nums">{{ totals.available }} j</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                <b class="text-gray-800">N-1</b> = reliquat de la période précédente, à poser avant l'échéance (orange, rouge si ≥ 5 j) ·
                <b class="text-gray-800">N</b> = droits de l'année en cours ·
                <b class="text-gray-800">N+1</b> = en cours d'acquisition (pas encore posables). Calcul congés payés (2,5 j ouvrables / mois, au prorata de présence).
            </p>
        </div>
    </AppLayout>
</template>
