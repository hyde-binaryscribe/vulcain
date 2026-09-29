<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    types: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    trackingModes: { type: Array, default: () => [] },
});

const blank = { name: '', category_id: '', tracking_mode: 'serial', display_order: 0, is_active: true };
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
    Object.assign(form, {
        name: t.name, category_id: t.category_id ?? '', tracking_mode: t.tracking_mode,
        display_order: t.display_order, is_active: t.is_active,
    });
}
function submit() {
    form.transform((d) => ({ ...d, category_id: d.category_id || null }));
    const opts = { preserveScroll: true, onSuccess: () => resetForm() };
    if (editingId.value) form.patch(`/material-types/${editingId.value}`, opts);
    else form.post('/material-types', opts);
}
function toggle(t) {
    router.post(`/material-types/${t.id}/toggle`, {}, { preserveScroll: true });
}
function remove(t) {
    const warn = t.models_count > 0
        ? `Supprimer « ${t.name} » ? ${t.models_count} modèle(s) y sont rattachés (ils perdront leur type).`
        : `Supprimer le type « ${t.name} » ?`;
    if (confirm(warn)) router.delete(`/material-types/${t.id}`, { preserveScroll: true });
}

// --- Catégories de matériel ---
const categoryForm = useForm({ name: '' });
function addCategory() {
    categoryForm.post('/material-categories', { preserveScroll: true, onSuccess: () => categoryForm.reset() });
}
function removeCategory(c) {
    const warn = c.types_count > 0
        ? `Supprimer la catégorie « ${c.name} » ? ${c.types_count} type(s) y sont rattachés (ils perdront leur catégorie).`
        : `Supprimer la catégorie « ${c.name} » ?`;
    if (confirm(warn)) router.delete(`/material-categories/${c.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Types de matériel" />
        <template #title>Types de matériel</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <p class="mb-3 text-sm text-gray-500">
                    Un type (Thermomètre, Compresse 5×5…) regroupe des modèles (marque + modèle) et fixe leur mode de
                    suivi : durable au n° de série, ou consommable au lot / péremption.
                </p>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Catégorie</th>
                                <th class="px-4 py-3">Suivi</th>
                                <th class="px-4 py-3">Modèles</th>
                                <th class="px-4 py-3">Actif</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in types" :key="t.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ t.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.category ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.tracking_label }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.models_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="t.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'">{{ t.is_active ? 'Oui' : 'Non' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="edit(t)">Modifier</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="toggle(t)">Activer/désactiver</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(t)">Suppr.</button>
                                </td>
                            </tr>
                            <tr v-if="types.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucun type de matériel.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier le type' : 'Nouveau type' }}</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Nom" />
                        <TextInput v-model="form.name" placeholder="Thermomètre, Compresse 5×5…" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Mode de suivi" />
                        <select v-model="form.tracking_mode" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option v-for="m in trackingModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <InputError :message="form.errors.tracking_mode" />
                    </div>
                    <div>
                        <InputLabel value="Catégorie (optionnel)" />
                        <select v-model="form.category_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
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

                <!-- Catégories de matériel -->
                <div class="mt-6 border-t border-gray-100 pt-5">
                    <h2 class="text-base font-semibold text-gray-900">Catégories</h2>
                    <p class="mt-1 text-sm text-gray-500">Regroupent les types (ex. Diagnostic, Consommables, Oxygène…).</p>
                    <form class="mt-3 flex gap-2" @submit.prevent="addCategory">
                        <TextInput v-model="categoryForm.name" class="min-w-0 flex-1" placeholder="Nouvelle catégorie" />
                        <button type="submit" :disabled="categoryForm.processing || !categoryForm.name" class="rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Ajouter</button>
                    </form>
                    <InputError :message="categoryForm.errors.name" class="mt-1" />
                    <ul class="mt-3 divide-y divide-gray-100">
                        <li v-for="c in categories" :key="c.id" class="flex items-center gap-2 py-2 text-sm">
                            <span class="min-w-0 flex-1 truncate text-gray-900">{{ c.name }}</span>
                            <span class="shrink-0 text-xs text-gray-400">{{ c.types_count }} type(s)</span>
                            <button class="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Supprimer" @click="removeCategory(c)">Suppr.</button>
                        </li>
                        <li v-if="categories.length === 0" class="py-3 text-center text-xs text-gray-400">Aucune catégorie.</li>
                    </ul>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
