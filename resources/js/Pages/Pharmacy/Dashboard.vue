<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    kpis: { type: Object, default: () => ({ references: 0, low_stock: 0, expired: 0, expiring_soon: 0 }) },
    expiryChart: { type: Array, default: () => [] },
    restock: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    alertDays: { type: Number, default: 30 },
});

// Palette de statut (data-viz) — fixe, jamais thématisée.
const TONES = {
    critical: '#d03b3b',
    serious: '#ec835a',
    warning: '#fab219',
    series: '#2a78d6',
};
const TONE_CHIP = {
    critical: { cls: 'bg-red-100 text-red-800', label: 'Critique' },
    serious: { cls: 'bg-orange-100 text-orange-700', label: 'Bas' },
    warning: { cls: 'bg-yellow-100 text-yellow-800', label: 'À prévoir' },
};

// Barres tracées à l'échelle (hauteur max 150px).
const maxCount = computed(() => Math.max(1, ...props.expiryChart.map((b) => b.count)));
function barHeight(count) {
    return Math.round((count / maxCount.value) * 150);
}
</script>

<template>
    <AppLayout>
        <Head title="Pharmacie — tableau de bord" />
        <template #title>Pharmacie</template>

        <!-- Onglets -->
        <div class="mb-6 flex gap-1 border-b border-gray-200">
            <Link href="/pharmacy/tableau-de-bord" class="border-b-2 border-[var(--brand)] px-4 py-2 text-sm font-semibold text-gray-900">Tableau de bord</Link>
            <Link href="/pharmacy" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-800">Consommables</Link>
        </div>

        <p class="mb-4 text-sm text-gray-500">Gestion FEFO · péremptions · réapprovisionnement</p>

        <!-- KPI -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Références</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ kpis.references }}</p>
                <p class="text-xs text-gray-500">consommables suivis</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stock bas</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.low_stock > 0 ? TONES.warning : undefined }">{{ kpis.low_stock }}</p>
                <p class="text-xs text-gray-500">sous le seuil mini</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Périmés</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.expired > 0 ? TONES.critical : undefined }">{{ kpis.expired }}</p>
                <p class="text-xs text-gray-500">à retirer</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Péremption &lt; {{ alertDays }} j</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.expiring_soon > 0 ? TONES.serious : undefined }">{{ kpis.expiring_soon }}</p>
                <p class="text-xs text-gray-500">à utiliser / remplacer</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <!-- Péremptions à venir -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900">Péremptions à venir</h2>
                <div class="mt-5 flex items-end gap-4 border-b border-gray-200 pb-0" style="height: 180px">
                    <div v-for="b in expiryChart" :key="b.label" class="flex flex-1 flex-col items-center justify-end" style="height: 100%">
                        <span class="mb-1 text-xs font-bold text-gray-600">{{ b.count }}</span>
                        <div class="w-9 rounded-t" :style="{ height: barHeight(b.count) + 'px', background: TONES[b.tone] }"></div>
                    </div>
                </div>
                <div class="flex gap-4 pt-2">
                    <span v-for="b in expiryChart" :key="b.label" class="flex-1 text-center text-xs font-medium text-gray-500">{{ b.label }}</span>
                </div>
                <p class="mt-2 text-xs text-gray-400">Lots regroupés par échéance (FEFO). Rouge = périmé, orange = sous {{ alertDays }} jours.</p>
            </div>

            <!-- À réapprovisionner -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900">À réapprovisionner</h2>
                    <Link href="/pharmacy" class="text-xs font-semibold text-[var(--brand)] hover:underline">Tout voir ›</Link>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2">Consommable</th>
                            <th class="py-2">Emplacement</th>
                            <th class="py-2">Stock</th>
                            <th class="py-2">État</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in restock" :key="r.id">
                            <td class="py-2.5">
                                <Link :href="`/materials/${r.id}`" class="font-semibold text-gray-900 hover:underline">{{ r.name }}</Link>
                            </td>
                            <td class="py-2.5 text-gray-600">{{ r.location ?? '—' }}</td>
                            <td class="py-2.5"><span class="font-bold tabular-nums">{{ r.stock }}</span> <span class="text-xs text-gray-400">/ {{ r.minimum_qty }}</span></td>
                            <td class="py-2.5">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="TONE_CHIP[r.tone].cls">{{ TONE_CHIP[r.tone].label }}</span>
                            </td>
                        </tr>
                        <tr v-if="restock.length === 0"><td colspan="4" class="py-6 text-center text-gray-500">Tout est au-dessus du seuil mini.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sorties récentes -->
        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900">Sorties récentes (consommations véhicules)</h2>
                <Link href="/activity" class="text-xs font-semibold text-[var(--brand)] hover:underline">Journal complet ›</Link>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Date</th>
                            <th class="px-2 py-2">Consommable</th>
                            <th class="px-2 py-2">Qté</th>
                            <th class="px-2 py-2">Véhicule</th>
                            <th class="px-2 py-2">Agent</th>
                            <th class="px-2 py-2">N° série / lot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in recent" :key="c.id">
                            <td class="px-2 py-2.5 tabular-nums text-gray-600">{{ c.date }}</td>
                            <td class="px-2 py-2.5 font-medium text-gray-900">{{ c.material }}</td>
                            <td class="px-2 py-2.5 font-bold tabular-nums">{{ c.quantity }}</td>
                            <td class="px-2 py-2.5 text-gray-700">{{ c.vehicle }}</td>
                            <td class="px-2 py-2.5 text-gray-700">{{ c.user }}</td>
                            <td class="px-2 py-2.5 text-xs text-gray-500">{{ c.serial ?? '—' }}</td>
                        </tr>
                        <tr v-if="recent.length === 0"><td colspan="6" class="py-6 text-center text-gray-500">Aucune sortie enregistrée.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
