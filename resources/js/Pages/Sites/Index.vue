<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    sites: { type: Array, default: () => [] },
    kinds: { type: Array, default: () => [] },
});

const kindLabels = { centre: 'Centre', depot: 'Dépôt', autre: 'Autre' };

const blank = { name: '', kind: 'centre', is_active: true };
const form = useForm({ ...blank });
const editingId = ref(null);

function resetForm() {
    editingId.value = null;
    form.clearErrors();
    Object.assign(form, blank);
}
function edit(s) {
    editingId.value = s.id;
    form.clearErrors();
    Object.assign(form, { name: s.name, kind: s.kind, is_active: s.is_active });
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => resetForm() };
    if (editingId.value) router.patch(`/sites/${editingId.value}`, form.data(), opts);
    else router.post('/sites', form.data(), opts);
}
function remove(s) {
    if (confirm(`Supprimer le site « ${s.name} » ?`)) {
        router.delete(`/sites/${s.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Sites" />
        <template #title>Sites</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-3">Site</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Véhicules</th><th class="px-4 py-3">Actif</th><th class="px-4 py-3 text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in sites" :key="s.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ s.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ kindLabels[s.kind] ?? s.kind }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ s.vehicles_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="s.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'">{{ s.is_active ? 'Oui' : 'Non' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="edit(s)">Modifier</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(s)">Suppr.</button>
                                </td>
                            </tr>
                            <tr v-if="sites.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun site. Créez-en un →</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier le site' : 'Nouveau site' }}</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Nom" />
                        <TextInput v-model="form.name" placeholder="CIS Nord, Dépôt central…" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Type" />
                        <select v-model="form.kind" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="k in kinds" :key="k" :value="k">{{ kindLabels[k] ?? k }}</option>
                        </select>
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
