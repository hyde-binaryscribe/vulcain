<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';
import ProtocolFieldInput from '@/Components/ProtocolFieldInput.vue';
import VehicleBodyMap from '@/Components/VehicleBodyMap.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    fields: { type: Array, default: () => [] },
    body: { type: Object, default: () => ({ enabled: false, damages: [], schematics: {} }) },
    opened_at: { type: String, default: null },
    rearm: { type: Array, default: () => [] },
});

function defaultValue(type) {
    if (type === 'checkbox') return false;
    if (type === 'tristate') return '';
    return null;
}
const initialResponses = {};
props.fields.forEach((f) => { initialResponses[f.key] = defaultValue(f.type); });

const form = useForm({
    mileage: props.vehicle.mileage ?? '',
    notes: '',
    responses: { ...initialResponses },
    photos: {},
    body_ack: false,
    restocked_material_ids: [],
});

function setPhoto(key, file) { form.photos[key] = file; }

function submit() {
    form.post(`/t/vehicules/${props.vehicle.id}/fin-de-service`, { preserveScroll: true, forceFormData: true });
}
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Fin de service" />
        <template #title>Fin de service</template>

        <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500"><Icon name="vehicle" :size="22" /></span>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-bold text-gray-900">{{ vehicle.callsign || vehicle.name }}</h1>
                <p class="text-xs text-gray-500">Service ouvert<template v-if="opened_at"> depuis {{ opened_at }}</template></p>
            </div>
        </div>

        <form class="mt-3 space-y-3" @submit.prevent="submit">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Kilométrage de fin (optionnel)</label>
                <div class="mt-2 flex items-center gap-2">
                    <input v-model="form.mileage" type="number" min="0" inputmode="numeric" class="block w-full rounded-lg border-gray-300 px-3 py-2.5 text-lg font-semibold" />
                    <span class="text-sm text-gray-400">km</span>
                </div>
            </div>

            <!-- Protocole de fin de service (champs configurés) -->
            <div v-if="fields.length" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-800">Protocole de fin de service</h2>
                <ProtocolFieldInput
                    v-for="f in fields"
                    :key="f.key"
                    :field="f"
                    v-model="form.responses[f.key]"
                    @photo="(file) => setPhoto(f.key, file)"
                />
            </div>

            <!-- Contrôle carrosserie (obligatoire si activé) -->
            <div v-if="body.enabled" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800"><Icon name="vehicle" :size="16" /> Contrôle carrosserie</h2>
                <p class="mt-0.5 text-xs text-gray-500">Vérifiez l'état extérieur avant de rendre le véhicule.</p>
                <div class="mt-3">
                    <VehicleBodyMap :damages="body.damages" :schematics="body.schematics || {}" />
                </div>
                <p v-if="body.damages.length" class="mt-2 text-xs text-gray-500">{{ body.damages.filter((d) => d.status === 'ouverte').length }} anomalie(s) signalée(s). Nouvelle anomalie : section « Carrosserie » de la fiche véhicule.</p>
                <label class="mt-3 flex items-start gap-2 rounded-xl bg-gray-50 p-3 text-sm text-gray-700">
                    <input v-model="form.body_ack" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300" />
                    <span>J'ai contrôlé la carrosserie du véhicule.</span>
                </label>
                <p v-if="form.errors.body_ack" class="mt-1 text-xs text-red-600">{{ form.errors.body_ack }}</p>
            </div>

            <!-- Réarmement guidé : pointer les consommables remis à niveau -->
            <div v-if="rearm.length" class="rounded-2xl border border-red-200 bg-red-50/50 p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800"><Icon name="materials" :size="16" class="text-red-600" /> Réarmement ({{ rearm.length }})</h2>
                <p class="mt-0.5 text-xs text-gray-500">Cochez ce que vous avez remis à niveau. Le non-réarmé sera signalé au suivant et à l'administration.</p>
                <ul class="mt-3 space-y-1.5">
                    <li v-for="m in rearm" :key="m.id">
                        <label class="flex items-center gap-3 rounded-xl bg-white px-3 py-2.5 text-sm shadow-sm">
                            <input v-model="form.restocked_material_ids" type="checkbox" :value="m.id" class="h-5 w-5 rounded border-gray-300" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-gray-900">{{ m.name }}</span>
                                <span class="block text-xs text-red-600">manque {{ m.missing }} (présent {{ m.stock }}/{{ m.theoretical_qty }})</span>
                            </span>
                        </label>
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Observations (optionnel)</label>
                <textarea v-model="form.notes" rows="3" placeholder="Anomalie, matériel à réapprovisionner…" class="mt-2 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white"></textarea>
            </div>

            <div class="space-y-2">
                <button type="submit" :disabled="form.processing || (body.enabled && !form.body_ack)" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--forge,#12161C)] py-3 text-sm font-semibold text-white hover:brightness-125 disabled:opacity-60">
                    <Icon name="check" :size="18" /> Clôturer le service
                </button>
                <Link :href="`/t/vehicules/${vehicle.id}`" class="block py-2 text-center text-sm font-medium text-gray-500">Annuler</Link>
            </div>
        </form>
    </TerrainLayout>
</template>
