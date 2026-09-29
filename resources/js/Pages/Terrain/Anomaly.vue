<script setup>
import { ref } from 'vue';
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
    photo: null,
});

const priorityLabels = { basse: 'Basse', normale: 'Normale', haute: 'Haute (urgent)' };

const photoPreview = ref(null);
function onPhoto(e) {
    const file = e.target.files?.[0] ?? null;
    form.photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : null;
}
function clearPhoto() {
    form.photo = null;
    photoPreview.value = null;
}

function submit() {
    form.transform((d) => ({ ...d, vehicle_id: d.vehicle_id || null })).post('/t/anomalie', { preserveScroll: true, forceFormData: true });
}
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Signaler une anomalie" />
        <template #title>Signaler une anomalie</template>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="block text-xs font-medium text-gray-600">Objet</label>
                <input v-model="form.title" type="text" placeholder="Ex. Bouteille O2 vide, DAE HS…" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>

                <label class="mt-3 block text-xs font-medium text-gray-600">Véhicule concerné</label>
                <select v-model="form.vehicle_id" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white">
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
                <textarea v-model="form.description" rows="3" placeholder="Précisez le problème…" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white"></textarea>

                <label class="mt-3 block text-xs font-medium text-gray-600">Photo (optionnel)</label>
                <div v-if="photoPreview" class="mt-1">
                    <div class="relative inline-block">
                        <img :src="photoPreview" alt="" class="h-40 w-full rounded-lg object-cover" />
                        <button type="button" class="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white" @click="clearPhoto">
                            <Icon name="x" :size="16" />
                        </button>
                    </div>
                </div>
                <label v-else class="mt-1 flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-6 text-sm text-gray-500 hover:bg-gray-50">
                    <Icon name="camera" :size="20" />
                    Prendre / ajouter une photo
                    <input type="file" accept="image/*" capture="environment" class="hidden" @change="onPhoto" />
                </label>
                <p v-if="form.errors.photo" class="mt-1 text-xs text-red-600">{{ form.errors.photo }}</p>
                <p v-if="form.progress" class="mt-1 text-xs text-gray-400">Envoi… {{ form.progress.percentage }}%</p>
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
