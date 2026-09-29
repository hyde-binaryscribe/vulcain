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
    canSubmitForOthers: { type: Boolean, default: false },
    agents: { type: Array, default: () => [] },
    me: { type: Object, default: () => ({}) },
});

const statusStyles = {
    en_attente: 'bg-amber-100 text-amber-800',
    approuve: 'bg-green-100 text-green-700',
    refuse: 'bg-red-100 text-red-700',
    annule: 'bg-gray-200 text-gray-600',
};

const showForm = ref(false);
const editingId = ref(null); // null = création ; sinon id de la demande éditée
const form = useForm({
    type: props.types[0]?.value ?? 'conge_paye',
    start_date: '',
    end_date: '',
    reason: '',
    user_id: props.me?.id ?? null,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.user_id = props.me?.id ?? null;
    form.clearErrors();
    showForm.value = true;
}
function openEdit(l) {
    editingId.value = l.id;
    form.type = l.type;
    form.start_date = l.start ?? '';
    form.end_date = l.end ?? '';
    form.reason = l.reason ?? '';
    form.clearErrors();
    showForm.value = true;
}
function closeForm() {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    const done = () => { form.reset(); showForm.value = false; editingId.value = null; };
    if (editingId.value) {
        form.patch(`/leave/${editingId.value}`, { preserveScroll: true, onSuccess: done });
    } else {
        form.post('/leave', { preserveScroll: true, onSuccess: done });
    }
}
function cancel(id) {
    if (confirm('Annuler cette demande ? Les responsables en seront informés.')) {
        router.post(`/leave/${id}/cancel`, {}, { preserveScroll: true });
    }
}
// Une demande annulée ou refusée n'est plus modifiable/annulable.
function canEdit(l) {
    return l.status === 'en_attente' || l.status === 'approuve';
}
</script>

<template>
    <TerrainLayout>
        <Head title="Terrain — Congés" />
        <template #title>Congés</template>

        <!-- Solde -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-800">Mon solde<template v-if="myBalance.period_label"> · {{ myBalance.period_label }}</template></h2>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ myBalance.annual_days }}</p>
                    <p class="text-[11px] text-gray-500">Acquis</p>
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
            <p class="mt-2 text-[11px] text-gray-400">Jours ouvrables · {{ (myBalance.monthly_rate ?? 2.5).toString().replace('.', ',') }} j/mois acquis (mai → avril, utilisables l'année suivante).</p>
        </div>

        <!-- Responsable : validation dans l'app complète -->
        <Link v-if="to_validate > 0" href="/leave" class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800">
            <span class="flex items-center gap-2"><Icon name="bell" :size="16" /> {{ to_validate }} demande(s) à valider</span>
            <Icon name="external" :size="16" />
        </Link>

        <!-- Nouvelle demande / édition -->
        <div class="mt-3">
            <button v-if="!showForm" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[var(--brand,#C6362B)] py-3 text-sm font-semibold text-white hover:brightness-110" @click="openCreate">
                <Icon name="plus" :size="18" /> Nouvelle demande
            </button>
            <form v-else class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm" @submit.prevent="submit">
                <p class="text-sm font-semibold text-gray-800">{{ editingId ? 'Modifier la demande' : 'Nouvelle demande' }}</p>
                <p v-if="editingId" class="-mt-1 text-xs text-amber-700">La demande repassera en attente de validation.</p>
                <div v-if="!editingId">
                    <label class="block text-xs font-medium text-gray-600">Pour</label>
                    <select v-if="canSubmitForOthers" v-model="form.user_id" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white">
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.id === me.id ? `${a.name} (moi)` : a.name }}</option>
                    </select>
                    <input v-else type="text" :value="me.name" disabled class="mt-1 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-100 px-3 py-2.5 text-sm text-gray-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Type</label>
                    <select v-model="form.type" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Du</label>
                        <input v-model="form.start_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                        <p v-if="form.errors.start_date" class="mt-1 text-xs text-red-600">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Au</label>
                        <input v-model="form.end_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2.5 text-sm focus:bg-white" />
                        <p v-if="form.errors.end_date" class="mt-1 text-xs text-red-600">{{ form.errors.end_date }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Motif (optionnel)</label>
                    <textarea v-model="form.reason" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:bg-white"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="closeForm">Annuler</button>
                    <button type="submit" :disabled="form.processing || !form.start_date || !form.end_date" class="flex-1 rounded-lg bg-[var(--brand,#C6362B)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">{{ editingId ? 'Enregistrer' : 'Envoyer' }}</button>
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
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusStyles[l.status] || 'bg-gray-100 text-gray-600'">{{ l.status_label }}</span>
                        <span v-if="l.modified" class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-medium text-orange-700">Modifiée</span>
                    </div>
                </div>
                <p v-if="l.decision_note" class="mt-1 text-xs text-gray-500">« {{ l.decision_note }} »<template v-if="l.reviewer"> — {{ l.reviewer }}</template></p>
                <div v-if="canEdit(l)" class="mt-2 flex gap-4">
                    <button class="text-xs font-medium text-gray-700 hover:underline" @click="openEdit(l)">Modifier</button>
                    <button class="text-xs font-medium text-red-600 hover:underline" @click="cancel(l.id)">Annuler</button>
                </div>
            </div>
            <p v-if="mine.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">Aucune demande.</p>
        </div>
    </TerrainLayout>
</template>
