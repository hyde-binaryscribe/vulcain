<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    vehicles: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    sites: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    vehicleTypes: { type: Array, default: () => [] },
});

const siteWord = computed(() => usePage().props.tenant?.profile?.site_label || 'Site');
// Mode multi-site : aucun site actif sélectionné et plusieurs sites accessibles.
const siteContext = computed(() => usePage().props.siteContext || { options: [], current: null });
const multiSite = computed(() => !siteContext.value.current && siteContext.value.options.length > 1);

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
            <article v-for="v in vehicles" :key="v.id" class="flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                <!-- En-tête : le nom du véhicule prime, le type devient un badge discret -->
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="`/vehicles/${v.id}`" class="truncate text-base font-semibold text-gray-900 hover:text-[var(--brand)] hover:underline">{{ v.name }}</Link>
                            <span v-if="v.type" class="shrink-0 rounded-md bg-[var(--brand)]/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[var(--brand)]">{{ v.type }}</span>
                        </div>
                        <p class="mt-1 truncate text-xs text-gray-500">
                            <template v-if="v.callsign || v.registration">
                                <span v-if="v.callsign">📻 {{ v.callsign }}</span>
                                <span v-if="v.callsign && v.registration"> · </span>
                                <span v-if="v.registration">🔖 {{ v.registration }}</span>
                            </template>
                            <span v-else>Ni indicatif ni immatriculation</span>
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusStyles[v.status]">{{ v.status_label }}</span>
                </div>

                <div class="flex flex-1 flex-col p-5 pt-4">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-if="v.site"
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="multiSite ? 'bg-[var(--brand)]/10 text-[var(--brand)] ring-1 ring-[var(--brand)]/30' : 'bg-gray-100 text-gray-600'"
                        >🏢 {{ v.site }}</span>
                        <span v-else-if="multiSite" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs italic text-gray-400">Sans {{ siteWord.toLowerCase() }}</span>
                        <span v-if="v.center" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ v.center }}</span>
                        <span v-if="v.mileage" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ Number(v.mileage).toLocaleString('fr-FR') }} km</span>
                    </div>

                    <p class="mt-3 text-xs text-gray-500">
                        <span class="font-medium text-gray-700">{{ v.assigned_names.length }}</span> utilisateur(s) autorisé(s)
                        <span v-if="v.assigned_names.length"> : {{ v.assigned_names.join(', ') }}</span>
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2 pt-2">
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
                    <div>
                        <InputLabel value="Type" />
                        <TextInput v-model="form.type" list="vehicle-types" placeholder="VSAV, Ambulance type A…" />
                        <datalist id="vehicle-types">
                            <option v-for="t in vehicleTypes" :key="t" :value="t" />
                        </datalist>
                        <p class="mt-1 text-xs text-gray-400">Choisissez une suggestion ou saisissez librement.</p>
                    </div>
                    <div><InputLabel value="Indicatif" /><TextInput v-model="form.callsign" /></div>
                    <div><InputLabel value="Immatriculation" /><TextInput v-model="form.registration" /></div>
                    <div><InputLabel value="Centre" /><TextInput v-model="form.center" /></div>
                    <div v-if="sites.length">
                        <InputLabel :value="siteWord" />
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
