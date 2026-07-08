<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    materials: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    trackingModes: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const canExport = computed(() => (usePage().props.auth?.user?.permissions || []).includes('exports.create'));

const statusStyles = {
    conforme: 'bg-green-100 text-green-800',
    manquant: 'bg-amber-100 text-amber-800',
    hs: 'bg-red-100 text-red-800',
    a_remplacer: 'bg-orange-100 text-orange-800',
    en_reparation: 'bg-blue-100 text-blue-800',
    indisponible: 'bg-gray-200 text-gray-700',
};

// Recherche
const searchTerm = ref(props.search);
function runSearch() {
    router.get('/materials', { q: searchTerm.value }, { preserveState: true, replace: true });
}

// Mise à jour rapide (statut + note)
const quick = reactive({});
props.materials.forEach((m) => (quick[m.id] = { status: m.status, note: '' }));
function saveQuick(m) {
    router.patch(`/materials/${m.id}/status`, quick[m.id], { preserveScroll: true });
}

// Ajout
const addForm = useForm({
    name: '', reference: '', category_id: '', location_id: '',
    tracking_mode: props.trackingModes[0]?.value ?? 'quantity', theoretical_qty: 0, minimum_qty: 0, status: 'conforme',
});
function add() {
    addForm.transform((d) => ({ ...d, category_id: d.category_id || null, location_id: d.location_id || null }))
        .post('/materials', { preserveScroll: true, onSuccess: () => addForm.reset() });
}

// Catégories
const catForm = useForm({ name: '' });
function addCategory() {
    catForm.post('/material-categories', { preserveScroll: true, onSuccess: () => catForm.reset() });
}
function removeCategory(c) {
    if (confirm(`Supprimer la catégorie « ${c.name} » ?`)) {
        router.delete(`/material-categories/${c.id}`, { preserveScroll: true });
    }
}

// Édition complète
const editing = ref(null);
const editForm = useForm({
    name: '', reference: '', description: '', category_id: '', location_id: '',
    tracking_mode: 'quantity', theoretical_qty: 0, minimum_qty: 0, serial_number: '',
    expiry_date: '', next_check_date: '', status: 'conforme', observations: '',
});
function openEdit(m) {
    editing.value = m;
    editForm.clearErrors();
    Object.assign(editForm, {
        name: m.name, reference: m.reference ?? '', description: m.description ?? '',
        category_id: m.category_id ?? '', location_id: m.location_id ?? '',
        tracking_mode: m.tracking_mode, theoretical_qty: m.theoretical_qty, minimum_qty: m.minimum_qty,
        serial_number: m.serial_number ?? '', expiry_date: m.expiry_date ?? '', next_check_date: m.next_check_date ?? '',
        status: m.status, observations: m.observations ?? '',
    });
}
function saveEdit() {
    editForm.transform((d) => ({ ...d, category_id: d.category_id || null, location_id: d.location_id || null }))
        .patch(`/materials/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
}
function remove(m) {
    if (confirm(`Supprimer « ${m.name} » ?`)) {
        router.delete(`/materials/${m.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Matériel" />
        <template #title>Catalogue matériel</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Table -->
            <section class="lg:col-span-2">
                <div class="mb-3 flex gap-2">
                    <TextInput v-model="searchTerm" placeholder="Rechercher nom ou référence…" @keyup.enter="runSearch" />
                    <button class="rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white" @click="runSearch">Rechercher</button>
                    <a v-if="canExport" href="/exports/materiel.csv" class="whitespace-nowrap rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50">⬇ CSV</a>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Matériel</th>
                                <th class="px-4 py-3">Catégorie</th>
                                <th class="px-4 py-3">Qté th.</th>
                                <th class="px-4 py-3">Emplacement</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3">Mise à jour rapide</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in materials" :key="m.id">
                                <td class="px-4 py-3">
                                    <Link :href="`/materials/${m.id}`" class="font-medium text-gray-900 hover:text-[var(--brand)] hover:underline">{{ m.name }}</Link>
                                    <div class="text-xs text-gray-500">{{ m.reference || '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ m.category ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ m.theoretical_qty }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ m.location ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyles[m.status]">{{ m.status_label }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <select v-model="quick[m.id].status" class="rounded-lg border border-gray-300 px-2 py-1 text-xs">
                                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                        </select>
                                        <input v-model="quick[m.id].note" placeholder="Note" class="w-20 rounded-lg border border-gray-300 px-2 py-1 text-xs" />
                                        <button class="rounded-lg bg-gray-900 px-2 py-1 text-xs font-medium text-white" @click="saveQuick(m)">OK</button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="openEdit(m)">Modifier</button>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(m)">Suppr.</button>
                                </td>
                            </tr>
                            <tr v-if="materials.length === 0"><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucun matériel.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Ajout + catégories -->
            <section class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Nouveau matériel</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="add">
                        <div>
                            <InputLabel value="Nom" /><TextInput v-model="addForm.name" /><InputError :message="addForm.errors.name" />
                        </div>
                        <div><InputLabel value="Référence" /><TextInput v-model="addForm.reference" /></div>
                        <div>
                            <InputLabel value="Catégorie" />
                            <select v-model="addForm.category_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                                <option value="">Sans catégorie</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Emplacement" />
                            <select v-model="addForm.location_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                                <option value="">Non défini</option>
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Mode suivi" />
                                <select v-model="addForm.tracking_mode" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                                    <option v-for="t in trackingModes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div><InputLabel value="Qté théorique" /><TextInput v-model="addForm.theoretical_qty" type="number" /></div>
                        </div>
                        <button type="submit" :disabled="addForm.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Ajouter</button>
                    </form>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Catégories</h2>
                    <form class="mt-3 flex gap-2" @submit.prevent="addCategory">
                        <TextInput v-model="catForm.name" placeholder="Nouvelle catégorie" />
                        <button type="submit" class="rounded-lg bg-[var(--brand)] px-3 py-2.5 text-sm font-semibold text-white">+</button>
                    </form>
                    <InputError :message="catForm.errors.name" />
                    <ul class="mt-3 flex flex-wrap gap-2">
                        <li v-for="c in categories" :key="c.id" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs">
                            {{ c.name }}
                            <button class="text-gray-400 hover:text-red-600" @click="removeCategory(c)">✕</button>
                        </li>
                        <li v-if="categories.length === 0" class="text-xs text-gray-500">Aucune catégorie.</li>
                    </ul>
                </div>
            </section>
        </div>

        <!-- Modal édition -->
        <div v-if="editing" class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 p-4" @click.self="editing = null">
            <div class="my-8 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Modifier {{ editing.name }}</h3>
                <form class="mt-4 grid grid-cols-2 gap-4" @submit.prevent="saveEdit">
                    <div class="col-span-2"><InputLabel value="Nom" /><TextInput v-model="editForm.name" /><InputError :message="editForm.errors.name" /></div>
                    <div><InputLabel value="Référence" /><TextInput v-model="editForm.reference" /></div>
                    <div><InputLabel value="N° de série" /><TextInput v-model="editForm.serial_number" /></div>
                    <div>
                        <InputLabel value="Catégorie" />
                        <select v-model="editForm.category_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="">Sans catégorie</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Emplacement" />
                        <select v-model="editForm.location_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="">Non défini</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Mode suivi" />
                        <select v-model="editForm.tracking_mode" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="t in trackingModes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Statut" />
                        <select v-model="editForm.status" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div><InputLabel value="Qté théorique" /><TextInput v-model="editForm.theoretical_qty" type="number" /></div>
                    <div><InputLabel value="Qté minimale" /><TextInput v-model="editForm.minimum_qty" type="number" /></div>
                    <div><InputLabel value="Péremption" /><TextInput v-model="editForm.expiry_date" type="date" /></div>
                    <div><InputLabel value="Prochain contrôle" /><TextInput v-model="editForm.next_check_date" type="date" /></div>
                    <div class="col-span-2">
                        <InputLabel value="Observations" />
                        <textarea v-model="editForm.observations" rows="2" class="block w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                    </div>
                    <div class="col-span-2 flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="editing = null">Annuler</button>
                        <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
