<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    mine: { type: Array, default: () => [] },
    pending: { type: Array, default: () => [] },
    calendar: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    jobRoles: { type: Array, default: () => [] },
    rules: { type: Array, default: () => [] },
    team: { type: Array, default: () => [] },
    myBalance: { type: Object, default: () => ({}) },
    month: { type: String, default: '' },
    monthLabel: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
    canSubmitForOthers: { type: Boolean, default: false },
    agents: { type: Array, default: () => [] },
    me: { type: Object, default: () => ({}) },
});

const statusStyles = {
    en_attente: 'bg-amber-100 text-amber-800',
    approuve: 'bg-green-100 text-green-800',
    refuse: 'bg-red-100 text-red-800',
    annule: 'bg-gray-200 text-gray-600',
};

// --- Demande (tout le monde) ---
const editingId = ref(null); // null = création ; sinon id de la demande éditée
const form = useForm({ type: props.types[0]?.value ?? 'conge_paye', start_date: '', end_date: '', reason: '', user_id: props.me?.id ?? null });
function submit() {
    const done = () => { form.reset('start_date', 'end_date', 'reason'); form.user_id = props.me?.id ?? null; editingId.value = null; };
    if (editingId.value) form.patch(`/leave/${editingId.value}`, { preserveScroll: true, onSuccess: done });
    else form.post('/leave', { preserveScroll: true, onSuccess: done });
}
function edit(l) {
    editingId.value = l.id;
    form.user_id = props.me?.id ?? null;
    form.type = l.type;
    form.start_date = l.start ?? '';
    form.end_date = l.end ?? '';
    form.reason = l.reason ?? '';
    form.clearErrors();
    if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' });
}
function cancelEdit() {
    editingId.value = null;
    form.reset('start_date', 'end_date', 'reason');
    form.clearErrors();
}
function cancel(l) {
    if (confirm('Annuler cette demande ? Les responsables en seront informés.')) router.post(`/leave/${l.id}/cancel`, {}, { preserveScroll: true });
}
// Une demande annulée ou refusée n'est plus modifiable/annulable.
const canEdit = (l) => l.status === 'en_attente' || l.status === 'approuve';
function decide(l, action) {
    const note = action === 'refuse' ? (window.prompt('Motif (facultatif) :') ?? '') : '';
    router.post(`/leave/${l.id}/decision`, { action, decision_note: note }, { preserveScroll: true });
}

// --- Règles par métier ---
const rulesForm = useForm({ rules: props.rules.map((r) => ({ ...r })) });
function saveRules() {
    rulesForm.post('/leave/rules', { preserveScroll: true });
}

// --- Calendrier ---
function shiftMonth(delta) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    router.get('/leave', { month: `${d.getFullYear()}-${mm}` }, { preserveScroll: true });
}

const ruleByRole = computed(() => Object.fromEntries(props.rules.map((r) => [r.job_role, r])));

// Construit la grille du mois (semaines commençant lundi) avec les congés par jour.
const weeks = computed(() => {
    if (!props.month) return [];
    const [y, m] = props.month.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const daysInMonth = new Date(y, m, 0).getDate();
    const startOffset = (first.getDay() + 6) % 7; // lundi = 0

    const cells = [];
    for (let i = 0; i < startOffset; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const entries = props.calendar.filter((e) => dateStr >= e.start && dateStr <= e.end);

        // Conflit : un métier dont le nb d'absences approuvées ce jour dépasse la règle.
        let conflict = false;
        const byRole = {};
        entries.forEach((e) => {
            if (e.status !== 'approuve' || !e.job_role) return;
            byRole[e.job_role] = (byRole[e.job_role] ?? 0) + 1;
        });
        Object.entries(byRole).forEach(([role, count]) => {
            const max = ruleByRole.value[role]?.max_simultaneous;
            if (max != null && count > max) conflict = true;
        });

        cells.push({ day: d, dateStr, entries, conflict });
    }
    while (cells.length % 7 !== 0) cells.push(null);

    const out = [];
    for (let i = 0; i < cells.length; i += 7) out.push(cells.slice(i, i + 7));
    return out;
});
const weekDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
</script>

<template>
    <AppLayout>
        <Head title="Congés & absences" />
        <template #title>Congés & absences</template>

        <!-- ===== Vue responsable : calendrier 2/3 + panneau 1/3 ===== -->
        <div v-if="canManage" class="grid gap-6 lg:grid-cols-3">
            <!-- Calendrier (2/3) -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold capitalize text-gray-900">{{ monthLabel }}</h2>
                    <div class="flex gap-1">
                        <button class="rounded-lg border border-gray-300 p-1.5 hover:bg-gray-50" @click="shiftMonth(-1)"><Icon name="arrow-left" :size="16" /></button>
                        <button class="rounded-lg border border-gray-300 p-1.5 hover:bg-gray-50" @click="shiftMonth(1)"><Icon name="arrow-right" :size="16" /></button>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-gray-400">
                    <div v-for="d in weekDays" :key="d" class="py-1">{{ d }}</div>
                </div>
                <div v-for="(week, wi) in weeks" :key="wi" class="grid grid-cols-7 gap-1">
                    <div
                        v-for="(cell, ci) in week"
                        :key="ci"
                        class="min-h-20 rounded-lg border p-1 text-left align-top"
                        :class="cell ? (cell.conflict ? 'border-red-300 bg-red-50' : 'border-gray-100') : 'border-transparent'"
                    >
                        <template v-if="cell">
                            <div class="mb-0.5 flex items-center justify-between">
                                <span class="text-[11px] font-medium text-gray-500">{{ cell.day }}</span>
                                <Icon v-if="cell.conflict" name="bell" :size="12" class="text-red-500" />
                            </div>
                            <div class="space-y-0.5">
                                <span
                                    v-for="e in cell.entries.slice(0, 3)"
                                    :key="e.id"
                                    class="block truncate rounded px-1 py-0.5 text-[10px] leading-tight"
                                    :class="e.status === 'approuve' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                                    :title="`${e.user} — ${e.type_label}`"
                                >{{ e.user }}</span>
                                <span v-if="cell.entries.length > 3" class="block text-[10px] text-gray-400">+{{ cell.entries.length - 3 }}</span>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-green-400"></span> Approuvé</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-amber-400"></span> En attente</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-red-300"></span> Effectif dépassé</span>
                </div>
            </section>

            <!-- Panneau (1/3) -->
            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-gray-500">À valider ({{ pending.length }})</h2>
                    <div class="divide-y divide-gray-100">
                        <div v-for="l in pending" :key="l.id" class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-gray-900">{{ l.user }}</p>
                                <span v-if="l.modified" class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-700">Modifiée</span>
                            </div>
                            <p class="text-xs text-gray-500">{{ l.type_label }} · {{ l.start_date }} → {{ l.end_date }} ({{ l.days }} j)</p>
                            <p v-if="l.modified" class="mt-1 text-xs font-medium text-orange-700">Dates modifiées par l'agent — à revérifier avant validation.</p>
                            <p v-if="l.conflict" class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600"><Icon name="bell" :size="12" /> Effectif du métier dépassé</p>
                            <div class="mt-2 flex gap-1">
                                <button class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700" @click="decide(l, 'approve')">Approuver</button>
                                <button class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" @click="decide(l, 'refuse')">Refuser</button>
                            </div>
                        </div>
                        <p v-if="pending.length === 0" class="px-5 py-6 text-center text-sm text-gray-400">Aucune demande en attente.</p>
                    </div>
                </section>

                <!-- Nouvelle demande (le responsable est aussi salarié) -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Nouvelle demande</h2>
                    <form class="mt-3 space-y-3" @submit.prevent="submit">
                        <select v-if="canSubmitForOthers" v-model="form.user_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.id === me.id ? `${a.name} (moi)` : a.name }}</option>
                        </select>
                        <select v-model="form.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="form.start_date" type="date" class="block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm" />
                            <input v-model="form.end_date" type="date" class="block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm" />
                        </div>
                        <InputError :message="form.errors.end_date" />
                        <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Envoyer</button>
                    </form>
                </section>
            </div>

            <!-- Règles par métier (pleine largeur) -->
            <section v-if="rules.length" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-3">
                <h2 class="text-base font-semibold text-gray-900">Règles par métier</h2>
                <p class="mt-1 text-sm text-gray-500">Effectif maximum en absence simultanée et droits annuels par métier. Sans valeur, les droits suivent la réalité : 2,5 jours ouvrables acquis par mois (30 j/an).</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="py-2 pr-4">Métier</th><th class="py-2 pr-4">Absences simultanées max</th><th class="py-2 pr-4">Droits (jours/an)</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in rulesForm.rules" :key="r.job_role" class="border-t border-gray-100">
                                <td class="py-2 pr-4 font-medium text-gray-900">{{ r.label }}</td>
                                <td class="py-2 pr-4"><input v-model.number="rulesForm.rules[i].max_simultaneous" type="number" min="0" class="w-28 rounded-lg border border-gray-300 px-2 py-1.5" placeholder="illimité" /></td>
                                <td class="py-2 pr-4"><input v-model.number="rulesForm.rules[i].annual_days" type="number" min="0" class="w-28 rounded-lg border border-gray-300 px-2 py-1.5" placeholder="30 (défaut)" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4"><button :disabled="rulesForm.processing" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60" @click="saveRules">Enregistrer les règles</button></div>
            </section>

            <!-- Soldes équipe + mes demandes (pleine largeur) -->
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm lg:col-span-3">
                <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Soldes de l'équipe ({{ new Date().getFullYear() }})</h2>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-5 py-2">Nom</th><th class="px-4 py-2">Métier</th><th class="px-4 py-2">Arrivée</th><th class="px-4 py-2">Consommés</th><th class="px-4 py-2">Droits</th><th class="px-4 py-2">Restant</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(t, i) in team" :key="i">
                            <td class="px-5 py-2 font-medium text-gray-900">{{ t.name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ t.job_role_label }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ t.hire_date ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ t.consumed }} j</td>
                            <td class="px-4 py-2 text-gray-600">{{ t.annual_days ?? '—' }}</td>
                            <td class="px-4 py-2 font-medium" :class="t.remaining === 0 ? 'text-red-600' : 'text-gray-800'">{{ t.remaining ?? '—' }}</td>
                        </tr>
                        <tr v-if="team.length === 0"><td colspan="6" class="px-5 py-6 text-center text-gray-400">Aucun métier renseigné sur les comptes.</td></tr>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- ===== Vue salarié ===== -->
        <div v-else class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:order-2">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier ma demande' : 'Nouvelle demande' }}</h2>
                <p v-if="editingId" class="mt-1 text-sm text-amber-700">La demande repassera en attente de validation.</p>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div v-if="!editingId">
                        <InputLabel value="Pour" />
                        <select v-if="canSubmitForOthers" v-model="form.user_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.id === me.id ? `${a.name} (moi)` : a.name }}</option>
                        </select>
                        <input v-else type="text" :value="me.name" disabled class="block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-100 px-3 py-2.5 text-sm text-gray-500" />
                    </div>
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
                    <div class="flex gap-2">
                        <button v-if="editingId" type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50" @click="cancelEdit">Annuler</button>
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">{{ editingId ? 'Enregistrer' : 'Envoyer la demande' }}</button>
                    </div>
                </form>

                <div v-if="myBalance.annual_days != null" class="mt-5 rounded-xl bg-gray-50 p-4 text-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Mon solde<template v-if="myBalance.period_label"> · {{ myBalance.period_label }}</template></p>
                    <p class="mt-1 font-semibold text-gray-900">{{ myBalance.remaining }} j ouvrables restants</p>
                    <p class="text-xs text-gray-500">{{ myBalance.consumed }} pris / {{ myBalance.annual_days }} acquis</p>
                    <p v-if="myBalance.hire_date && myBalance.annual_full != null && myBalance.annual_days !== myBalance.annual_full" class="mt-1 text-xs text-gray-400">
                        Au prorata depuis le {{ myBalance.hire_date }} (année pleine : {{ myBalance.annual_full }} j).
                    </p>
                    <p class="mt-1 text-xs text-gray-400">Acquisition : {{ (myBalance.monthly_rate ?? 2.5).toString().replace('.', ',') }} j ouvrables/mois.</p>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm lg:col-span-2 lg:order-1">
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
                                <span v-if="l.modified" class="ml-1 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-medium text-orange-700">Modifiée</span>
                                <span v-if="l.decision_note" class="mt-0.5 block text-xs text-gray-400">{{ l.decision_note }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="canEdit(l)" class="flex justify-end gap-2">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50" @click="edit(l)">Modifier</button>
                                    <button class="rounded-lg border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="cancel(l)">Annuler</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="mine.length === 0"><td colspan="5" class="px-5 py-8 text-center text-gray-400">Aucune demande.</td></tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
