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
    current_holder: { type: String, default: null },
    current_since: { type: String, default: null },
    crew: { type: Array, default: () => [] },
    shortage: { type: Array, default: () => [] },
});

// Valeurs par défaut selon le type de champ.
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
    partner_user_id: '',
    responses: { ...initialResponses },
    photos: {},
    body_ack: false,
});

const viewLabels = { avant: 'Avant', arriere: 'Arrière', gauche: 'Côté gauche', droite: 'Côté droit', dessus: 'Dessus' };
const viewLabel = (v) => viewLabels[v] ?? v;

function setPhoto(key, file) { form.photos[key] = file; }

function submit() {
    form.post(`/t/vehicules/${props.vehicle.id}/prise-de-service`, { preserveScroll: true, forceFormData: true });
}
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Prise de service" />
        <template #title>Prise de service</template>

        <!-- Véhicule -->
        <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500"><Icon name="vehicle" :size="22" /></span>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-bold text-gray-900">{{ vehicle.callsign || vehicle.name }}</h1>
                <p class="text-xs text-gray-500">{{ vehicle.type || '—' }} · {{ vehicle.status_label }}</p>
            </div>
        </div>

        <!-- Passation -->
        <div v-if="current_holder" class="mt-3 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800">
            <Icon name="bell" :size="18" class="mt-0.5 shrink-0" />
            <p>
                Ce véhicule est actuellement pris par <strong>{{ current_holder }}</strong><template v-if="current_since"> depuis le {{ current_since }}</template>.
                Votre prise de service <strong>clôturera automatiquement</strong> la sienne (passation).
            </p>
        </div>

        <div class="mt-3 flex items-start gap-2 rounded-2xl border border-gray-200 bg-white p-3.5 text-xs text-gray-500 shadow-sm">
            <Icon name="shield" :size="16" class="mt-0.5 shrink-0 text-[var(--brand,#C6362B)]" />
            <p>La vérification de prise de service est obligatoire pour accéder au véhicule.</p>
        </div>

        <form class="mt-3 space-y-3" @submit.prevent="submit">
            <!-- Manquements laissés par l'équipage précédent -->
            <div v-if="shortage.length" class="rounded-2xl border-l-4 border-red-400 bg-red-50 p-4">
                <p class="flex items-center gap-2 text-sm font-bold text-red-800"><Icon name="materials" :size="16" /> {{ shortage.length }} consommable(s) non réarmé(s)</p>
                <p class="mt-1 text-xs text-red-700">{{ shortage.map(s => s.name + ' (' + s.missing + ')').join(', ') }}</p>
            </div>

            <!-- Kilométrage -->
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Kilométrage au compteur</label>
                <div class="mt-2 flex items-center gap-2">
                    <input v-model="form.mileage" type="number" min="0" inputmode="numeric" placeholder="ex. 84 200" class="block w-full rounded-lg border-gray-300 px-3 py-2.5 text-lg font-semibold" />
                    <span class="text-sm text-gray-400">km</span>
                </div>
                <p v-if="form.errors.mileage" class="mt-1 text-xs text-red-600">{{ form.errors.mileage }}</p>
            </div>

            <!-- Binôme -->
            <div v-if="crew.length" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Binôme (optionnel)</label>
                <p class="mb-2 mt-0.5 text-xs text-gray-500">Il partagera ce service avec vous, avec les mêmes actions.</p>
                <select v-model="form.partner_user_id" class="block w-full rounded-lg border-gray-300 px-3 py-2.5 text-base">
                    <option value="">— Aucun —</option>
                    <option v-for="c in crew" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <!-- Protocole (champs configurés) -->
            <div v-if="fields.length" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-800">Protocole de prise de service</h2>
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
                <p class="mt-0.5 text-xs text-gray-500">Vérifiez l'état extérieur et comparez aux anomalies déjà connues.</p>
                <div class="mt-3">
                    <VehicleBodyMap :damages="body.damages" :schematics="body.schematics || {}" />
                </div>
                <p v-if="body.damages.length" class="mt-2 text-xs text-gray-500">{{ body.damages.filter((d) => d.status === 'ouverte').length }} anomalie(s) déjà signalée(s). Nouvelle anomalie : ouvrez le service puis section « Carrosserie ».</p>
                <label class="mt-3 flex items-start gap-2 rounded-xl bg-gray-50 p-3 text-sm text-gray-700">
                    <input v-model="form.body_ack" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300" />
                    <span>J'ai contrôlé la carrosserie du véhicule.</span>
                </label>
                <p v-if="form.errors.body_ack" class="mt-1 text-xs text-red-600">{{ form.errors.body_ack }}</p>
            </div>

            <!-- Notes -->
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Observations (optionnel)</label>
                <textarea v-model="form.notes" rows="2" placeholder="Anomalie constatée, remarque…" class="mt-2 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white"></textarea>
            </div>

            <div class="space-y-2">
                <button type="submit" :disabled="form.processing || (body.enabled && !form.body_ack)" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--brand,#C6362B)] py-3 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">
                    <Icon name="check" :size="18" /> {{ current_holder ? 'Reprendre le service et ouvrir' : 'Ouvrir le service et accéder' }}
                </button>
                <Link href="/t" class="block py-2 text-center text-sm font-medium text-gray-500">Annuler</Link>
            </div>
        </form>
    </TerrainLayout>
</template>
