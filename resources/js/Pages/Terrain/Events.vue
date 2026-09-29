<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
});

const open = computed(() => props.events.filter((e) => !e.resolved));
const closed = computed(() => props.events.filter((e) => e.resolved));

const statusBadge = {
    a_traiter: 'bg-red-100 text-red-800',
    en_cours: 'bg-orange-100 text-orange-800',
    resolu: 'bg-green-100 text-green-800',
    ferme: 'bg-gray-200 text-gray-600',
};
function badgeClass(e) {
    return statusBadge[e.status] || 'bg-gray-100 text-gray-600';
}
const priorityDot = { haute: 'bg-red-500', normale: 'bg-amber-500', basse: 'bg-gray-400' };
</script>

<template>
    <TerrainLayout>
        <Head title="Mes événements" />

        <div class="mx-auto w-full max-w-xl px-4 py-4">
            <h1 class="mb-1 text-xl font-bold text-gray-900">Mes événements</h1>
            <p class="mb-4 text-sm text-gray-500">Vos signalements et les événements qui vous sont assignés.</p>

            <!-- En cours -->
            <h2 class="mb-2 mt-2 text-xs font-bold uppercase tracking-wide text-gray-400">À suivre</h2>
            <div v-if="open.length" class="space-y-2.5">
                <div v-for="e in open" :key="e.id" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="priorityDot[e.priority] || 'bg-gray-400'"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-base font-semibold leading-snug text-gray-900">{{ e.title }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                <span class="rounded-full px-2 py-0.5 font-medium" :class="badgeClass(e)">{{ e.status_label }}</span>
                                <span>{{ e.type_label }}</span>
                                <span v-if="e.vehicle">· {{ e.vehicle }}</span>
                                <span v-if="e.at">· {{ e.at }}</span>
                                <span v-if="e.assigned" class="rounded-full bg-violet-100 px-2 py-0.5 font-medium text-violet-700">Qui m’est assigné</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-2xl border border-dashed border-gray-300 bg-white/50 px-4 py-8 text-center text-sm text-gray-400">
                <Icon name="check" :size="26" class="mx-auto mb-2 text-green-500" />
                Aucun événement en cours vous concernant.
            </div>

            <!-- Clôturés -->
            <template v-if="closed.length">
                <h2 class="mb-2 mt-6 text-xs font-bold uppercase tracking-wide text-gray-400">Clôturés récemment</h2>
                <div class="space-y-2">
                    <div v-for="e in closed" :key="e.id" class="rounded-xl border border-gray-100 bg-white px-4 py-3 opacity-80">
                        <p class="truncate text-sm font-medium text-gray-700">{{ e.title }}</p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-400">
                            <span class="rounded-full px-2 py-0.5 font-medium" :class="badgeClass(e)">{{ e.status_label }}</span>
                            <span v-if="e.vehicle">· {{ e.vehicle }}</span>
                            <span v-if="e.at">· {{ e.at }}</span>
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </TerrainLayout>
</template>
