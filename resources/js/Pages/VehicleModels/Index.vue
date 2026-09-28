<script setup>
import { computed, ref } from 'vue';
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
    maintenanceTypes: { type: Array, default: () => [] },
});

const editingId = ref(null);
const form = useForm({ brand: '', model: '', year: '', coachbuilder: '', display_order: 0, template: [], motorizations: [] });

function resetForm() {
    editingId.value = null;
    form.clearErrors();
    form.brand = '';
    form.model = '';
    form.year = '';
    form.coachbuilder = '';
    form.display_order = 0;
    form.template = [];
    form.motorizations = [];
}

// Clone profond simple (le gabarit ne contient que des objets/tableaux/chaînes).
function cloneTree(nodes) {
    return (nodes || []).map((n) => ({ name: n.name, kind: n.kind, children: cloneTree(n.children) }));
}
function cloneMotorizations(list) {
    return (list || []).map((mo) => ({
        name: mo.name ?? '',
        fuel: mo.fuel ?? '',
        plans: (mo.plans || []).map((p) => ({
            type: p.type,
            label: p.label ?? '',
            interval_km: p.interval_km ?? '',
            interval_months: p.interval_months ?? '',
        })),
    }));
}

function edit(m) {
    editingId.value = m.id;
    form.clearErrors();
    form.brand = m.brand ?? '';
    form.model = m.model ?? '';
    form.year = m.year ?? '';
    form.coachbuilder = m.coachbuilder ?? '';
    form.display_order = m.display_order;
    form.template = cloneTree(m.template);
    form.motorizations = cloneMotorizations(m.motorizations);
}

const defaultType = () => props.maintenanceTypes[0]?.value ?? 'revision';
function addMotorization() {
    form.motorizations.push({ name: '', fuel: '', plans: [] });
}
function removeMotorization(i) {
    form.motorizations.splice(i, 1);
}
function addPlan(mo) {
    mo.plans.push({ type: defaultType(), label: '', interval_km: '', interval_months: '' });
}
function removePlan(mo, i) {
    mo.plans.splice(i, 1);
}

// Aperçu du libellé composé (identique au serveur).
const previewName = computed(() => {
    const head = [form.brand, form.model, form.year].map((v) => String(v ?? '').trim()).filter(Boolean).join(' ');
    const cb = String(form.coachbuilder ?? '').trim();
    return cb ? (head ? `${head} · ${cb}` : cb) : head;
});
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
                            <p class="mt-1 flex flex-wrap gap-1.5 text-xs">
                                <span v-if="m.brand" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">Marque : <span class="font-medium text-gray-800">{{ m.brand }}</span></span>
                                <span v-if="m.model" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">Modèle : <span class="font-medium text-gray-800">{{ m.model }}</span></span>
                                <span v-if="m.year" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">Année : <span class="font-medium text-gray-800">{{ m.year }}</span></span>
                                <span v-if="m.coachbuilder" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">Carrossier : <span class="font-medium text-gray-800">{{ m.coachbuilder }}</span></span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
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

                    <ul v-if="m.motorizations && m.motorizations.length" class="mt-2 flex flex-wrap gap-1.5">
                        <li v-for="(mo, i) in m.motorizations" :key="i" class="inline-flex items-center gap-1 rounded-full border border-gray-200 px-2.5 py-1 text-xs text-gray-700">
                            <Icon name="settings" :size="12" class="text-gray-400" />
                            <span class="font-medium">{{ mo.name }}</span>
                            <span v-if="mo.fuel" class="text-gray-400">· {{ mo.fuel }}</span>
                            <span v-if="mo.plans && mo.plans.length" class="text-gray-400">· {{ mo.plans.length }} plan(s)</span>
                        </li>
                    </ul>
                </div>

                <div v-if="models.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                    Aucun modèle. Créez-en un pour générer automatiquement les emplacements de vos véhicules.
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">{{ editingId ? 'Modifier le modèle' : 'Nouveau modèle' }}</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Marque" />
                            <TextInput v-model="form.brand" placeholder="Renault" />
                            <InputError :message="form.errors.brand" />
                        </div>
                        <div>
                            <InputLabel value="Modèle" />
                            <TextInput v-model="form.model" placeholder="Master" />
                            <InputError :message="form.errors.model" />
                        </div>
                        <div>
                            <InputLabel value="Année" />
                            <TextInput v-model="form.year" type="number" min="1950" placeholder="2023" />
                            <InputError :message="form.errors.year" />
                        </div>
                        <div>
                            <InputLabel value="Carrossier" />
                            <TextInput v-model="form.coachbuilder" placeholder="Gruau, Petit…" />
                            <InputError :message="form.errors.coachbuilder" />
                        </div>
                    </div>
                    <p v-if="previewName" class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                        Libellé : <span class="font-medium text-gray-700">{{ previewName }}</span>
                    </p>
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

                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel value="Motorisations & entretien" />
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-[var(--brand)] hover:brightness-110" @click="addMotorization">
                                <Icon name="plus" :size="13" /> Motorisation
                            </button>
                        </div>
                        <p class="mb-2 mt-1 text-xs text-gray-500">
                            Une ou plusieurs motorisations, chacune avec ses plans d'entretien (par kilométrage et/ou durée).
                            À la création d'un véhicule, ces plans génèrent ses échéances d'entretien.
                        </p>

                        <div v-if="form.motorizations.length" class="space-y-3">
                            <div v-for="(mo, mi) in form.motorizations" :key="mi" class="rounded-xl border border-gray-200 bg-gray-50/60 p-3">
                                <div class="flex items-center gap-2">
                                    <input v-model="mo.name" type="text" placeholder="Motorisation (ex. 2.3 dCi 145 ch)" class="min-w-0 flex-1 rounded-md border-gray-300 px-2 py-1.5 text-sm" />
                                    <input v-model="mo.fuel" type="text" placeholder="Énergie" class="w-24 rounded-md border-gray-300 px-2 py-1.5 text-sm" />
                                    <button type="button" class="shrink-0 rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Supprimer" @click="removeMotorization(mi)">
                                        <Icon name="x" :size="15" />
                                    </button>
                                </div>

                                <div class="mt-2 border-l-2 border-gray-200 pl-3">
                                    <div v-for="(p, pi) in mo.plans" :key="pi" class="mb-2 grid grid-cols-12 items-center gap-1.5">
                                        <select v-model="p.type" class="col-span-4 rounded-md border-gray-300 px-1.5 py-1.5 text-xs">
                                            <option v-for="t in maintenanceTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                        </select>
                                        <input v-model="p.interval_km" type="number" min="1" placeholder="km" class="col-span-3 rounded-md border-gray-300 px-1.5 py-1.5 text-xs" title="Périodicité en km" />
                                        <input v-model="p.interval_months" type="number" min="1" placeholder="mois" class="col-span-3 rounded-md border-gray-300 px-1.5 py-1.5 text-xs" title="Périodicité en mois" />
                                        <button type="button" class="col-span-2 rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Supprimer" @click="removePlan(mo, pi)">
                                            <Icon name="x" :size="14" />
                                        </button>
                                        <input v-model="p.label" type="text" placeholder="Libellé (optionnel, ex. courroie de distribution)" class="col-span-12 rounded-md border-gray-300 px-2 py-1.5 text-xs" />
                                    </div>
                                    <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-[var(--brand)]" @click="addPlan(mo)">
                                        <Icon name="plus" :size="12" /> Plan d'entretien
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-400">
                            Aucune motorisation. Optionnel — ajoutez-en pour générer l'entretien automatiquement.
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
