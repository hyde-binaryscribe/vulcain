<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    bags: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
});

const transferring = ref(null); // bag being transferred
const target = ref('');

function openTransfer(bag) {
    transferring.value = bag;
    target.value = '';
}
function confirmTransfer() {
    if (!target.value) return;
    router.post(`/sacs/${transferring.value.id}/transfer`, { to_vehicle_id: target.value }, {
        preserveScroll: true,
        onSuccess: () => { transferring.value = null; },
    });
}
function vehicleLabel(v) {
    return v.callsign || v.name;
}
</script>

<template>
    <AppLayout>
        <Head title="Sacs" />
        <template #title>Sacs</template>

        <p class="mb-4 max-w-2xl text-sm text-gray-500">
            Vue d'ensemble des sacs et de leur affectation. Un transfert déplace le sac
            <span class="font-medium text-gray-700">et tout son contenu</span> vers une autre ambulance, avec traçabilité.
        </p>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-3">Sac</th><th class="px-4 py-3">Véhicule</th><th class="px-4 py-3">Contenu</th><th class="px-4 py-3 text-right">Action</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="b in bags" :key="b.id" :class="b.is_active ? '' : 'opacity-60'">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ b.name }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="b.vehicle" class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                        <Icon name="vehicle" :size="13" /> {{ b.vehicle }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ b.materials }} réf.</td>
                                <td class="px-4 py-3 text-right">
                                    <button class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="openTransfer(b)">
                                        <Icon name="arrow-right" :size="14" /> Transférer
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="bags.length === 0"><td colspan="4" class="px-4 py-10 text-center text-gray-500">Aucun sac. Créez des emplacements de nature « Sac » sur vos véhicules.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Derniers mouvements</h2>
                <ul class="divide-y divide-gray-100">
                    <li v-for="(m, i) in movements" :key="i" class="px-5 py-3 text-sm">
                        <p class="font-medium text-gray-900">{{ m.bag }}</p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500">
                            <span>{{ m.from || '—' }}</span>
                            <Icon name="arrow-right" :size="13" class="text-gray-400" />
                            <span class="font-medium text-gray-700">{{ m.to }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ m.at }}<span v-if="m.user"> · {{ m.user }}</span></p>
                    </li>
                    <li v-if="movements.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">Aucun mouvement.</li>
                </ul>
            </section>
        </div>

        <!-- Modale de transfert -->
        <div v-if="transferring" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="transferring = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Transférer « {{ transferring.name }} »</h3>
                <p class="mt-1 text-sm text-gray-500">Le sac et son contenu seront réaffectés au véhicule choisi.</p>
                <label class="mt-4 block text-xs font-medium text-gray-600">Vers le véhicule</label>
                <select v-model="target" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    <option value="">Choisir…</option>
                    <option v-for="v in vehicles.filter((v) => v.id !== transferring.vehicle_id)" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option>
                </select>
                <div class="mt-5 flex justify-end gap-2">
                    <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="transferring = null">Annuler</button>
                    <button :disabled="!target" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-50" @click="confirmTransfer">Transférer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
