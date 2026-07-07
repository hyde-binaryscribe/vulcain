<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    template: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    availableMaterials: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    frequencies: { type: Array, default: () => [] },
});

// Réglages du modèle
const settings = useForm({
    name: props.template.name,
    frequency: props.template.frequency,
    custom_days: props.template.custom_days ?? '',
    is_active: props.template.is_active,
});
function saveSettings() {
    settings.transform((d) => ({ ...d, custom_days: d.custom_days || null }))
        .patch(`/templates/${props.template.id}`, { preserveScroll: true });
}

// Ajout d'un élément
const addForm = useForm({ material_id: '', expected_qty: 1, location_id: '', photo_required: false });
function selectMaterial() {
    const m = props.availableMaterials.find((x) => x.id === addForm.material_id);
    if (m) {
        addForm.expected_qty = m.theoretical_qty ?? 0;
        addForm.location_id = m.location_id ?? '';
    }
}
function addItem() {
    addForm.transform((d) => ({ ...d, location_id: d.location_id || null }))
        .post(`/templates/${props.template.id}/items`, { preserveScroll: true, onSuccess: () => addForm.reset() });
}
function removeItem(i) {
    router.delete(`/templates/${props.template.id}/items/${i.id}`, { preserveScroll: true });
}

// Édition inline de la quantité
const editingQty = ref({});
function saveQty(i) {
    router.patch(`/templates/${props.template.id}/items/${i.id}`, {
        expected_qty: editingQty.value[i.id] ?? i.expected_qty,
        photo_required: i.photo_required,
        display_order: i.display_order,
    }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="template.name" />
        <template #title>{{ template.name }}</template>

        <Link href="/templates" class="text-sm text-[var(--brand)] hover:underline">← Modèles</Link>

        <div class="mt-3 grid gap-6 lg:grid-cols-3">
            <!-- Réglages -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Réglages</h2>
                <p class="text-xs text-gray-500">Véhicule : {{ template.vehicle }} · v{{ template.version }}</p>
                <form class="mt-4 space-y-4" @submit.prevent="saveSettings">
                    <div><InputLabel value="Nom" /><TextInput v-model="settings.name" /></div>
                    <div>
                        <InputLabel value="Fréquence" />
                        <select v-model="settings.frequency" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>
                    <div v-if="settings.frequency === 'custom'">
                        <InputLabel value="Tous les (jours)" />
                        <TextInput v-model="settings.custom_days" type="number" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="settings.is_active" type="checkbox" class="rounded border-gray-300" /> Actif
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110">Enregistrer</button>
                </form>
            </section>

            <!-- Éléments -->
            <section class="lg:col-span-2">
                <div class="mb-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Ajouter un matériel à contrôler</h3>
                    <form class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="addItem">
                        <div class="min-w-48 flex-1">
                            <select v-model="addForm.material_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2" @change="selectMaterial">
                                <option value="">Choisir un matériel…</option>
                                <option v-for="m in availableMaterials" :key="m.id" :value="m.id">{{ m.name }}<span v-if="m.reference"> ({{ m.reference }})</span></option>
                            </select>
                        </div>
                        <div class="w-24">
                            <TextInput v-model="addForm.expected_qty" type="number" />
                        </div>
                        <label class="flex items-center gap-1 text-xs text-gray-600">
                            <input v-model="addForm.photo_required" type="checkbox" class="rounded border-gray-300" /> Photo
                        </label>
                        <button type="submit" :disabled="!addForm.material_id" class="rounded-lg bg-[var(--brand)] px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">Ajouter</button>
                    </form>
                    <InputError :message="addForm.errors.material_id" />
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-3">Matériel</th><th class="px-4 py-3">Emplacement</th><th class="px-4 py-3">Qté attendue</th><th class="px-4 py-3">Photo</th><th class="px-4 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="i in items" :key="i.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ i.material }}</div>
                                    <div class="text-xs text-gray-400">{{ i.reference }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ i.location ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <input :value="i.expected_qty" type="number" class="w-16 rounded-lg border border-gray-300 px-2 py-1 text-sm" @input="editingQty[i.id] = $event.target.value" />
                                        <button class="rounded bg-gray-900 px-2 py-1 text-xs text-white" @click="saveQty(i)">OK</button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ i.photo_required ? 'Oui' : '—' }}</td>
                                <td class="px-4 py-3 text-right"><button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="removeItem(i)">Retirer</button></td>
                            </tr>
                            <tr v-if="items.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun élément. Ajoutez du matériel à contrôler.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
