<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';
import ProtocolFieldInput from '@/Components/ProtocolFieldInput.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    fields: { type: Array, default: () => [] },
    current_holder: { type: String, default: null },
    current_since: { type: String, default: null },
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
    responses: { ...initialResponses },
    photos: {},
});

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
            <!-- Kilométrage -->
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Kilométrage au compteur</label>
                <div class="mt-2 flex items-center gap-2">
                    <input v-model="form.mileage" type="number" min="0" inputmode="numeric" placeholder="ex. 84 200" class="block w-full rounded-lg border-gray-300 px-3 py-2.5 text-lg font-semibold" />
                    <span class="text-sm text-gray-400">km</span>
                </div>
                <p v-if="form.errors.mileage" class="mt-1 text-xs text-red-600">{{ form.errors.mileage }}</p>
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

            <!-- Notes -->
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Observations (optionnel)</label>
                <textarea v-model="form.notes" rows="2" placeholder="Anomalie constatée, remarque…" class="mt-2 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white"></textarea>
            </div>

            <div class="space-y-2">
                <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--brand,#C6362B)] py-3 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">
                    <Icon name="check" :size="18" /> {{ current_holder ? 'Reprendre le service et ouvrir' : 'Ouvrir le service et accéder' }}
                </button>
                <Link href="/t" class="block py-2 text-center text-sm font-medium text-gray-500">Annuler</Link>
            </div>
        </form>
    </TerrainLayout>
</template>
