<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    kind: { type: String, required: true },
    counts: { type: Object, default: () => ({}) },
    records: { type: Array, default: () => [] },
    candidates: { type: Array, default: () => [] },
    segments: { type: Object, default: () => ({}) },
});

const kindLabels = { agent: 'Agents', vehicle: 'Véhicules', bag: 'Sacs', oxygen_bottle: 'Bouteilles O2' };

function link(record, value) {
    router.patch(`/settings/intra-chatel/${record.id}`, { linked_id: value === '' ? null : Number(value) }, { preserveScroll: true });
}
function fmt(iso) {
    return iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <AppLayout>
        <Head title="Référentiels intra-Chatel" />
        <template #title>Référentiels intra-Chatel</template>

        <div class="max-w-4xl space-y-4">
            <p class="text-sm text-gray-600">
                Fiches publiées par intra-Chatel, qui fait référence. Elles sont en lecture seule : seule leur association à une fiche Vulkain se règle ici.
                Une correspondance exacte (initiales = identifiant interne, indicatif ou immatriculation, nom du sac, n° de série) est associée automatiquement.
            </p>

            <nav class="flex flex-wrap gap-2">
                <Link
                    v-for="(label, k) in kindLabels"
                    :key="k"
                    :href="`/settings/intra-chatel?kind=${k}`"
                    preserve-scroll
                    class="rounded-full border px-3 py-1.5 text-sm font-medium"
                    :class="k === kind ? 'border-[var(--brand)] bg-[var(--brand)] text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                >
                    {{ label }} · {{ counts[k]?.total ?? 0 }}
                    <span v-if="counts[k]?.unlinked" class="ml-1 rounded-full bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800">{{ counts[k].unlinked }} à associer</span>
                </Link>
            </nav>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table v-if="records.length" class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">intra-Chatel</th>
                            <th class="px-4 py-3">Fiche Vulkain associée</th>
                            <th class="px-4 py-3">Publiée</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in records" :key="r.id" :class="r.present ? '' : 'bg-gray-50 text-gray-400'">
                            <td class="px-4 py-3">
                                <span class="font-medium" :class="r.present ? 'text-gray-900' : ''">{{ r.label }}</span>
                                <span class="ml-1 font-mono text-[11px] text-gray-400">#{{ r.external_id }}</span>
                                <span v-if="!r.present" class="ml-1.5 rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">absente</span>
                                <span v-else-if="!r.active" class="ml-1.5 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">inactive</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <select
                                        :value="r.linked_id ?? ''"
                                        class="block w-full max-w-xs rounded-lg border-gray-300 py-1.5 text-sm"
                                        :class="r.linked_id ? '' : 'border-amber-300 bg-amber-50'"
                                        @change="link(r, $event.target.value)"
                                    >
                                        <option value="">— Non associée —</option>
                                        <option v-for="c in candidates" :key="c.id" :value="c.id">{{ c.label }}</option>
                                    </select>
                                    <span v-if="r.linked_id" class="text-[11px] text-gray-500">{{ r.link_mode === 'auto' ? 'auto' : 'manuelle' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ fmt(r.synced_at) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="p-6 text-center text-sm text-gray-500">
                    Rien de publié pour l'instant. intra-Chatel envoie ses {{ kindLabels[kind].toLowerCase() }} avec
                    <code class="rounded bg-gray-100 px-1 font-mono text-xs">PUT /api/v1/intra-chatel/{{ segments[kind] }}</code>.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
