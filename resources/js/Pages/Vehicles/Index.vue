<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    vehicles: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    sites: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const statusStyles = {
    disponible: 'bg-green-100 text-green-800',
    indisponible: 'bg-gray-200 text-gray-700',
    maintenance: 'bg-amber-100 text-amber-800',
    reparation: 'bg-orange-100 text-orange-800',
    reforme: 'bg-red-100 text-red-800',
};

// Formulaire véhicule (création / édition)
const showForm = ref(false);
const editingId = ref(null);
const form = useForm({
    name: '', type: '', callsign: '', registration: '', center: '', site_id: '',
    status: 'disponible', commissioned_at: '', mileage: '', observations: '',
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}
function openEdit(v) {
    editingId.value = v.id;
    form.clearErrors();
    Object.assign(form, {
        name: v.name, type: v.type ?? '', callsign: v.callsign ?? '', registration: v.registration ?? '',
        center: v.center ?? '', site_id: v.site_id ?? '', status: v.status, commissioned_at: v.commissioned_at ?? '',
        mileage: v.mileage ?? '', observations: v.observations ?? '',
    });
    showForm.value = true;
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    form.transform((d) => ({ ...d, site_id: d.site_id || null }));
    if (editingId.value) {
        form.patch(`/vehicles/${editingId.value}`, opts);
    } else {
        form.post('/vehicles', opts);
    }
}
function remove(v) {
    if (confirm(`Supprimer « ${v.name} » ?`)) {
        router.delete(`/vehicles/${v.id}`, { preserveScroll: true });
    }
}

// Affectations
const showAssign = ref(false);
const assignForm = useForm({ user_ids: [] });
const assignVehicle = ref(null);
function openAssign(v) {
    assignVehicle.value = v;
    assignForm.user_ids = [...v.assigned_user_ids];
    showAssign.value = true;
}
function saveAssign() {
    assignForm.put(`/vehicles/${assignVehicle.value.id}/assignments`, {
        preserveScroll: true,
        onSuccess: () => (showAssign.value = false),
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Véhicules" />
        <template #title>Véhicules</template>

        <div class="mb-4 flex justify-end">
            <button class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110" @click="openCreate">
                + Ajouter un véhicule
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="v in vehicles" :key="v.id" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between bg-[var(--brand)] px-5 py-3 text-white">
                    <span class="text-lg font-bold tracking-wide">{{ v.type || 'ENGIN' }}</span>
                    <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ v.status_label }}</span>
                </div>
                <div class="p-5">
                    <Link :href="`/vehicles/${v.id}`" class="text-base font-semibold text-gray-900 hover:text-[var(--brand)] hover:underline">{{ v.name }}</Link>
                    <p class="text-xs text-gray-500">{{ v.callsign || '—' }} · {{ v.registration || '—' }}</p>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyles[v.status]">{{ v.status_label }}</span>
                        <span v-if="v.center" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ v.center }}</span>
                    </div>

                    <p class="mt-3 text-xs text-gray-500">
                        <span class="font-medium text-gray-700">{{ v.assigned_names.length }}</span> utilisateur(s) autorisé(s)
                        <span v-if="v.assigned_names.length"> : {{ v.assigned_names.join(', ') }}</span>
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link :href="`/vehicles/${v.id}`" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">Voir la fiche</Link>
                        <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="openEdit(v)">Modifier</button>
                        <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="openAssign(v)">Affecter</button>
                        <button class="ml-auto rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50" @click="remove(v)">Supprimer</button>
                    </div>
                </div>
            </article>

            <p v-if="vehicles.length === 0" class="col-span-full rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                Aucun véhicule. Cliquez sur « Ajouter un véhicule ».
            </p>
        </div>

        <!-- Modal formulaire -->
        <div v-if="showForm" class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 p-4" @click.self="showForm = false">
            <div class="my-8 w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Modifier le véhicule' : 'Nouveau véhicule' }}</h3>
                <form class="mt-4 grid grid-cols-2 gap-4" @submit.prevent="submit">
                    <div class="col-span-2">
                        <InputLabel value="Nom" /><TextInput v-model="form.name" /><InputError :message="form.errors.name" />
                    </div>
                    <div><InputLabel value="Type" /><TextInput v-model="form.type" /></div>
                    <div><InputLabel value="Indicatif" /><TextInput v-model="form.callsign" /></div>
                    <div><InputLabel value="Immatriculation" /><TextInput v-model="form.registration" /></div>
                    <div><InputLabel value="Centre" /><TextInput v-model="form.center" /></div>
                    <div v-if="sites.length">
                        <InputLabel value="Site" />
                        <select v-model="form.site_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option value="">—</option>
                            <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Statut" />
                        <select v-model="form.status" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div><InputLabel value="Mise en service" /><TextInput v-model="form.commissioned_at" type="date" /></div>
                    <div><InputLabel value="Kilométrage" /><TextInput v-model="form.mileage" type="number" /></div>
                    <div class="col-span-2">
                        <InputLabel value="Observations" />
                        <textarea v-model="form.observations" rows="2" class="block w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10"></textarea>
                    </div>
                    <div class="col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="showForm = false">Annuler</button>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal affectations -->
        <div v-if="showAssign" class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 p-4" @click.self="showAssign = false">
            <div class="my-8 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Affecter — {{ assignVehicle?.name }}</h3>
                <p class="mt-1 text-sm text-gray-500">Utilisateurs autorisés sur ce véhicule.</p>
                <div class="mt-4 max-h-72 space-y-2 overflow-y-auto">
                    <label v-for="u in users" :key="u.id" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        <input v-model="assignForm.user_ids" type="checkbox" :value="u.id" class="rounded border-gray-300" />
                        <span>{{ u.name }}<span v-if="u.grade" class="text-gray-400"> · {{ u.grade }}</span></span>
                    </label>
                    <p v-if="users.length === 0" class="text-sm text-gray-500">Aucun utilisateur actif.</p>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="showAssign = false">Annuler</button>
                    <button type="button" :disabled="assignForm.processing" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="saveAssign">Enregistrer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
