<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    types: { type: Array, default: () => [] },
});

const form = useForm({ name: '', capacity_l: '', volume_l: '', pressure_min: 0, pressure_max: 200, is_active: true });
const editingId = ref(null);

function resetForm() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}
function edit(t) {
    editingId.value = t.id;
    form.name = t.name;
    form.capacity_l = t.capacity_l ?? '';
    form.volume_l = t.volume_l ?? '';
    form.pressure_min = t.pressure_min;
    form.pressure_max = t.pressure_max;
    form.is_active = t.is_active;
}
function submit() {
    const payload = form.transform((d) => ({ ...d, capacity_l: d.capacity_l === '' ? null : d.capacity_l, volume_l: d.volume_l === '' ? null : d.volume_l }));
    if (editingId.value) {
        payload.patch(`/oxygene/types-bouteilles/${editingId.value}`, { preserveScroll: true, onSuccess: resetForm });
    } else {
        payload.post('/oxygene/types-bouteilles', { preserveScroll: true, onSuccess: resetForm });
    }
}
function toggle(t) { router.post(`/oxygene/types-bouteilles/${t.id}/toggle`, {}, { preserveScroll: true }); }
function remove(t) {
    if (confirm(`Supprimer le type « ${t.name} » ?`)) {
        router.delete(`/oxygene/types-bouteilles/${t.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Types de bouteilles d'oxygène" />
        <template #title>Types de bouteilles d'oxygène</template>

        <div class="mb-4 flex items-center gap-2 border-b border-gray-200 pb-3">
            <Link href="/oxygene/tableau-de-bord" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-800"><Icon name="arrow-left" :size="15" /> Tableau de bord Oxygène</Link>
        </div>

        <p class="mb-4 max-w-2xl text-sm text-gray-500">
            Caractéristiques pré-réglées des bouteilles (B2, B5, B11, B15…). À la déclaration d'une bouteille sur un véhicule,
            choisir le type pré-remplit capacité, pression et volume d'O₂ — l'agent ne saisit plus que le type, le n° de série et le niveau.
        </p>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-2.5">Type</th>
                        <th class="px-4 py-2.5">Capacité (L eau)</th>
                        <th class="px-4 py-2.5">Pression</th>
                        <th class="px-4 py-2.5">Volume O₂</th>
                        <th class="px-4 py-2.5">Actif</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="t in types" :key="t.id" :class="t.is_active ? '' : 'opacity-50'">
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ t.name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ t.capacity_l ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ t.pressure_min }}–{{ t.pressure_max }} bar</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ t.volume_l != null ? t.volume_l + ' L' : '—' }}</td>
                        <td class="px-4 py-3">{{ t.is_active ? 'Oui' : 'Non' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="edit(t)">Modifier</button>
                            <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="toggle(t)">Activer/désactiver</button>
                            <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(t)">Suppr.</button>
                        </td>
                    </tr>
                    <tr v-if="types.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucun type.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Ajouter / modifier -->
        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-gray-900">{{ editingId ? 'Modifier le type' : 'Ajouter un type' }}</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-12">
                <div class="sm:col-span-3"><label class="text-xs text-gray-500">Nom (ex. B5)</label><input v-model="form.name" class="mt-0.5 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm" /><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Capacité (L eau)</label><input v-model="form.capacity_l" type="number" class="mt-0.5 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm" /></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Volume O₂ (L)</label><input v-model="form.volume_l" type="number" class="mt-0.5 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm" /></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Pression min</label><input v-model.number="form.pressure_min" type="number" class="mt-0.5 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm" /></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Pression max</label><input v-model.number="form.pressure_max" type="number" class="mt-0.5 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm" /><p v-if="form.errors.pressure_max" class="mt-1 text-xs text-red-600">{{ form.errors.pressure_max }}</p></div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <button type="button" :disabled="form.processing || !form.name" class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="submit">{{ editingId ? 'Enregistrer' : 'Ajouter' }}</button>
                <button v-if="editingId" type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm" @click="resetForm">Annuler</button>
            </div>
        </div>
    </AppLayout>
</template>
