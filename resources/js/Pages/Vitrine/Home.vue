<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    registerUrl: { type: String, default: '/inscription' },
    appHomeUrl: { type: String, default: '/' },
});

const brand = '#991b1b';

const sectors = [
    { emoji: '🚒', label: 'Sapeurs-pompiers' },
    { emoji: '🚑', label: 'Ambulanciers' },
    { emoji: '⛑️', label: 'Sécurité civile' },
];

const features = [
    { icon: '✅', title: 'Protocoles sur mesure', text: 'Inventaires, vérifications et contrôles véhicule déduits automatiquement du périmètre — un outil optimisé pour les admins, simple pour les équipes.' },
    { icon: '🧰', title: 'Matériel & consommables', text: 'Catalogue Type → Modèle → Exemplaire : suivi au n° de série pour le durable, par lot et péremption pour le consommable.' },
    { icon: '🗂', title: 'Événements & réparations', text: 'Chaque anomalie relevée alimente un Kanban de gestion : assignation, commentaires, suivi jusqu’à résolution.' },
    { icon: '🏢', title: 'Multi-sites', text: 'Centres, dépôts, antennes : rattachez véhicules et emplacements à leurs sites, avec bascule et vue consolidée.' },
    { icon: '🔔', title: 'Alertes & péremptions', text: 'Stocks sous le seuil et lots proches de la péremption remontés en temps réel, sur la fiche véhicule comme au tableau de bord.' },
    { icon: '🕓', title: 'Traçabilité complète', text: 'Historique horodaté de chaque action, protocoles figés et verrouillés, exports CSV — conformité et sérénité.' },
];

function priceLabel(p) {
    return p.price === 0 ? 'Gratuit' : `${p.price} € /mois`;
}
function limit(v) {
    return v === null || v === undefined ? 'illimité' : v;
}
</script>

<template>
    <Head title="Vulcain — inventaire opérationnel des services de secours" />

    <div class="min-h-screen bg-white text-gray-900" :style="{ '--brand': brand }">
        <!-- En-tête -->
        <header class="sticky top-0 z-20 border-b border-gray-100 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <a href="#top" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[var(--brand)] font-bold text-white">V</span>
                    <span class="text-lg font-semibold tracking-tight">Vulcain</span>
                </a>
                <nav class="hidden items-center gap-8 text-sm font-medium text-gray-600 md:flex">
                    <a href="#fonctionnalites" class="hover:text-gray-900">Fonctionnalités</a>
                    <a href="#tarifs" class="hover:text-gray-900">Tarifs</a>
                    <a :href="appHomeUrl" class="hover:text-gray-900">Se connecter</a>
                </nav>
                <a :href="registerUrl" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110">
                    Essai gratuit
                </a>
            </div>
        </header>

        <!-- Hero -->
        <section id="top" class="relative overflow-hidden">
            <div class="mx-auto max-w-6xl px-6 py-20 text-center sm:py-28">
                <span class="inline-flex items-center rounded-full bg-[var(--brand)]/10 px-3 py-1 text-xs font-semibold text-[var(--brand)]">
                    Inventaire opérationnel des services de secours
                </span>
                <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">
                    Le matériel prêt à partir, à chaque départ.
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-gray-600">
                    Vulcain digitalise les inventaires, le suivi du matériel et des consommables, et la
                    gestion des anomalies de vos véhicules et de vos sites — pompiers, ambulanciers, sécurité civile.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a :href="registerUrl" class="rounded-lg bg-[var(--brand)] px-6 py-3 text-sm font-semibold text-white transition hover:brightness-110">
                        Démarrer l’essai gratuit
                    </a>
                    <a href="#fonctionnalites" class="rounded-lg border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                        Découvrir les fonctionnalités
                    </a>
                </div>

                <div class="mt-14 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-sm text-gray-500">
                    <span v-for="s in sectors" :key="s.label" class="inline-flex items-center gap-2">
                        <span class="text-lg">{{ s.emoji }}</span> {{ s.label }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Fonctionnalités -->
        <section id="fonctionnalites" class="border-t border-gray-100 bg-gray-50/60 py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-center text-3xl font-bold tracking-tight">Tout le cycle de vie de votre matériel</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-gray-600">
                    Du catalogue à la vérification terrain, jusqu’à la réparation — une seule plateforme.
                </p>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="f in features" :key="f.title" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-xl">{{ f.icon }}</div>
                        <h3 class="mt-4 text-base font-semibold">{{ f.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ f.text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Tarifs -->
        <section id="tarifs" class="py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-center text-3xl font-bold tracking-tight">Des formules claires</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-gray-600">
                    Commencez gratuitement, faites évoluer votre abonnement quand vos besoins grandissent.
                </p>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="(p, i) in plans" :key="p.value"
                        class="flex flex-col rounded-2xl border bg-white p-8 shadow-sm"
                        :class="i === 1 ? 'border-[var(--brand)] ring-1 ring-[var(--brand)]/30' : 'border-gray-200'"
                    >
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">{{ p.label }}</h3>
                            <span v-if="i === 1" class="rounded-full bg-[var(--brand)]/10 px-2.5 py-0.5 text-xs font-semibold text-[var(--brand)]">Populaire</span>
                        </div>
                        <p class="mt-4 text-3xl font-bold">{{ priceLabel(p) }}</p>
                        <ul class="mt-6 space-y-2 text-sm text-gray-600">
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> {{ limit(p.limits.users) }} utilisateurs</li>
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> {{ limit(p.limits.vehicles) }} véhicules</li>
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> {{ limit(p.limits.sites) }} site(s)</li>
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> Protocoles, matériel, événements</li>
                        </ul>
                        <a
                            :href="registerUrl"
                            class="mt-8 rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition"
                            :class="i === 1 ? 'bg-[var(--brand)] text-white hover:brightness-110' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                        >
                            {{ p.price === 0 ? 'Commencer gratuitement' : 'Choisir ' + p.label }}
                        </a>
                    </article>
                </div>
                <p class="mt-6 text-center text-sm text-gray-500">
                    Formule supérieure : nom de domaine personnalisé pour votre structure. Tarifs HT.
                </p>
            </div>
        </section>

        <!-- CTA final -->
        <section class="border-t border-gray-100 bg-[var(--brand)] py-16 text-white">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <h2 class="text-3xl font-bold tracking-tight">Prêt à fiabiliser vos inventaires ?</h2>
                <p class="mt-3 text-white/80">Lancez votre organisation en quelques minutes, sans carte bancaire.</p>
                <a :href="registerUrl" class="mt-8 inline-block rounded-lg bg-white px-6 py-3 text-sm font-semibold text-[var(--brand)] transition hover:bg-white/90">
                    Démarrer l’essai gratuit
                </a>
            </div>
        </section>

        <!-- Pied de page -->
        <footer class="border-t border-gray-100 py-10">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 text-sm text-gray-500 sm:flex-row">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[var(--brand)] text-xs font-bold text-white">V</span>
                    <span>© {{ new Date().getFullYear() }} Vulcain</span>
                </div>
                <div class="flex items-center gap-6">
                    <a href="#fonctionnalites" class="hover:text-gray-900">Fonctionnalités</a>
                    <a href="#tarifs" class="hover:text-gray-900">Tarifs</a>
                    <a :href="appHomeUrl" class="hover:text-gray-900">Se connecter</a>
                </div>
            </div>
        </footer>
    </div>
</template>
