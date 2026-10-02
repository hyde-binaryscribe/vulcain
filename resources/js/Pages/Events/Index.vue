<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    columns: { type: Array, default: () => [] },
    boards: { type: Array, default: () => [] },
    current_board: { type: [Number, String], default: null },
    can_manage_board: { type: Boolean, default: false },
    types: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    materialStatuses: { type: Array, default: () => [] },
});

// --- Tableaux & colonnes ---
function selectBoard(id) {
    router.get('/events', { board: id }, { preserveState: false, preserveScroll: true });
}
function addBoard() {
    const name = window.prompt('Nom du nouveau tableau ?');
    if (name && name.trim()) router.post('/kanban/boards', { name: name.trim() });
}
function removeBoard() {
    if (props.boards.length <= 1) return;
    if (window.confirm('Supprimer ce tableau ? (il doit être vide)')) router.delete(`/kanban/boards/${props.current_board}`);
}
function addColumn() {
    const name = window.prompt('Nom de la nouvelle colonne ?');
    if (name && name.trim()) router.post(`/kanban/boards/${props.current_board}/columns`, { name: name.trim() }, { preserveScroll: true });
}
function renameColumn(col) {
    const name = window.prompt('Renommer la colonne :', col.label);
    if (name && name.trim()) router.patch(`/kanban/columns/${col.value}`, { name: name.trim(), is_done: col.is_done }, { preserveScroll: true });
}
function toggleDone(col) {
    router.patch(`/kanban/columns/${col.value}`, { name: col.label, is_done: !col.is_done }, { preserveScroll: true });
}
function removeColumn(col) {
    if (window.confirm(`Supprimer la colonne « ${col.label} » ? (elle doit être vide)`)) router.delete(`/kanban/columns/${col.value}`, { preserveScroll: true });
}

const statusOrder = computed(() => props.columns.map((c) => c.value));
const showForm = ref(false);
const canExport = computed(() => (usePage().props.auth?.user?.permissions || []).includes('exports.create'));

// Détail d'une carte (résolu par id pour se rafraîchir après chaque action).
const selectedId = ref(null);
const commentBody = ref('');
const selectedEvent = computed(() => {
    for (const col of props.columns) {
        const found = col.events.find((e) => e.id === selectedId.value);
        if (found) return found;
    }
    return null;
});

function openDetail(e) {
    selectedId.value = e.id;
    commentBody.value = '';
}
function assign(e, userId) {
    router.patch(`/events/${e.id}`, { type: e.type, title: e.title, priority: e.priority, assigned_to: userId || null }, { preserveScroll: true });
}
function setMaterialStatus(e, status) {
    router.patch(`/events/${e.id}/material-status`, { status }, { preserveScroll: true });
}
function addComment(e) {
    if (!commentBody.value.trim()) return;
    router.post(`/events/${e.id}/comments`, { body: commentBody.value }, {
        preserveScroll: true,
        onSuccess: () => (commentBody.value = ''),
    });
}

// Priorité alignée sur l'échelle d'urgence unifiée : rouge / orange / jaune.
const priorityStyles = {
    haute: 'bg-red-100 text-red-700',
    normale: 'bg-orange-100 text-orange-700',
    basse: 'bg-yellow-100 text-yellow-800',
};
const typeStyles = {
    anomalie: 'bg-red-50 text-red-700 border-red-200',
    reparation: 'bg-amber-50 text-amber-700 border-amber-200',
    autre: 'bg-gray-50 text-gray-600 border-gray-200',
};

const form = useForm({
    type: 'anomalie',
    title: '',
    description: '',
    priority: 'normale',
    vehicle_id: '',
    material_id: '',
    assigned_to: '',
});

function create() {
    form.transform((d) => ({
        ...d,
        vehicle_id: d.vehicle_id || null,
        material_id: d.material_id || null,
        assigned_to: d.assigned_to || null,
    })).post('/events', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

function move(event, dir) {
    const idx = statusOrder.value.indexOf(event.status);
    const target = statusOrder.value[idx + dir];
    if (!target) return;
    router.patch(`/events/${event.id}/move`, { status: target }, { preserveScroll: true });
}

function remove(event) {
    if (confirm(`Supprimer « ${event.title} » ?`)) {
        router.delete(`/events/${event.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Événements" />
        <template #title>Événements</template>

        <!-- Onglets -->
        <div class="mb-6 flex gap-1 border-b border-gray-200">
            <Link href="/events/tableau-de-bord" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-800">Tableau de bord</Link>
            <Link href="/events" class="border-b-2 border-[var(--brand)] px-4 py-2 text-sm font-semibold text-gray-900">Kanban</Link>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">Anomalies et réparations à suivre. Déplace les cartes selon leur avancement.</p>
            <div class="flex gap-2">
                <a v-if="canExport" href="/exports/evenements.csv" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"><Icon name="download" :size="16" /> CSV</a>
                <button class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110" @click="showForm = !showForm">
                    {{ showForm ? 'Fermer' : 'Nouvel événement' }}
                </button>
            </div>
        </div>

        <!-- Formulaire de création -->
        <div v-if="showForm" class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="create">
                <div class="md:col-span-2">
                    <InputLabel value="Titre" />
                    <TextInput v-model="form.title" placeholder="Ex. Défibrillateur HS" />
                    <InputError :message="form.errors.title" />
                </div>
                <div>
                    <InputLabel value="Type" />
                    <select v-model="form.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Priorité" />
                    <select v-model="form.priority" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option v-for="p in priorities" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Véhicule (optionnel)" />
                    <select v-model="form.vehicle_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option value="">—</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Matériel (optionnel)" />
                    <select v-model="form.material_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option value="">—</option>
                        <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Assigné à (optionnel)" />
                    <select v-model="form.assigned_to" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option value="">—</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Description (optionnel)" />
                    <textarea v-model="form.description" rows="2" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Créer l'événement</button>
                </div>
            </form>
        </div>

        <!-- Tableaux -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <button
                v-for="b in boards" :key="b.id" type="button"
                class="rounded-full px-3 py-1.5 text-sm font-medium"
                :class="b.id === current_board ? 'bg-[var(--brand)] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                @click="selectBoard(b.id)"
            >{{ b.name }}</button>
            <button v-if="can_manage_board" type="button" class="rounded-full border border-dashed border-gray-300 px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-50" @click="addBoard">+ Tableau</button>
            <button v-if="can_manage_board && boards.length > 1" type="button" class="rounded-full p-1.5 text-gray-400 hover:text-red-600" title="Supprimer ce tableau" @click="removeBoard"><Icon name="x" :size="14" /></button>
        </div>

        <!-- Kanban -->
        <div class="flex gap-4 overflow-x-auto pb-4">
            <section v-for="col in columns" :key="col.value" class="flex w-72 shrink-0 flex-col rounded-2xl bg-gray-100/70 p-3">
                <div class="mb-2 flex items-center justify-between px-1">
                    <h2 class="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
                        {{ col.label }}
                        <Icon v-if="col.is_done" name="check" :size="13" class="text-green-600" title="Colonne terminée" />
                    </h2>
                    <div class="flex items-center gap-1">
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500">{{ col.events.length }}</span>
                        <template v-if="can_manage_board">
                            <button class="rounded p-0.5 text-gray-300 hover:text-gray-700" title="Marquer terminée / active" @click="toggleDone(col)"><Icon name="check" :size="13" /></button>
                            <button class="rounded p-0.5 text-gray-300 hover:text-gray-700" title="Renommer" @click="renameColumn(col)"><Icon name="settings" :size="13" /></button>
                            <button class="rounded p-0.5 text-gray-300 hover:text-red-600" title="Supprimer" @click="removeColumn(col)"><Icon name="x" :size="13" /></button>
                        </template>
                    </div>
                </div>

                <div class="space-y-2">
                    <article v-for="e in col.events" :key="e.id" class="cursor-pointer rounded-xl border border-gray-200 bg-white p-3 shadow-sm hover:border-[var(--brand)]/40" @click="openDetail(e)">
                        <div class="flex items-start justify-between gap-2">
                            <span class="rounded border px-1.5 py-0.5 text-[10px] font-medium uppercase" :class="typeStyles[e.type]">{{ e.type_label }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="priorityStyles[e.priority]">{{ e.priority }}</span>
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-gray-900">{{ e.title }}</p>
                        <p v-if="e.description" class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ e.description }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-gray-400">
                            <span v-if="e.vehicle" class="inline-flex items-center gap-1"><Icon name="vehicle" :size="14" /> {{ e.vehicle }}</span>
                            <span v-if="e.material" class="inline-flex items-center gap-1"><Icon name="materials" :size="14" /> {{ e.material }}</span>
                            <span v-if="e.assignee" class="inline-flex items-center gap-1"><Icon name="user" :size="14" /> {{ e.assignee }}</span>
                            <span v-if="e.comments && e.comments.length" class="inline-flex items-center gap-1"><Icon name="message" :size="14" /> {{ e.comments.length }}</span>
                        </div>

                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex gap-1">
                                <button
                                    class="inline-flex items-center justify-center rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-500 hover:bg-gray-50 disabled:opacity-30"
                                    :disabled="statusOrder.indexOf(e.status) === 0"
                                    title="Reculer"
                                    @click.stop="move(e, -1)"
                                ><Icon name="arrow-left" :size="14" /></button>
                                <button
                                    class="inline-flex items-center justify-center rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-500 hover:bg-gray-50 disabled:opacity-30"
                                    :disabled="statusOrder.indexOf(e.status) === statusOrder.length - 1"
                                    title="Avancer"
                                    @click.stop="move(e, 1)"
                                ><Icon name="arrow-right" :size="14" /></button>
                            </div>
                            <button class="text-xs text-red-500 hover:underline" @click.stop="remove(e)">Suppr.</button>
                        </div>
                    </article>

                    <p v-if="col.events.length === 0" class="rounded-lg border border-dashed border-gray-300 py-6 text-center text-xs text-gray-400">Aucun</p>
                </div>
            </section>

            <button v-if="can_manage_board" type="button" class="flex h-12 w-56 shrink-0 items-center justify-center gap-1.5 self-start rounded-2xl border-2 border-dashed border-gray-300 text-sm font-medium text-gray-500 hover:bg-gray-50" @click="addColumn">
                <Icon name="plus" :size="16" /> Ajouter une colonne
            </button>
        </div>

        <!-- Détail d'un événement -->
        <div v-if="selectedEvent" class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center" @click.self="selectedId = null">
            <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-start justify-between border-b border-gray-100 p-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded border px-1.5 py-0.5 text-[10px] font-medium uppercase" :class="typeStyles[selectedEvent.type]">{{ selectedEvent.type_label }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="priorityStyles[selectedEvent.priority]">{{ selectedEvent.priority }}</span>
                        </div>
                        <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ selectedEvent.title }}</h2>
                        <p class="text-xs text-gray-400">Créé le {{ selectedEvent.created_at }}</p>
                    </div>
                    <button class="inline-flex items-center text-gray-400 hover:text-gray-600" @click="selectedId = null"><Icon name="x" :size="16" /></button>
                </div>

                <div class="space-y-5 overflow-y-auto p-5">
                    <p v-if="selectedEvent.description" class="whitespace-pre-line text-sm text-gray-600">{{ selectedEvent.description }}</p>

                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                        <span v-if="selectedEvent.vehicle" class="inline-flex items-center gap-1"><Icon name="vehicle" :size="14" /> {{ selectedEvent.vehicle }}</span>
                        <span v-if="selectedEvent.material" class="inline-flex items-center gap-1"><Icon name="materials" :size="14" /> {{ selectedEvent.material }}</span>
                    </div>

                    <!-- Assignation -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Assigné à</label>
                        <select :value="selectedEvent.assigned_to ?? ''" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" @change="assign(selectedEvent, $event.target.value)">
                            <option value="">Personne</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>

                    <!-- Statut du matériel rattaché (réparation) -->
                    <div v-if="selectedEvent.material_id">
                        <label class="block text-xs font-medium text-gray-600">Statut du matériel « {{ selectedEvent.material }} »
                            <span v-if="selectedEvent.material_status_label" class="text-gray-400">— actuel : {{ selectedEvent.material_status_label }}</span>
                        </label>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <button
                                v-for="s in materialStatuses"
                                :key="s.value"
                                type="button"
                                class="rounded-full border px-3 py-1 text-xs font-medium transition"
                                :class="selectedEvent.material_status === s.value ? 'border-[var(--brand)] bg-[var(--brand)]/10 text-[var(--brand)]' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="setMaterialStatus(selectedEvent, s.value)"
                            >{{ s.label }}</button>
                        </div>
                    </div>

                    <!-- Fil de commentaires -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Suivi ({{ selectedEvent.comments.length }})</label>
                        <div class="mt-2 space-y-2">
                            <div v-for="c in selectedEvent.comments" :key="c.id" class="rounded-lg bg-gray-50 p-2.5">
                                <p class="text-sm text-gray-700">{{ c.body }}</p>
                                <p class="mt-0.5 text-[11px] text-gray-400">{{ c.author ?? '—' }} · {{ c.at }}</p>
                            </div>
                            <p v-if="selectedEvent.comments.length === 0" class="text-xs text-gray-400">Aucun commentaire.</p>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <input v-model="commentBody" type="text" placeholder="Ajouter un suivi…" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" @keyup.enter="addComment(selectedEvent)" />
                            <button class="rounded-lg bg-[var(--brand)] px-3 py-2 text-sm font-semibold text-white hover:brightness-110" @click="addComment(selectedEvent)">Envoyer</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
