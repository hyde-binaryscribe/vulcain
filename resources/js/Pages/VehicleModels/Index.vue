<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Icon from '@/Components/Icon.vue';
import VehicleModelNode from '@/Components/VehicleModelNode.vue';

const props = defineProps({
    models: { type: Array, default: () => [] },
    kinds: { type: Array, default: () => [] },
});

const editingId = ref(null);
const form = useForm({ name: '', display_order: 0, template: [] });

function resetForm() {
    editingId.value = null;
    form.clearErrors();
    form.name = '';
    form.display_order = 0;
    form.template = [];
}

// Clone profond simple (le gabarit ne contient que des objets/tableaux/chaînes).
function cloneTree(nodes) {
    return (nodes || []).map((n) => ({ name: n.name, kind: n.kind, children: cloneTree(n.children) }));
}

function edit(m) {
    editingId.value = m.id;
    form.clearErrors();
    form.name = m.name;
    form.display_order = m.display_order;
    form.template = cloneTree(m.template);
}
function addEmplacement() {
    form.template.push({ name: '', kind: 'mobile', children: [] });
}
function removeEmplacement(i) {
    form.template.splice(i, 1);
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => resetForm() };
    if (editingId.value) form.patch(`/vehicle-models/${editingId.value}`, opts);
    else form.post('/vehicle-models', opts);
}
function toggle(m) {
    router.post(`/vehicle-models/${m.id}/toggle`, {}, { preserveScroll: true });
}
function remove(m) {
    const warn = m.vehicles_count > 0
        ? `Supprimer le modèle « ${m.name} » ? ${m.vehicles_count} véhicule(s) ont été créés dessus (leurs emplacements sont conservés).`
        : `Supprimer le modèle « ${m.name} » ?`;
    if (confirm(warn)) router.delete(`/vehicle-models/${m.id}`, { preserveScroll: true });
}

function kindLabel(value) {
    return props.kinds.find((k) => k.value === value)?.label ?? value;
}
</script>

<template>
    <AppLayout>
        <Head title="Modèles de véhicule" />
        <template #title>Modèles de véhicule</template>

        <p class="mb-4 max-w-2xl text-sm text-gray-500">
            Configurez une fois le gabarit d'emplacements d'un modèle. À la création d'un véhicule sur ce modèle,
            ses emplacements (et sous-emplacements) sont <span class="font-medium text-gray-700">générés automatiquement</span>.
        </p>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="space-y-4 lg:col-span-2">
                <div v-for="m in models" :key="m.id" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" :class="m.is_active ? '' : 'opacity-70'">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                                <Icon name="vehicle" :size="17" class="text-gray-400" />
                                {{ m.name }}
                                <span v-if="!m.is_active" class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">Inactif</span>
                            </h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ m.emplacements_count }} emplacement(s) · {{ m.vehicles_count }} véhicule(s) créé(s)
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="edit(m)">Modifier</button>
                            <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="toggle(m)">Activer/désactiver</button>
                            <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(m)">Suppr.</button>
                        </div>
                    </div>

                    <ul v-if="m.template.length" class="mt-3 flex flex-wrap gap-1.5">
                        <li v-for="(n, i) in m.template" :key="i" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">
                            <span class="font-medium">{{ n.name }}</span>
                            <span class="text-gray-400">· {{ kindLabel(n.kind) }}</span>
                            <span v-if="n.children && n.children.length" class="text-gray-400">· {{ n.children.length }} sous-empl.</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-xs text-gray-400">Aucun emplacement dans ce gabarit.</p>
                </div>

                <div v-if="models.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                    Aucun modèle. Créez-en un pour générer automatiquement les emplacements de vos véhicules.
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier le modèle' : 'Nouveau modèle' }}</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Nom du modèle" />
                        <TextInput v-model="form.name" placeholder="Renault Master ASSU, VSL Trafic…" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Ordre d’affichage" />
                        <TextInput v-model="form.display_order" type="number" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel value="Gabarit d’emplacements" />
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-[var(--brand)] hover:brightness-110" @click="addEmplacement">
                                <Icon name="plus" :size="13" /> Emplacement
                            </button>
                        </div>
                        <p class="mb-2 mt-1 text-xs text-gray-500">
                            Ajoutez les emplacements du véhicule, avec sous-emplacements si besoin (ex. un sac contenant des pochettes).
                        </p>
                        <div v-if="form.template.length" class="space-y-2">
                            <VehicleModelNode
                                v-for="(node, i) in form.template"
                                :key="i"
                                :node="node"
                                :kinds="kinds"
                                @remove="removeEmplacement(i)"
                            />
                        </div>
                        <p v-else class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-400">
                            Aucun emplacement. Cliquez sur « Emplacement » pour commencer.
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">{{ editingId ? 'Enregistrer' : 'Créer' }}</button>
                        <button v-if="editingId" type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm" @click="resetForm">Annuler</button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
