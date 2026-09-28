<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const q = ref(props.search);
function runSearch() {
    router.get('/platform/users', { q: q.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <PlatformLayout>
        <Head title="Desk — Utilisateurs" />

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-xl font-extrabold text-slate-900">Utilisateurs</h1>
                <p class="text-sm text-slate-500">Recherche transverse à toutes les organisations ({{ users.length }} affichés, 200 max).</p>
            </div>
            <div class="flex gap-2">
                <input v-model="q" type="search" placeholder="Nom ou e-mail…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @keyup.enter="runSearch" />
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="runSearch">Rechercher</button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Utilisateur</th>
                        <th class="px-4 py-3">Organisation</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3">Dernière connexion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ u.name }}<span v-if="u.grade" class="text-slate-400"> · {{ u.grade }}</span></div>
                            <div class="text-xs text-slate-400">{{ u.email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <Link :href="`/platform/organisations/${u.org_id}`" class="text-slate-700 hover:text-indigo-600 hover:underline">{{ u.org }}</Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="u.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'">{{ u.is_active ? 'Actif' : 'Inactif' }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ u.last_login ?? 'jamais' }}</td>
                    </tr>
                    <tr v-if="users.length === 0"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Aucun utilisateur trouvé.</td></tr>
                </tbody>
            </table>
        </div>
    </PlatformLayout>
</template>
