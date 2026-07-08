<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    consumables: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    reference: '',
    category_id: '',
    location_id: '',
    minimum_qty: 0,
});

function declare() {
    form.transform((d) => ({
        ...d,
        category_id: d.category_id || null,
        location_id: d.location_id || null,
    })).post('/pharmacy/consumables', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <AppLayout>
        <Head title="Pharmacie" />
        <template #title>Pharmacie</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Liste des consommables -->
            <section class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-3">Consommable</th><th class="px-4 py-3">Emplacement</th><th class="px-4 py-3">Stock</th><th class="px-4 py-3">Péremption proche</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in consumables" :key="c.id" :class="c.expired ? 'bg-red-50' : (c.expiring_soon ? 'bg-amber-50' : '')">
                                <td class="px-4 py-3">
                                    <Link :href="`/materials/${c.id}`" class="font-medium text-gray-900 hover:text-[var(--brand)] hover:underline">{{ c.name }}</Link>
                                    <div class="text-xs text-gray-400">{{ c.reference }}<span v-if="c.category"> · {{ c.category }}</span></div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ c.location ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span :class="c.below_threshold ? 'font-semibold text-red-600' : 'text-gray-700'">{{ c.stock }}</span>
                                    <span class="text-xs text-gray-400"> / min {{ c.minimum_qty }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="c.expired ? 'font-semibold text-red-600' : (c.expiring_soon ? 'font-semibold text-amber-600' : 'text-gray-600')">{{ c.nearest_expiry ?? '—' }}</span>
                                    <span v-if="c.expired" class="ml-1 rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700">Périmé</span>
                                    <span v-else-if="c.expiring_soon" class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">Proche</span>
                                </td>
                            </tr>
                            <tr v-if="consumables.length === 0"><td colspan="4" class="px-4 py-8 text-center text-gray-500">Aucun consommable déclaré.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-400">Les lots (quantité + péremption) se gèrent depuis la fiche de chaque consommable.</p>
            </section>

            <!-- Déclaration rapide -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Déclarer un consommable</h2>
                <p class="mt-1 text-xs text-gray-500">Crée un matériel suivi par lot / péremption.</p>
                <form class="mt-4 space-y-4" @submit.prevent="declare">
                    <div>
                        <InputLabel value="Nom" />
                        <TextInput v-model="form.name" placeholder="Sérum physiologique 500 ml" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Référence (optionnel)" />
                        <TextInput v-model="form.reference" placeholder="PHA-SERUM-500" />
                    </div>
                    <div>
                        <InputLabel value="Catégorie (optionnel)" />
                        <select v-model="form.category_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Emplacement (optionnel)" />
                        <select v-model="form.location_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="">—</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Seuil d'alerte (stock mini)" />
                        <TextInput v-model="form.minimum_qty" type="number" />
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Déclarer</button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
