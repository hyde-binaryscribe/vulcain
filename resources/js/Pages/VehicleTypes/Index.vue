<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    types: { type: Array, default: () => [] },
});

const blank = { name: '', display_order: 0, is_active: true };
const form = useForm({ ...blank });
const editingId = ref(null);

function resetForm() {
    editingId.value = null;
    form.clearErrors();
    Object.assign(form, blank);
}
function edit(t) {
    editingId.value = t.id;
    form.clearErrors();
    Object.assign(form, { name: t.name, display_order: t.display_order, is_active: t.is_active });
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => resetForm() };
    if (editingId.value) form.patch(`/vehicle-types/${editingId.value}`, opts);
    else form.post('/vehicle-types', opts);
}
function toggle(t) {
    router.post(`/vehicle-types/${t.id}/toggle`, {}, { preserveScroll: true });
}
function remove(t) {
    const warn = t.vehicles_count > 0
        ? `Supprimer « ${t.name} » ? ${t.vehicles_count} véhicule(s) le portent encore (leur type sera conservé mais ne sera plus proposé).`
        : `Supprimer le type « ${t.name} » ?`;
    if (confirm(warn)) router.delete(`/vehicle-types/${t.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Types de véhicule" />
        <template #title>Types de véhicule</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <p class="mb-3 text-sm text-gray-500">
                    Ces types alimentent la liste imposée dans le formulaire véhicule. La liste initiale
                    reprend les types courants de votre secteur — adaptez-la librement.
                </p>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Véhicules</th>
                                <th class="px-4 py-3">Ordre</th>
                                <th class="px-4 py-3">Actif</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in types" :key="t.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ t.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.vehicles_count }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.display_order }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="t.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'">{{ t.is_active ? 'Oui' : 'Non' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="edit(t)">Modifier</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="toggle(t)">Activer/désactiver</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(t)">Suppr.</button>
                                </td>
                            </tr>
                            <tr v-if="types.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun type de véhicule.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier le type' : 'Nouveau type' }}</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Libellé" />
                        <TextInput v-model="form.name" placeholder="VSAV, Ambulance type A…" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Ordre d’affichage" />
                        <TextInput v-model="form.display_order" type="number" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" /> Actif
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">{{ editingId ? 'Enregistrer' : 'Créer' }}</button>
                        <button v-if="editingId" type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm" @click="resetForm">Annuler</button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
