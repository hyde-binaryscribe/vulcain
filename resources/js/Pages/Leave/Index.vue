<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    mine: { type: Array, default: () => [] },
    pending: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const statusStyles = {
    en_attente: 'bg-amber-100 text-amber-800',
    approuve: 'bg-green-100 text-green-800',
    refuse: 'bg-red-100 text-red-800',
    annule: 'bg-gray-200 text-gray-600',
};

const form = useForm({ type: props.types[0]?.value ?? 'conge_paye', start_date: '', end_date: '', reason: '' });
function submit() {
    form.post('/leave', { preserveScroll: true, onSuccess: () => form.reset('start_date', 'end_date', 'reason') });
}
function cancel(l) {
    if (confirm('Annuler cette demande ?')) router.post(`/leave/${l.id}/cancel`, {}, { preserveScroll: true });
}
function decide(l, action) {
    const note = action === 'refuse' ? (window.prompt('Motif (facultatif) :') ?? '') : '';
    router.post(`/leave/${l.id}/decision`, { action, decision_note: note }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Congés & absences" />
        <template #title>Congés & absences</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Nouvelle demande -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:order-2">
                <h2 class="text-base font-semibold text-gray-900">Nouvelle demande</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Nature" />
                        <select v-model="form.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Du" />
                            <input v-model="form.start_date" type="date" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                            <InputError :message="form.errors.start_date" />
                        </div>
                        <div>
                            <InputLabel value="Au" />
                            <input v-model="form.end_date" type="date" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                            <InputError :message="form.errors.end_date" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Motif (facultatif)" />
                        <textarea v-model="form.reason" rows="3" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Envoyer la demande</button>
                </form>
            </section>

            <!-- Listes -->
            <div class="space-y-6 lg:col-span-2 lg:order-1">
                <!-- À valider (responsables) -->
                <section v-if="canManage" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-gray-500">À valider ({{ pending.length }})</h2>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="l in pending" :key="l.id">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">{{ l.user }}</p>
                                    <p class="text-xs text-gray-500">{{ l.type_label }} · {{ l.start_date }} → {{ l.end_date }} ({{ l.days }} j)</p>
                                    <p v-if="l.reason" class="mt-0.5 text-xs text-gray-400">{{ l.reason }}</p>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700" @click="decide(l, 'approve')">Approuver</button>
                                    <button class="ml-1 rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" @click="decide(l, 'refuse')">Refuser</button>
                                </td>
                            </tr>
                            <tr v-if="pending.length === 0"><td class="px-5 py-6 text-center text-gray-400">Aucune demande en attente.</td></tr>
                        </tbody>
                    </table>
                </section>

                <!-- Mes demandes -->
                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Mes demandes</h2>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-5 py-2">Nature</th><th class="px-4 py-2">Période</th><th class="px-4 py-2">Jours</th><th class="px-4 py-2">Statut</th><th class="px-4 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="l in mine" :key="l.id">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ l.type_label }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ l.start_date }} → {{ l.end_date }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ l.days }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyles[l.status]">{{ l.status_label }}</span>
                                    <span v-if="l.decision_note" class="mt-0.5 block text-xs text-gray-400">{{ l.decision_note }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button v-if="l.status === 'en_attente'" class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="cancel(l)">Annuler</button>
                                </td>
                            </tr>
                            <tr v-if="mine.length === 0"><td colspan="5" class="px-5 py-8 text-center text-gray-400">Aucune demande.</td></tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
