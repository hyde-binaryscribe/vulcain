<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
    sites: { type: Array, default: () => [] },
    kinds: { type: Array, default: () => [] },
});

const blank = { name: '', kind: 'mobile', vehicle_id: '', site_id: '', parent_id: '', holder_material_id: '', display_order: 0, is_active: true };
const form = useForm({ ...blank });
const editingId = ref(null);

const isMobile = computed(() => form.kind === 'mobile');

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
        kind: l.kind ?? 'mobile',
        vehicle_id: l.vehicle_id ?? '',
        site_id: l.site_id ?? '',
        parent_id: l.parent_id ?? '',
        holder_material_id: l.holder_material_id ?? '',
        display_order: l.display_order,
        is_active: l.is_active,
    });
}
function submit() {
    const payload = {
        ...form.data(),
        vehicle_id: isMobile.value ? (form.vehicle_id || null) : null,
        site_id: form.site_id || null,
        parent_id: form.parent_id || null,
        holder_material_id: form.holder_material_id || null,
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
                                <th class="px-4 py-3">Emplacement</th>
                                <th class="px-4 py-3">Nature</th>
                                <th v-if="sites.length" class="px-4 py-3">Site</th>
                                <th class="px-4 py-3">Matériel hôte</th>
                                <th class="px-4 py-3">Ordre</th>
                                <th class="px-4 py-3">Actif</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="l in locations" :key="l.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ l.name }}</div>
                                    <div class="text-xs text-gray-400">{{ l.full_path }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="l.kind === 'mobile' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800'">
                                        {{ l.kind_label }}
                                    </span>
                                </td>
                                <td v-if="sites.length" class="px-4 py-3">
                                    <span v-if="l.site" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">🏢 {{ l.site }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ l.holder ?? '—' }}</td>
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
                            <tr v-if="locations.length === 0"><td :colspan="sites.length ? 7 : 6" class="px-4 py-8 text-center text-gray-500">Aucun emplacement.</td></tr>
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
                        <TextInput v-model="form.name" placeholder="Coffre gauche, Sac rouge, Dépôt central…" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Nature" />
                        <div class="mt-1 flex gap-2">
                            <label v-for="k in kinds" :key="k.value" class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="form.kind === k.value ? 'border-[var(--brand)] bg-[var(--brand)]/5 font-semibold text-[var(--brand)]' : 'border-gray-300 text-gray-600'">
                                <input v-model="form.kind" type="radio" class="sr-only" :value="k.value" />
                                {{ k.label }}
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Mobile = à bord d'un véhicule · Fixe = dépôt, pièce de stock.</p>
                    </div>
                    <div v-if="isMobile">
                        <InputLabel value="Véhicule" />
                        <select v-model="form.vehicle_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">Choisir un véhicule…</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                        <InputError :message="form.errors.vehicle_id" />
                    </div>
                    <div>
                        <InputLabel value="Emplacement parent" />
                        <select v-model="form.parent_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">Aucun (racine)</option>
                            <option v-for="p in parents" :key="p.id" :value="p.id" :disabled="p.id === editingId">{{ p.name }}</option>
                        </select>
                        <InputError :message="form.errors.parent_id" />
                    </div>
                    <div v-if="sites.length">
                        <InputLabel value="Site (optionnel)" />
                        <select v-model="form.site_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">Aucun</option>
                            <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <InputError :message="form.errors.site_id" />
                    </div>
                    <div>
                        <InputLabel value="Matériel hôte (optionnel)" />
                        <select v-model="form.holder_material_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">Aucun</option>
                            <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }}<span v-if="m.reference"> ({{ m.reference }})</span></option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Ex. la pochette d'un Lifepak 15 qui contient du consommable.</p>
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
