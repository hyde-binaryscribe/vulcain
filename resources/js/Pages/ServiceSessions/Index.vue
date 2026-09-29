<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    open: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const expanded = ref(null);
function toggle(id) { expanded.value = expanded.value === id ? null : id; }

const reasonLabel = { manual: 'Fin de service', handover: 'Passation' };
</script>

<template>
    <AppLayout>
        <Head title="Suivi de service" />
        <template #title>Suivi de service</template>

        <!-- Services en cours -->
        <section class="mb-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Services en cours ({{ open.length }})</h2>
            <div v-if="open.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="s in open" :key="s.id" class="rounded-2xl border border-green-200 bg-green-50/60 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 font-semibold text-gray-900"><span class="h-2 w-2 rounded-full bg-green-500"></span> {{ s.vehicle }}</span>
                        <button class="text-xs font-medium text-gray-500 hover:text-gray-800" @click="toggle('o' + s.id)">Détail</button>
                    </div>
                    <p class="mt-1 text-sm text-gray-600"><Icon name="user" :size="14" class="mr-1 inline" />{{ s.agent }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">Depuis {{ s.opened_at }}<template v-if="s.open_mileage"> · {{ Number(s.open_mileage).toLocaleString('fr-FR') }} km</template></p>

                    <div v-if="expanded === 'o' + s.id && s.open_responses.length" class="mt-3 space-y-1 border-t border-green-200 pt-2">
                        <div v-for="(r, i) in s.open_responses" :key="i" class="flex items-center justify-between gap-2 text-xs">
                            <span class="text-gray-600">{{ r.label }}</span>
                            <span class="flex items-center gap-1 font-medium" :class="r.alert ? 'text-red-600' : 'text-gray-800'">
                                <span v-if="r.has_photo" title="Photo jointe">📷</span>
                                {{ r.display ?? '' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">Aucun service en cours.</p>
        </section>

        <!-- Historique -->
        <section>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Historique des services</h2>
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Véhicule</th><th class="px-4 py-3">Agent</th>
                            <th class="px-4 py-3">Ouverture</th><th class="px-4 py-3">Clôture</th>
                            <th class="px-4 py-3">Motif</th><th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template v-for="s in history" :key="s.id">
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ s.vehicle }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ s.agent }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ s.opened_at }}<span v-if="s.open_mileage" class="text-xs text-gray-400"> · {{ Number(s.open_mileage).toLocaleString('fr-FR') }} km</span></td>
                                <td class="px-4 py-3 text-gray-600">{{ s.closed_at || '—' }}<span v-if="s.close_mileage" class="text-xs text-gray-400"> · {{ Number(s.close_mileage).toLocaleString('fr-FR') }} km</span></td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="s.close_reason === 'handover' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'">{{ reasonLabel[s.close_reason] || '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button v-if="s.open_responses.length || s.close_responses.length" class="text-xs font-medium text-[var(--brand)] hover:underline" @click="toggle('h' + s.id)">
                                        {{ expanded === 'h' + s.id ? 'Masquer' : 'Vérifications' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="expanded === 'h' + s.id">
                                <td colspan="6" class="bg-gray-50/60 px-4 py-3">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Prise de service</p>
                                            <div v-if="s.open_responses.length" class="space-y-1">
                                                <div v-for="(r, i) in s.open_responses" :key="i" class="flex items-center justify-between gap-2 text-xs">
                                                    <span class="text-gray-600">{{ r.label }}</span>
                                                    <span class="flex items-center gap-1 font-medium" :class="r.alert ? 'text-red-600' : 'text-gray-800'"><span v-if="r.has_photo" title="Photo jointe">📷</span>{{ r.display ?? '' }}</span>
                                                </div>
                                            </div>
                                            <p v-else class="text-xs text-gray-400">Aucune vérification.</p>
                                            <p v-if="s.open_notes" class="mt-1 text-xs text-gray-500 italic">« {{ s.open_notes }} »</p>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Fin de service</p>
                                            <div v-if="s.close_responses.length" class="space-y-1">
                                                <div v-for="(r, i) in s.close_responses" :key="i" class="flex items-center justify-between gap-2 text-xs">
                                                    <span class="text-gray-600">{{ r.label }}</span>
                                                    <span class="flex items-center gap-1 font-medium" :class="r.alert ? 'text-red-600' : 'text-gray-800'"><span v-if="r.has_photo" title="Photo jointe">📷</span>{{ r.display ?? '' }}</span>
                                                </div>
                                            </div>
                                            <p v-else class="text-xs text-gray-400">Aucune vérification.</p>
                                            <p v-if="s.close_notes" class="mt-1 text-xs text-gray-500 italic">« {{ s.close_notes }} »</p>
                                            <p v-if="s.closed_by && s.close_reason === 'handover'" class="mt-1 text-xs text-gray-400">Repris par {{ s.closed_by }}</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="history.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucun service clôturé.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
