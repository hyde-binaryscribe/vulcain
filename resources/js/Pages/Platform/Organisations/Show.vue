<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    org: { type: Object, required: true },
    subscription: { type: Object, default: null },
    counts: { type: Object, default: () => ({}) },
    admin: { type: Object, default: null },
    pendingInvitation: { type: String, default: null },
    activity: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash?.status);
const flashError = computed(() => page.props.flash?.error);
const credentials = computed(() => page.props.flash?.credentials);

function copyCredentials() {
    const c = credentials.value;
    if (!c) return;
    navigator.clipboard?.writeText(`Identifiant : ${c.email}\nMot de passe : ${c.password}`);
}

const metrics = computed(() => [
    { label: 'Utilisateurs', value: props.counts.users ?? 0 },
    { label: 'Véhicules', value: props.counts.vehicles ?? 0 },
    { label: 'Sites', value: props.counts.sites ?? 0 },
    { label: 'Matériel', value: props.counts.materials ?? 0 },
]);

const base = () => `/platform/organisations/${props.org.id}`;
function toggle() { router.post(`${base()}/toggle`, {}, { preserveScroll: true }); }
function resend() { router.post(`${base()}/resend-invitation`, {}, { preserveScroll: true }); }
function generateCredentials() {
    const q = props.admin
        ? `Réinitialiser le mot de passe de l'administrateur de « ${props.org.name} » ?`
        : `Créer le compte administrateur de « ${props.org.name} » et générer son mot de passe ?`;
    if (confirm(q)) {
        router.post(`${base()}/credentials`, {}, { preserveScroll: true });
    }
}
function impersonate() {
    if (confirm(`Se connecter en tant qu'administrateur de « ${props.org.name} » ? Vous quitterez le Desk.`)) {
        router.post(`${base()}/impersonate`);
    }
}
</script>

<template>
    <PlatformLayout>
        <Head :title="`Desk — ${org.name}`" />

        <Link href="/platform" class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline"><Icon name="arrow-left" :size="16" /> Tableau de bord</Link>

        <div v-if="flash" class="mt-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</div>
        <div v-if="flashError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ flashError }}</div>

        <!-- Identifiants générés : affichés une seule fois, à communiquer manuellement. -->
        <div v-if="credentials" class="mt-3 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-indigo-900">Identifiants à communiquer</p>
                    <p class="mt-0.5 text-xs text-indigo-700">{{ credentials.message }}</p>
                </div>
                <button type="button" class="shrink-0 rounded-lg border border-indigo-300 bg-white px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100" @click="copyCredentials">Copier</button>
            </div>
            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                <div class="rounded-lg bg-white px-3 py-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Identifiant</dt>
                    <dd class="mt-0.5 select-all font-mono text-sm text-slate-900">{{ credentials.email }}</dd>
                </div>
                <div class="rounded-lg bg-white px-3 py-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Mot de passe</dt>
                    <dd class="mt-0.5 select-all font-mono text-sm text-slate-900">{{ credentials.password }}</dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-indigo-500">Ce mot de passe n'est affiché qu'une fois : notez-le avant de quitter la page.</p>
        </div>

        <!-- En-tête -->
        <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: org.theme }"></span>
                        <h1 class="font-display text-xl font-extrabold text-slate-900">{{ org.name }}</h1>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="org.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                            {{ org.status === 'active' ? 'Active' : 'Suspendue' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ org.sector_label }} · <span class="font-mono">{{ org.slug }}</span>
                        <span v-if="org.group"> · groupe {{ org.group }}</span> · créée le {{ org.created_at }}
                    </p>
                </div>
                <a :href="org.app_url" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Ouvrir l'espace <Icon name="external" :size="16" /></a>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="m in metrics" :key="m.label" class="rounded-xl bg-slate-50 p-4">
                    <p class="font-display text-2xl font-extrabold text-slate-900" style="font-variant-numeric:tabular-nums">{{ m.value }}</p>
                    <p class="text-xs text-slate-500">{{ m.label }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <!-- Abonnement -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Abonnement</h2>
                    <Link :href="`${base()}/subscription`" class="text-xs text-indigo-600 hover:underline">Gérer</Link>
                </div>
                <p v-if="subscription" class="mt-3">
                    <span class="font-display text-lg font-bold text-slate-900">{{ subscription.plan_label }}</span>
                    <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ subscription.status_label }}</span>
                </p>
                <p v-else class="mt-3 text-sm text-slate-500">Aucun abonnement.</p>
                <dl v-if="subscription" class="mt-3 space-y-1 text-sm text-slate-600">
                    <div v-if="subscription.trial_ends_at" class="flex justify-between"><dt>Fin d'essai</dt><dd>{{ subscription.trial_ends_at }}</dd></div>
                    <div v-if="subscription.current_period_end" class="flex justify-between"><dt>Prochaine échéance</dt><dd>{{ subscription.current_period_end }}</dd></div>
                </dl>
            </div>

            <!-- Administrateur -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Administrateur</h2>
                <template v-if="admin">
                    <p class="mt-3 font-medium text-slate-900">{{ admin.name }}</p>
                    <p class="text-sm text-slate-500">{{ admin.email }}</p>
                    <p class="mt-1 text-xs text-slate-400">Dernière connexion : {{ admin.last_login ?? 'jamais' }}</p>
                </template>
                <p v-else-if="pendingInvitation" class="mt-3 text-sm text-amber-700">Invitation en attente : {{ pendingInvitation }}</p>
                <p v-else class="mt-3 text-sm text-slate-500">Aucun administrateur.</p>
            </div>

            <!-- Support -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Support</h2>
                <div class="mt-3 flex flex-col gap-2">
                    <button v-if="admin" type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="impersonate">Se connecter en tant qu'admin</button>
                    <button v-if="admin || pendingInvitation" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="generateCredentials">
                        {{ admin ? 'Réinitialiser le mot de passe admin' : 'Générer les identifiants admin' }}
                    </button>
                    <button v-if="pendingInvitation" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="resend">Renvoyer l'invitation (e-mail)</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="toggle">{{ org.status === 'active' ? 'Suspendre l\'organisation' : 'Réactiver l\'organisation' }}</button>
                </div>
            </div>
        </div>

        <!-- Activité récente -->
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Activité récente</h2>
            <ul class="mt-3 divide-y divide-slate-100">
                <li v-for="(a, i) in activity" :key="i" class="flex items-center justify-between gap-3 py-2 text-sm">
                    <span class="text-slate-700">
                        <span class="font-medium">{{ a.action }}</span>
                        <span class="text-slate-500"> · {{ a.subject_type }}</span>
                        <span v-if="a.description" class="text-slate-500"> — {{ a.description }}</span>
                        <span v-if="a.actor" class="text-slate-400"> ({{ a.actor }})</span>
                    </span>
                    <span class="whitespace-nowrap text-xs text-slate-400">{{ a.at }}</span>
                </li>
                <li v-if="activity.length === 0" class="py-4 text-center text-sm text-slate-500">Aucune activité enregistrée.</li>
            </ul>
        </div>
    </PlatformLayout>
</template>
