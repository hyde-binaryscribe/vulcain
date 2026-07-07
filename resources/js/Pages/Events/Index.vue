<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    columns: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const statusOrder = computed(() => props.columns.map((c) => c.value));
const showForm = ref(false);

const priorityStyles = {
    haute: 'bg-red-100 text-red-700',
    normale: 'bg-gray-100 text-gray-600',
    basse: 'bg-blue-100 text-blue-700',
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

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">Anomalies et réparations à suivre. Déplace les cartes selon leur avancement.</p>
            <button class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110" @click="showForm = !showForm">
                {{ showForm ? 'Fermer' : 'Nouvel événement' }}
            </button>
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

        <!-- Kanban -->
        <div class="flex gap-4 overflow-x-auto pb-4">
            <section v-for="col in columns" :key="col.value" class="flex w-72 shrink-0 flex-col rounded-2xl bg-gray-100/70 p-3">
                <div class="mb-2 flex items-center justify-between px-1">
                    <h2 class="text-sm font-semibold text-gray-700">{{ col.label }}</h2>
                    <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500">{{ col.events.length }}</span>
                </div>

                <div class="space-y-2">
                    <article v-for="e in col.events" :key="e.id" class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="rounded border px-1.5 py-0.5 text-[10px] font-medium uppercase" :class="typeStyles[e.type]">{{ e.type_label }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="priorityStyles[e.priority]">{{ e.priority }}</span>
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-gray-900">{{ e.title }}</p>
                        <p v-if="e.description" class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ e.description }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-gray-400">
                            <span v-if="e.vehicle">🚑 {{ e.vehicle }}</span>
                            <span v-if="e.material">🧰 {{ e.material }}</span>
                            <span v-if="e.assignee">👤 {{ e.assignee }}</span>
                        </div>

                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex gap-1">
                                <button
                                    class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-500 hover:bg-gray-50 disabled:opacity-30"
                                    :disabled="statusOrder.indexOf(e.status) === 0"
                                    title="Reculer"
                                    @click="move(e, -1)"
                                >←</button>
                                <button
                                    class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-500 hover:bg-gray-50 disabled:opacity-30"
                                    :disabled="statusOrder.indexOf(e.status) === statusOrder.length - 1"
                                    title="Avancer"
                                    @click="move(e, 1)"
                                >→</button>
                            </div>
                            <button class="text-xs text-red-500 hover:underline" @click="remove(e)">Suppr.</button>
                        </div>
                    </article>

                    <p v-if="col.events.length === 0" class="rounded-lg border border-dashed border-gray-300 py-6 text-center text-xs text-gray-400">Aucun</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
