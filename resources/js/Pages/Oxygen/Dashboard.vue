<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SubTabs from '@/Components/SubTabs.vue';

const props = defineProps({
    kpis: { type: Object, default: () => ({ vehicles_equipped: 0, bottles: 0, low: 0, to_replace: 0, out_of_service: 0, avg_fill: null }) },
    bands: { type: Array, default: () => [] },
    attention: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    can_manage: { type: Boolean, default: false },
});

// Pourcentage en direct dans une modale (valeur ↔ plage min/max).
function livePercent(b, pressure) {
    if (!b || pressure === '' || pressure == null || isNaN(pressure)) return null;
    if (b.pressure_max === b.pressure_min) return null;
    return Math.round(Math.max(0, Math.min(100, (Number(pressure) - b.pressure_min) / (b.pressure_max - b.pressure_min) * 100)));
}

// Ajuster le niveau.
const adjusting = ref(null);
const readingForm = useForm({ pressure: null, note: '' });
function openReading(b) { adjusting.value = b; readingForm.clearErrors(); readingForm.pressure = b.pressure ?? b.pressure_max; readingForm.note = ''; }
const readingPercent = computed(() => livePercent(adjusting.value, readingForm.pressure));
function submitReading() {
    readingForm.post(`/vehicles/${adjusting.value.vehicle_id}/oxygen-bottles/${adjusting.value.id}/releve`, {
        preserveScroll: true, onSuccess: () => { adjusting.value = null; },
    });
}

// Changer la bouteille.
const replacing = ref(null);
const replaceForm = useForm({ serial_number: '', pressure: null, note: '' });
function openReplace(b) { replacing.value = b; replaceForm.clearErrors(); replaceForm.serial_number = ''; replaceForm.pressure = b.pressure_max; replaceForm.note = ''; }
const replacePercent = computed(() => livePercent(replacing.value, replaceForm.pressure));
function submitReplace() {
    replaceForm.post(`/vehicles/${replacing.value.vehicle_id}/oxygen-bottles/${replacing.value.id}/remplacer`, {
        preserveScroll: true, onSuccess: () => { replacing.value = null; },
    });
}
function setStatus(b, status) {
    router.post(`/vehicles/${b.vehicle_id}/oxygen-bottles/${b.id}/statut`, { status }, { preserveScroll: true });
}

const section = ref('attention');
const sections = computed(() => [
    { key: 'attention', label: 'À surveiller', badge: props.attention.length || null },
    { key: 'fill', label: 'Répartition des niveaux' },
    { key: 'recent', label: 'Activité récente' },
]);

// Palette de statut (data-viz) — fixe, jamais thématisée.
const TONES = { critical: '#d03b3b', serious: '#ec835a', warning: '#fab219', series: '#2a78d6' };

// Couleur d'un pourcentage de remplissage (bas = rouge).
function fillColor(p) {
    if (p == null) return '#9ca3af';
    if (p < 25) return TONES.critical;
    if (p < 50) return TONES.warning;
    return '#15a34a';
}
const statusChip = {
    en_service: { cls: 'bg-green-100 text-green-700', label: 'En service' },
    a_remplacer: { cls: 'bg-red-100 text-red-700', label: 'À remplacer' },
    hors_service: { cls: 'bg-gray-200 text-gray-600', label: 'Hors service' },
};

// Barres tracées à l'échelle (hauteur max 150px).
const maxBand = computed(() => Math.max(1, ...props.bands.map((b) => b.count)));
const barHeight = (count) => Math.round((count / maxBand.value) * 150);
</script>

<template>
    <AppLayout>
        <Head title="Oxygène — tableau de bord" />
        <template #title>Oxygène</template>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-500">Dotation, niveaux et changements de bouteilles · vue flotte</p>
            <Link href="/oxygene/types-bouteilles" class="text-xs font-semibold text-[var(--brand)] hover:underline">Types de bouteilles (B2, B5…) ›</Link>
        </div>

        <!-- KPI -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Véhicules équipés</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ kpis.vehicles_equipped }}</p>
                <p class="text-xs text-gray-500">{{ kpis.bottles }} bouteille(s) au total</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Remplissage moyen</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.avg_fill != null ? fillColor(kpis.avg_fill) : undefined }">{{ kpis.avg_fill != null ? kpis.avg_fill + ' %' : '—' }}</p>
                <p class="text-xs text-gray-500">bouteilles en service</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Niveau bas &lt; 25 %</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.low > 0 ? TONES.critical : undefined }">{{ kpis.low }}</p>
                <p class="text-xs text-gray-500">à recharger / changer</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">À remplacer</p>
                <p class="mt-1 text-3xl font-bold" :style="{ color: kpis.to_replace > 0 ? TONES.critical : undefined }">{{ kpis.to_replace }}</p>
                <p class="text-xs text-gray-500">signalées par l'équipage</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hors service</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ kpis.out_of_service }}</p>
                <p class="text-xs text-gray-500">retirées du service</p>
            </div>
        </div>

        <SubTabs v-model="section" :tabs="sections" class="mt-5" />

        <!-- À surveiller -->
        <div v-show="section === 'attention'" class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-bold text-gray-900">Bouteilles à surveiller</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Véhicule</th>
                            <th class="px-2 py-2">Bouteille</th>
                            <th class="px-2 py-2">N° série</th>
                            <th class="px-2 py-2">Niveau</th>
                            <th class="px-2 py-2">État</th>
                            <th class="px-2 py-2">Dernier relevé</th>
                            <th v-if="can_manage" class="px-2 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="b in attention" :key="b.id">
                            <td class="px-2 py-2.5">
                                <Link :href="`/vehicles/${b.vehicle_id}#oxygen`" class="font-semibold text-gray-900 hover:underline">{{ b.vehicle ?? '—' }}</Link>
                            </td>
                            <td class="px-2 py-2.5 text-gray-700">{{ b.label }}</td>
                            <td class="px-2 py-2.5 text-xs text-gray-500">{{ b.serial_number ?? '—' }}</td>
                            <td class="px-2 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full" :style="{ width: (b.percent ?? 0) + '%', background: fillColor(b.percent) }"></div>
                                    </div>
                                    <span class="tabular-nums text-xs font-bold" :style="{ color: fillColor(b.percent) }">{{ b.percent ?? '—' }}%</span>
                                    <span class="text-xs text-gray-400">{{ b.pressure != null ? b.pressure + ' bar' : '' }}</span>
                                </div>
                            </td>
                            <td class="px-2 py-2.5">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="(statusChip[b.status] || statusChip.en_service).cls">{{ b.status_label }}</span>
                            </td>
                            <td class="px-2 py-2.5 tabular-nums text-xs text-gray-500">{{ b.last_checked_at ?? '—' }}</td>
                            <td v-if="can_manage" class="px-2 py-2.5">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <button type="button" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="openReading(b)">Ajuster</button>
                                    <button type="button" class="rounded-lg bg-[var(--brand)] px-2.5 py-1 text-xs font-semibold text-white hover:brightness-110" @click="openReplace(b)">Changer</button>
                                    <button v-if="b.status !== 'a_remplacer'" type="button" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50" @click="setStatus(b, 'a_remplacer')">À remplacer</button>
                                    <button v-else type="button" class="rounded-lg border border-green-300 px-2.5 py-1 text-xs font-semibold text-green-700 hover:bg-green-50" @click="setStatus(b, 'en_service')">En service</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="attention.length === 0"><td :colspan="can_manage ? 7 : 6" class="py-6 text-center text-gray-500">Toutes les bouteilles sont au-dessus de 50 % et en service.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Répartition des niveaux -->
        <div v-show="section === 'fill'" class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-gray-900">Répartition des niveaux</h2>
            <div class="mt-5 flex items-end gap-4 border-b border-gray-200 pb-0" style="height: 180px">
                <div v-for="b in bands" :key="b.label" class="flex flex-1 flex-col items-center justify-end" style="height: 100%">
                    <span class="mb-1 text-xs font-bold text-gray-600">{{ b.count }}</span>
                    <div class="w-10 rounded-t" :style="{ height: barHeight(b.count) + 'px', background: TONES[b.tone] }"></div>
                </div>
            </div>
            <div class="flex gap-4 pt-2">
                <span v-for="b in bands" :key="b.label" class="flex-1 text-center text-xs font-medium text-gray-500">{{ b.label }}</span>
            </div>
            <p class="mt-2 text-xs text-gray-400">Bouteilles en service, regroupées par pourcentage de remplissage. Rouge = presque vide.</p>
        </div>

        <!-- Activité récente -->
        <div v-show="section === 'recent'" class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-bold text-gray-900">Relevés et changements récents</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Date</th>
                            <th class="px-2 py-2">Véhicule</th>
                            <th class="px-2 py-2">Bouteille</th>
                            <th class="px-2 py-2">Action</th>
                            <th class="px-2 py-2">Détail</th>
                            <th class="px-2 py-2">Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(l, i) in recent" :key="i">
                            <td class="px-2 py-2.5 tabular-nums text-gray-600">{{ l.at }}</td>
                            <td class="px-2 py-2.5 text-gray-700">{{ l.vehicle ?? '—' }}</td>
                            <td class="px-2 py-2.5 text-gray-700">{{ l.bottle ?? '—' }}</td>
                            <td class="px-2 py-2.5 font-medium text-gray-900">{{ l.action_label }}</td>
                            <td class="px-2 py-2.5 text-xs text-gray-600">
                                <template v-if="l.action === 'releve' && l.percent != null">{{ l.pressure }} bar ({{ l.percent }} %)</template>
                                <template v-else-if="l.action === 'remplacement'">{{ l.previous_serial || '—' }} → {{ l.serial_number || '—' }}</template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-2 py-2.5 text-gray-700">{{ l.by ?? '—' }}</td>
                        </tr>
                        <tr v-if="recent.length === 0"><td colspan="6" class="py-6 text-center text-gray-500">Aucune activité récente.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modale : ajuster le niveau -->
        <div v-if="adjusting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="adjusting = null">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Ajuster le niveau</h3>
                <p class="mt-0.5 text-xs text-gray-500">{{ adjusting.vehicle }} · {{ adjusting.label }} (plage {{ adjusting.pressure_min }}–{{ adjusting.pressure_max }} bar).</p>

                <label class="mt-4 block text-xs font-medium text-gray-600">Pression (bar)</label>
                <input v-model.number="readingForm.pressure" type="number" :min="0" :max="adjusting.pressure_max" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-lg font-semibold focus:bg-white" />
                <p v-if="readingForm.errors.pressure" class="mt-1 text-xs text-red-600">{{ readingForm.errors.pressure }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full transition-all" :style="{ width: (readingPercent ?? 0) + '%', background: fillColor(readingPercent) }"></div>
                    </div>
                    <span class="w-10 text-right text-sm font-bold" :style="{ color: fillColor(readingPercent) }">{{ readingPercent ?? '—' }}%</span>
                </div>
                <label class="mt-3 block text-xs font-medium text-gray-600">Note (optionnel)</label>
                <input v-model="readingForm.note" type="text" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white" />

                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium" @click="adjusting = null">Annuler</button>
                    <button type="button" :disabled="readingForm.processing || readingForm.pressure === '' || readingForm.pressure == null" class="flex-1 rounded-xl bg-[var(--brand)] py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submitReading">Enregistrer</button>
                </div>
            </div>
        </div>

        <!-- Modale : changer la bouteille -->
        <div v-if="replacing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="replacing = null">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Changer la bouteille</h3>
                <p class="mt-0.5 text-xs text-gray-500">
                    {{ replacing.vehicle }} · {{ replacing.label }}<template v-if="replacing.serial_number"> · sortie n° {{ replacing.serial_number }}</template>. Remise à plein par défaut.
                </p>

                <label class="mt-4 block text-xs font-medium text-gray-600">N° de série de la nouvelle bouteille</label>
                <input v-model="replaceForm.serial_number" type="text" placeholder="ex. AB-12345" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm uppercase focus:bg-white" @input="replaceForm.serial_number = replaceForm.serial_number.toUpperCase()" />
                <p v-if="replaceForm.errors.serial_number" class="mt-1 text-xs text-red-600">{{ replaceForm.errors.serial_number }}</p>

                <label class="mt-3 block text-xs font-medium text-gray-600">Pression de la bouteille neuve (bar)</label>
                <input v-model.number="replaceForm.pressure" type="number" :min="0" :max="replacing.pressure_max" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                <div class="mt-2 flex items-center gap-2">
                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full transition-all" :style="{ width: (replacePercent ?? 0) + '%', background: fillColor(replacePercent) }"></div>
                    </div>
                    <span class="w-10 text-right text-sm font-bold" :style="{ color: fillColor(replacePercent) }">{{ replacePercent ?? '—' }}%</span>
                </div>
                <label class="mt-3 block text-xs font-medium text-gray-600">Note (optionnel)</label>
                <input v-model="replaceForm.note" type="text" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white" />

                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium" @click="replacing = null">Annuler</button>
                    <button type="button" :disabled="replaceForm.processing" class="flex-1 rounded-xl bg-[var(--brand)] py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submitReplace">Enregistrer le changement</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
