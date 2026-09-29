<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    opened_at: { type: String, default: null },
});

const form = useForm({
    mileage: props.vehicle.mileage ?? '',
    notes: '',
});

function submit() {
    form.post(`/t/vehicules/${props.vehicle.id}/fin-de-service`, { preserveScroll: true });
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

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-sm font-semibold text-gray-800">Observations (optionnel)</label>
                <textarea v-model="form.notes" rows="3" placeholder="Anomalie, matériel à réapprovisionner…" class="mt-2 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm"></textarea>
            </div>

            <div class="space-y-2">
                <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--forge,#12161C)] py-3 text-sm font-semibold text-white hover:brightness-125 disabled:opacity-60">
                    <Icon name="check" :size="18" /> Clôturer le service
                </button>
                <Link :href="`/t/vehicules/${vehicle.id}`" class="block py-2 text-center text-sm font-medium text-gray-500">Annuler</Link>
            </div>
        </form>
    </TerrainLayout>
</template>
