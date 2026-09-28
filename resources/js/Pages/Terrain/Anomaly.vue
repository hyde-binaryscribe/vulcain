<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    vehicles: { type: Array, default: () => [] },
    vehicle_id: { type: [Number, String], default: null },
    priorities: { type: Array, default: () => [] },
});

const form = useForm({
    title: '',
    description: '',
    priority: 'normale',
    vehicle_id: props.vehicle_id ?? '',
});

const priorityLabels = { basse: 'Basse', normale: 'Normale', haute: 'Haute (urgent)' };

function submit() {
    form.transform((d) => ({ ...d, vehicle_id: d.vehicle_id || null })).post('/t/anomalie', { preserveScroll: true });
}
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Signaler une anomalie" />
        <template #title>Signaler une anomalie</template>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-xs font-medium text-gray-600">Objet</label>
                <input v-model="form.title" type="text" placeholder="Ex. Bouteille O2 vide, DAE HS…" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" />
                <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>

                <label class="mt-3 block text-xs font-medium text-gray-600">Véhicule concerné</label>
                <select v-model="form.vehicle_id" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                    <option value="">— Aucun / général</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.callsign || v.name }}</option>
                </select>

                <label class="mt-3 block text-xs font-medium text-gray-600">Niveau d'urgence</label>
                <div class="mt-1 grid grid-cols-3 gap-2">
                    <button
                        v-for="p in priorities" :key="p" type="button"
                        class="rounded-lg border py-2 text-xs font-semibold"
                        :class="form.priority === p
                            ? (p === 'haute' ? 'border-red-500 bg-red-50 text-red-700' : p === 'normale' ? 'border-orange-400 bg-orange-50 text-orange-700' : 'border-yellow-400 bg-yellow-50 text-yellow-700')
                            : 'border-gray-300 text-gray-600'"
                        @click="form.priority = p"
                    >{{ priorityLabels[p] || p }}</button>
                </div>

                <label class="mt-3 block text-xs font-medium text-gray-600">Description (optionnel)</label>
                <textarea v-model="form.description" rows="3" placeholder="Précisez le problème…" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm"></textarea>
            </div>

            <div class="flex gap-2">
                <Link href="/t" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700">Annuler</Link>
                <button type="submit" :disabled="form.processing || !form.title" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[var(--brand,#C6362B)] py-3 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">
                    <Icon name="bell" :size="16" /> Signaler
                </button>
            </div>
        </form>
    </TerrainLayout>
</template>
