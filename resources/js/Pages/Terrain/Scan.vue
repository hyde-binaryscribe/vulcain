<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    vehicles: { type: Array, default: () => [] },
});

const query = ref('');
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.vehicles;
    return props.vehicles.filter((v) => (v.callsign || '').toLowerCase().includes(q) || v.name.toLowerCase().includes(q));
});
function open(v) {
    router.visit(`/t/vehicules/${v.id}/prise-de-service`);
}
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Scanner" />
        <template #title>Scanner un véhicule</template>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 text-center shadow-sm">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[var(--brand,#C6362B)]/10 text-[var(--brand,#C6362B)]"><Icon name="camera" :size="30" /></span>
            <p class="mt-3 text-sm font-medium text-gray-800">Scannez le QR code présent dans le véhicule</p>
            <p class="mt-1 text-xs text-gray-500">
                Utilisez l'appareil photo de votre téléphone : le QR démarre la prise de service du véhicule.
            </p>
        </div>

        <div class="mt-6">
            <p class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Ou choisir manuellement</p>
            <div class="relative">
                <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input v-model="query" type="search" placeholder="Rechercher un véhicule…" class="block w-full rounded-xl border-gray-300 py-2.5 pl-9 pr-3 text-sm" />
            </div>
            <div class="mt-2 space-y-2">
                <button
                    v-for="v in filtered" :key="v.id"
                    class="flex w-full items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3.5 text-left shadow-sm active:scale-[0.99]"
                    @click="open(v)"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-500"><Icon name="vehicle" :size="20" /></span>
                    <span class="min-w-0 flex-1 truncate font-medium text-gray-900">{{ v.callsign || v.name }}</span>
                    <Icon name="chevron-right" :size="18" class="text-gray-300" />
                </button>
                <p v-if="filtered.length === 0" class="py-6 text-center text-sm text-gray-400">Aucun véhicule.</p>
            </div>
        </div>
    </TerrainLayout>
</template>
