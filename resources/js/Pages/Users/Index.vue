<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    pendingInvitations: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    sites: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const searchTerm = ref(props.search);
const siteWordPlural = computed(() => usePage().props.tenant?.profile?.site_label_plural || 'sites');

function runSearch() {
    router.get('/users', { q: searchTerm.value }, { preserveState: true, replace: true });
}

// Invitation
const inviteForm = useForm({ email: '', role: props.roles[0]?.value ?? '' });
function invite() {
    inviteForm.post('/users/invite', { preserveScroll: true, onSuccess: () => inviteForm.reset('email') });
}

// Édition
const editing = ref(null);
const editForm = useForm({ grade: '', role: '', is_active: true, site_ids: [] });

function openEdit(user) {
    editing.value = user;
    editForm.clearErrors();
    editForm.grade = user.grade ?? '';
    editForm.role = user.role ?? props.roles[0]?.value;
    editForm.is_active = user.is_active;
    editForm.site_ids = [...(user.site_ids ?? [])];
}
function toggleSite(id) {
    const i = editForm.site_ids.indexOf(id);
    if (i === -1) editForm.site_ids.push(id);
    else editForm.site_ids.splice(i, 1);
}
function saveEdit() {
    editForm.patch(`/users/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

function roleLabel(value) {
    return props.roles.find((r) => r.value === value)?.label ?? value;
}

function resend(inv) {
    router.post(`/invitations/${inv.id}/resend`, {}, { preserveScroll: true });
}
function cancel(inv) {
    router.delete(`/invitations/${inv.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Utilisateurs" />
        <template #title>Utilisateurs</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Liste + recherche -->
            <section class="lg:col-span-2">
                <div class="mb-3 flex items-center gap-2">
                    <TextInput v-model="searchTerm" placeholder="Rechercher un nom ou e-mail…" @keyup.enter="runSearch" />
                    <button class="rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white" @click="runSearch">Rechercher</button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Nom</th>
                                <th class="px-4 py-3">Rôle</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3">Dernière connexion</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="u in users" :key="u.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ u.name }}</div>
                                    <div class="text-xs text-gray-500">{{ u.email }}<span v-if="u.grade"> · {{ u.grade }}</span></div>
                                </td>
                                <td class="px-4 py-3">{{ roleLabel(u.role) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="u.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'">
                                        {{ u.is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ u.last_login_at ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="openEdit(u)">Modifier</button>
                                </td>
                            </tr>
                            <tr v-if="users.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun utilisateur.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Inviter + invitations en attente -->
            <section class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Inviter un utilisateur</h2>
                    <form class="mt-3 space-y-3" @submit.prevent="invite">
                        <div>
                            <InputLabel value="Adresse e-mail" />
                            <TextInput v-model="inviteForm.email" type="email" />
                            <InputError :message="inviteForm.errors.email" />
                        </div>
                        <div>
                            <InputLabel value="Rôle" />
                            <select v-model="inviteForm.role" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                            <InputError :message="inviteForm.errors.role" />
                        </div>
                        <button type="submit" :disabled="inviteForm.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">
                            Envoyer l’invitation
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Invitations en attente</h2>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <li v-for="inv in pendingInvitations" :key="inv.id" class="py-3">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-gray-800">{{ inv.email }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ inv.role_label }} ·
                                        <span :class="inv.expired ? 'text-red-600' : ''">{{ inv.expired ? 'expirée' : 'expire le ' + inv.expires_at }}</span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="resend(inv)">Renvoyer</button>
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="cancel(inv)">Annuler</button>
                                </div>
                            </div>
                        </li>
                        <li v-if="pendingInvitations.length === 0" class="py-3 text-sm text-gray-500">Aucune invitation en attente.</li>
                    </ul>
                </div>
            </section>
        </div>

        <!-- Modal édition -->
        <div v-if="editing" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="editing = null">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Modifier {{ editing.name }}</h3>
                <form class="mt-4 space-y-4" @submit.prevent="saveEdit">
                    <div>
                        <InputLabel value="Grade" />
                        <TextInput v-model="editForm.grade" />
                    </div>
                    <div>
                        <InputLabel value="Rôle" />
                        <select v-model="editForm.role" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <InputError :message="editForm.errors.role" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300" />
                        Compte actif
                    </label>
                    <div v-if="sites.length">
                        <InputLabel :value="`Périmètre (${siteWordPlural.toLowerCase()})`" />
                        <p class="mb-1 text-xs text-gray-400">Aucun coché = accès à {{ siteWordPlural.toLowerCase() }}.</p>
                        <div class="space-y-1">
                            <label v-for="s in sites" :key="s.id" class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300" :checked="editForm.site_ids.includes(s.id)" @change="toggleSite(s.id)" />
                                {{ s.name }}
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm" @click="editing = null">Annuler</button>
                        <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
