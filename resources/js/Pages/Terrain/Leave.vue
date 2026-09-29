<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import TerrainLayout from '@/Layouts/TerrainLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    mine: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    myBalance: { type: Object, default: () => ({}) },
    to_validate: { type: Number, default: 0 },
});

const statusStyles = {
    en_attente: 'bg-amber-100 text-amber-800',
    approuve: 'bg-green-100 text-green-700',
    refuse: 'bg-red-100 text-red-700',
    annule: 'bg-gray-200 text-gray-600',
};

const showForm = ref(false);
const form = useForm({
    type: props.types[0]?.value ?? 'conge_paye',
    start_date: '',
    end_date: '',
    reason: '',
});

function submit() {
    form.post('/leave', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); showForm.value = false; },
    });
}
function cancel(id) {
    if (confirm('Annuler cette demande ?')) {
        router.post(`/leave/${id}/cancel`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Congés" />
        <template #title>Congés</template>

        <!-- Solde -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-800">Mon solde {{ new Date().getFullYear() }}</h2>
            <div v-if="myBalance.remaining !== null && myBalance.remaining !== undefined" class="mt-3 grid grid-cols-3 gap-2 text-center">
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ myBalance.annual_days }}</p>
                    <p class="text-[11px] text-gray-500">Droits</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-500">{{ myBalance.consumed }}</p>
                    <p class="text-[11px] text-gray-500">Pris</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[var(--brand,#C6362B)]">{{ myBalance.remaining }}</p>
                    <p class="text-[11px] text-gray-500">Restant</p>
                </div>
            </div>
            <p v-else class="mt-2 text-xs text-gray-500">Solde non configuré (aucun droit annuel défini pour votre métier).</p>
        </div>

        <!-- Responsable : validation dans l'app complète -->
        <Link v-if="to_validate > 0" href="/leave" class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800">
            <span class="flex items-center gap-2"><Icon name="bell" :size="16" /> {{ to_validate }} demande(s) à valider</span>
            <Icon name="external" :size="16" />
        </Link>

        <!-- Nouvelle demande -->
        <div class="mt-3">
            <button v-if="!showForm" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[var(--brand,#C6362B)] py-3 text-sm font-semibold text-white hover:brightness-110" @click="showForm = true">
                <Icon name="plus" :size="18" /> Nouvelle demande
            </button>
            <form v-else class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-medium text-gray-600">Type</label>
                    <select v-model="form.type" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Du</label>
                        <input v-model="form.start_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.start_date" class="mt-1 text-xs text-red-600">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Au</label>
                        <input v-model="form.end_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.end_date" class="mt-1 text-xs text-red-600">{{ form.errors.end_date }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Motif (optionnel)</label>
                    <textarea v-model="form.reason" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="showForm = false">Annuler</button>
                    <button type="submit" :disabled="form.processing || !form.start_date || !form.end_date" class="flex-1 rounded-lg bg-[var(--brand,#C6362B)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Envoyer</button>
                </div>
            </form>
        </div>

        <!-- Mes demandes -->
        <h2 class="mb-2 mt-6 text-sm font-semibold uppercase tracking-wide text-gray-500">Mes demandes</h2>
        <div class="space-y-2">
            <div v-for="l in mine" :key="l.id" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ l.type_label }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">Du {{ l.start_date }} au {{ l.end_date }} · {{ l.days }} j</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusStyles[l.status] || 'bg-gray-100 text-gray-600'">{{ l.status_label }}</span>
                </div>
                <p v-if="l.decision_note" class="mt-1 text-xs text-gray-500">« {{ l.decision_note }} »<template v-if="l.reviewer"> — {{ l.reviewer }}</template></p>
                <button v-if="l.status === 'en_attente'" class="mt-2 text-xs font-medium text-red-600 hover:underline" @click="cancel(l.id)">Annuler la demande</button>
            </div>
            <p v-if="mine.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">Aucune demande.</p>
        </div>
    </TerrainLayout>
</template>
