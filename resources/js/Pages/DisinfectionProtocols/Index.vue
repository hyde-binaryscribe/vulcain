<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    protocols: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const blank = { name: '', type: props.types[1]?.value ?? props.types[0]?.value ?? 'desinfection', cadence: '', frequency_days: null, procedure: '', display_order: 0, is_active: true };
const form = useForm({ ...blank });
const editingId = ref(null);

function resetForm() {
    editingId.value = null;
    form.clearErrors();
    Object.assign(form, blank);
}
function edit(p) {
    editingId.value = p.id;
    form.clearErrors();
    Object.assign(form, {
        name: p.name, type: p.type, cadence: p.cadence ?? '', frequency_days: p.frequency_days,
        procedure: p.procedure ?? '', display_order: p.display_order, is_active: p.is_active,
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => resetForm() };
    if (editingId.value) form.patch(`/disinfection-protocols/${editingId.value}`, opts);
    else form.post('/disinfection-protocols', opts);
}
function toggle(p) {
    router.post(`/disinfection-protocols/${p.id}/toggle`, {}, { preserveScroll: true });
}
function remove(p) {
    if (confirm(`Supprimer le protocole « ${p.name} » ?`)) router.delete(`/disinfection-protocols/${p.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Protocoles de désinfection" />
        <template #title>Protocoles de désinfection</template>

        <p class="mb-4 max-w-3xl text-sm text-gray-500">
            Bibliothèque pré-remplie selon les niveaux recommandés pour le transport sanitaire (cadre ARS).
            Ce sont des <span class="font-medium text-gray-700">modèles à adapter</span> à votre protocole
            d'établissement (produits, temps de contact, EPI). Les protocoles actifs sont proposés lors de
            l'enregistrement d'une désinfection sur la fiche véhicule.
        </p>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="space-y-4 lg:col-span-2">
                <article v-for="p in protocols" :key="p.id" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" :class="p.is_active ? '' : 'opacity-60'">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-gray-900">{{ p.name }}</h2>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ p.type_label }}</span>
                                <span v-if="!p.is_active" class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600">Inactif</span>
                            </div>
                            <p v-if="p.cadence" class="mt-1 text-xs text-gray-500">
                                Cadence : {{ p.cadence }}<span v-if="p.frequency_days"> · {{ p.frequency_days }} j</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="edit(p)">Modifier</button>
                            <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="toggle(p)">{{ p.is_active ? 'Désactiver' : 'Activer' }}</button>
                            <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(p)">Suppr.</button>
                        </div>
                    </div>
                    <ol v-if="p.procedure" class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-700">
                        <li v-for="(step, i) in p.procedure.split('\n').filter(Boolean)" :key="i">{{ step }}</li>
                    </ol>
                </article>

                <p v-if="protocols.length === 0" class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                    Aucun protocole. Créez-en un avec le formulaire.
                </p>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:sticky lg:top-20 lg:self-start">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier le protocole' : 'Nouveau protocole' }}</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Nom" />
                        <TextInput v-model="form.name" placeholder="Ex. Désinfection renforcée" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Niveau" />
                        <select v-model="form.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Cadence" />
                            <TextInput v-model="form.cadence" placeholder="Quotidien…" />
                        </div>
                        <div>
                            <InputLabel value="Périodicité (jours)" />
                            <TextInput v-model="form.frequency_days" type="number" min="1" placeholder="7 — vide = à l’usage" />
                            <InputError :message="form.errors.frequency_days" />
                        </div>
                    </div>
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                        La périodicité détermine l’échéance de désinfection des véhicules auxquels ce protocole est affecté. Laissez vide pour une procédure à réaliser à l’usage (sans échéance datée).
                    </p>
                    <div>
                        <InputLabel value="Procédure (une étape par ligne)" />
                        <textarea v-model="form.procedure" rows="7" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Aérer la cellule…&#10;Mettre des gants…"></textarea>
                        <InputError :message="form.errors.procedure" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" /> Actif
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">{{ editingId ? 'Enregistrer' : 'Créer' }}</button>
                        <button v-if="editingId" type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm" @click="resetForm">Annuler</button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
