<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    organisations: { type: Array, default: () => [] },
});

const groupForm = useForm({ name: '' });
function createGroup() {
    groupForm.post('/platform/groups', { preserveScroll: true, onSuccess: () => groupForm.reset() });
}

function assign(org, groupId) {
    router.patch(`/platform/organisations/${org.id}/group`, { group_id: groupId || null }, { preserveScroll: true });
}

// Création d'un gestionnaire de groupe.
const managerFor = ref(null);
const managerForm = useForm({ name: '', email: '', password: '', password_confirmation: '' });
function openManager(group) {
    managerFor.value = group;
    managerForm.reset();
    managerForm.clearErrors();
}
function createManager() {
    managerForm.post(`/platform/groups/${managerFor.value.id}/managers`, {
        preserveScroll: true,
        onSuccess: () => { managerFor.value = null; },
    });
}
</script>

<template>
    <PlatformLayout>
        <Head title="Desk — Groupes" />

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Groupes (entreprises)</h1>
                <p class="text-sm text-slate-500">Regroupe des organisations et délègue leur gestion.</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Groupes -->
            <section class="space-y-4 lg:col-span-2">
                <div v-for="g in groups" :key="g.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">{{ g.name }}</h2>
                            <p class="text-xs text-slate-500">{{ g.organisations_count }} organisation(s)</p>
                        </div>
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="openManager(g)">+ Gestionnaire</button>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span v-for="name in g.organisations" :key="name" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ name }}</span>
                        <span v-if="g.organisations.length === 0" class="text-xs text-slate-400">Aucune organisation rattachée.</span>
                    </div>
                    <div v-if="g.managers.length" class="mt-3 border-t border-slate-100 pt-2 text-xs text-slate-500">
                        Gestionnaires : <span v-for="(m, i) in g.managers" :key="m.id">{{ m.email }}<span v-if="i < g.managers.length - 1">, </span></span>
                    </div>
                </div>
                <p v-if="groups.length === 0" class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500">Aucun groupe. Créez-en un →</p>
            </section>

            <!-- Nouveau groupe + rattachement -->
            <section class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Nouveau groupe</h2>
                    <form class="mt-3 flex gap-2" @submit.prevent="createGroup">
                        <input v-model="groupForm.name" placeholder="Nom du groupe" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Créer</button>
                    </form>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Rattachement des organisations</h2>
                    <ul class="mt-3 space-y-2">
                        <li v-for="o in organisations" :key="o.id" class="flex items-center justify-between gap-2">
                            <span class="text-sm text-slate-700">{{ o.name }}</span>
                            <select :value="o.group_id ?? ''" class="rounded-lg border border-slate-300 px-2 py-1 text-xs" @change="assign(o, $event.target.value)">
                                <option value="">— Aucun —</option>
                                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <!-- Modale : créer un gestionnaire -->
        <div v-if="managerFor" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="managerFor = null">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-slate-900">Gestionnaire — {{ managerFor.name }}</h2>
                <p class="mt-1 text-sm text-slate-500">Accès Desk limité aux organisations de ce groupe.</p>
                <form class="mt-4 space-y-3" @submit.prevent="createManager">
                    <div>
                        <input v-model="managerForm.name" placeholder="Nom" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="managerForm.errors.name" class="mt-1 text-xs text-red-600">{{ managerForm.errors.name }}</p>
                    </div>
                    <div>
                        <input v-model="managerForm.email" type="email" placeholder="E-mail" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="managerForm.errors.email" class="mt-1 text-xs text-red-600">{{ managerForm.errors.email }}</p>
                    </div>
                    <div>
                        <input v-model="managerForm.password" type="password" placeholder="Mot de passe (10+ car., lettres + chiffres)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="managerForm.errors.password" class="mt-1 text-xs text-red-600">{{ managerForm.errors.password }}</p>
                    </div>
                    <input v-model="managerForm.password_confirmation" type="password" placeholder="Confirmer le mot de passe" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="managerFor = null">Annuler</button>
                        <button type="submit" :disabled="managerForm.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </PlatformLayout>
</template>
