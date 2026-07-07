<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
});

const blank = { name: '', vehicle_id: '', parent_id: '', display_order: 0, is_active: true };
const form = useForm({ ...blank });
const editingId = ref(null);

function resetForm() {
    editingId.value = null;
    form.clearErrors();
    Object.assign(form, blank);
}
function edit(l) {
    editingId.value = l.id;
    form.clearErrors();
    Object.assign(form, {
        name: l.name,
        vehicle_id: l.vehicle_id ?? '',
        parent_id: l.parent_id ?? '',
        display_order: l.display_order,
        is_active: l.is_active,
    });
}
function submit() {
    const payload = {
        ...form.data(),
        vehicle_id: form.vehicle_id || null,
        parent_id: form.parent_id || null,
    };
    const opts = { preserveScroll: true, onSuccess: () => resetForm() };
    if (editingId.value) {
        router.patch(`/locations/${editingId.value}`, payload, opts);
    } else {
        router.post('/locations', payload, opts);
    }
}
function toggle(l) {
    router.post(`/locations/${l.id}/toggle`, {}, { preserveScroll: true });
}
function remove(l) {
    if (confirm(`Supprimer « ${l.name} » ?`)) {
        router.delete(`/locations/${l.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Emplacements" />
        <template #title>Emplacements</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Table -->
            <section class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Nom</th>
                                <th class="px-4 py-3">Véhicule</th>
                                <th class="px-4 py-3">Parent</th>
                                <th class="px-4 py-3">Ordre</th>
                                <th class="px-4 py-3">Actif</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="l in locations" :key="l.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ l.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ l.vehicle ?? 'Global / réserve' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ l.parent ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ l.display_order }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="l.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'">
                                        {{ l.is_active ? 'Oui' : 'Non' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="edit(l)">Modifier</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="toggle(l)">Activer/désactiver</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(l)">Suppr.</button>
                                </td>
                            </tr>
                            <tr v-if="locations.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucun emplacement.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Formulaire -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier l’emplacement' : 'Nouvel emplacement' }}</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Nom" />
                        <TextInput v-model="form.name" placeholder="Coffre gauche, Sac rouge…" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Véhicule" />
                        <select v-model="form.vehicle_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">Global / réserve</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Emplacement parent" />
                        <select v-model="form.parent_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">Aucun</option>
                            <option v-for="p in parents" :key="p.id" :value="p.id" :disabled="p.id === editingId">{{ p.name }}</option>
                        </select>
                        <InputError :message="form.errors.parent_id" />
                    </div>
                    <div>
                        <InputLabel value="Ordre d’affichage" />
                        <TextInput v-model="form.display_order" type="number" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
                        Actif
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">
                            {{ editingId ? 'Enregistrer' : 'Créer' }}
                        </button>
                        <button v-if="editingId" type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm" @click="resetForm">Annuler</button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
