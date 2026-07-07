<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import HistoryList from '@/Components/HistoryList.vue';

const props = defineProps({
    material: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    lots: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const statusStyles = {
    conforme: 'bg-green-100 text-green-800', manquant: 'bg-amber-100 text-amber-800',
    hs: 'bg-red-100 text-red-800', a_remplacer: 'bg-orange-100 text-orange-800',
    en_reparation: 'bg-blue-100 text-blue-800', indisponible: 'bg-gray-200 text-gray-700',
};

// Stock (mode quantité)
const stockForm = useForm({ current_qty: props.material.current_qty });
function saveStock() {
    stockForm.patch(`/materials/${props.material.id}/stock`, { preserveScroll: true });
}

// Unités (mode unitaire)
const itemForm = useForm({ serial_number: '', status: 'conforme', location_id: '', next_check_date: '', notes: '' });
function addItem() {
    itemForm.transform((d) => ({ ...d, location_id: d.location_id || null }))
        .post(`/materials/${props.material.id}/items`, { preserveScroll: true, onSuccess: () => itemForm.reset() });
}
function removeItem(i) {
    if (confirm('Supprimer cet exemplaire ?')) router.delete(`/material-items/${i.id}`, { preserveScroll: true });
}

// Lots (mode consommable)
const lotForm = useForm({ lot_number: '', quantity: 1, expiry_date: '', received_at: '', status: 'conforme', location_id: '' });
function addLot() {
    lotForm.transform((d) => ({ ...d, location_id: d.location_id || null }))
        .post(`/materials/${props.material.id}/lots`, { preserveScroll: true, onSuccess: () => lotForm.reset() });
}
function removeLot(l) {
    if (confirm('Supprimer ce lot ?')) router.delete(`/stock-lots/${l.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="material.name" />
        <template #title>{{ material.name }}</template>

        <Link href="/materials" class="text-sm text-[var(--brand)] hover:underline">← Catalogue</Link>

        <!-- En-tête -->
        <div class="mt-3 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ material.name }}</h2>
                    <p class="text-sm text-gray-500">{{ material.reference || '—' }} · {{ material.category || 'Sans catégorie' }} · {{ material.location || 'Emplacement non défini' }}</p>
                    <p class="mt-1 text-xs text-gray-500">Suivi : <span class="font-medium text-gray-700">{{ material.tracking_label }}</span></p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-bold text-gray-900">{{ material.stock }}</p>
                    <p class="text-xs text-gray-500">en stock</p>
                    <span v-if="material.below_threshold" class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                        Sous le seuil ({{ material.minimum_qty }})
                    </span>
                </div>
            </div>
        </div>

        <!-- Mode QUANTITÉ -->
        <div v-if="material.tracking_mode === 'quantity'" class="mt-6 max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Stock</h3>
            <p class="mt-1 text-sm text-gray-500">Quantité théorique : {{ material.theoretical_qty }} · Seuil : {{ material.minimum_qty }}</p>
            <form class="mt-4 flex items-end gap-2" @submit.prevent="saveStock">
                <div class="flex-1">
                    <InputLabel value="Quantité en stock" />
                    <TextInput v-model="stockForm.current_qty" type="number" />
                    <InputError :message="stockForm.errors.current_qty" />
                </div>
                <button type="submit" class="rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110">Enregistrer</button>
            </form>
        </div>

        <!-- Mode UNITAIRE -->
        <div v-else-if="material.tracking_mode === 'serial'" class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-4 py-3">N° de série</th><th class="px-4 py-3">État</th><th class="px-4 py-3">Emplacement</th><th class="px-4 py-3">Prochain contrôle</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="i in items" :key="i.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ i.serial_number || '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyles[i.status]">{{ i.status_label }}</span></td>
                            <td class="px-4 py-3 text-gray-600">{{ i.location ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ i.next_check_date ?? '—' }}</td>
                            <td class="px-4 py-3 text-right"><button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="removeItem(i)">Suppr.</button></td>
                        </tr>
                        <tr v-if="items.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun exemplaire.</td></tr>
                    </tbody>
                </table>
            </section>
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Ajouter un exemplaire</h3>
                <form class="mt-3 space-y-3" @submit.prevent="addItem">
                    <div><InputLabel value="N° de série" /><TextInput v-model="itemForm.serial_number" /></div>
                    <div>
                        <InputLabel value="État" />
                        <select v-model="itemForm.status" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Emplacement" />
                        <select v-model="itemForm.location_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="">Non défini</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div><InputLabel value="Prochain contrôle" /><TextInput v-model="itemForm.next_check_date" type="date" /></div>
                    <button type="submit" :disabled="itemForm.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Ajouter</button>
                </form>
            </section>
        </div>

        <!-- Mode CONSOMMABLE / LOT -->
        <div v-else class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-4 py-3">N° de lot</th><th class="px-4 py-3">Qté</th><th class="px-4 py-3">Péremption</th><th class="px-4 py-3">Emplacement</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="l in lots" :key="l.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ l.lot_number || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ l.quantity }}</td>
                            <td class="px-4 py-3">
                                <span v-if="l.expired" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Périmé · {{ l.expiry_date }}</span>
                                <span v-else-if="l.expiring_soon" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ l.expiry_date }}</span>
                                <span v-else class="text-gray-600">{{ l.expiry_date ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ l.location ?? '—' }}</td>
                            <td class="px-4 py-3 text-right"><button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="removeLot(l)">Suppr.</button></td>
                        </tr>
                        <tr v-if="lots.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun lot.</td></tr>
                    </tbody>
                </table>
            </section>
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Ajouter un lot</h3>
                <form class="mt-3 space-y-3" @submit.prevent="addLot">
                    <div><InputLabel value="N° de lot" /><TextInput v-model="lotForm.lot_number" /></div>
                    <div><InputLabel value="Quantité" /><TextInput v-model="lotForm.quantity" type="number" /><InputError :message="lotForm.errors.quantity" /></div>
                    <div><InputLabel value="Date de péremption" /><TextInput v-model="lotForm.expiry_date" type="date" /></div>
                    <div>
                        <InputLabel value="Emplacement" />
                        <select v-model="lotForm.location_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="">Non défini</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="lotForm.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Ajouter</button>
                </form>
            </section>
        </div>

        <!-- Historique -->
        <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Historique</h3>
            <div class="mt-3"><HistoryList :logs="history" /></div>
        </section>
    </AppLayout>
</template>
