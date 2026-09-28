<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Logo from '@/Components/Logo.vue';

defineProps({
    plans: { type: Array, default: () => [] },
    registerUrl: { type: String, default: '/inscription' },
    appHomeUrl: { type: String, default: '/' },
});

const sectors = [
    { emoji: '🚒', label: 'Sapeurs-pompiers' },
    { emoji: '🚑', label: 'Ambulanciers' },
    { emoji: '⛑️', label: 'Sécurité civile' },
];

const features = [
    { icon: '✅', title: 'Protocoles sur mesure', text: 'Inventaires, vérifications et contrôles véhicule déduits du périmètre — optimisé pour les admins, simple pour les équipes.' },
    { icon: '🧰', title: 'Matériel & consommables', text: 'Catalogue Type → Modèle → Exemplaire : n° de série pour le durable, lot et péremption pour le consommable.' },
    { icon: '🗂', title: 'Événements & réparations', text: 'Chaque anomalie relevée alimente un Kanban : assignation, commentaires, suivi jusqu’à résolution.' },
    { icon: '🏢', title: 'Multi-sites', text: 'Centres, dépôts, antennes : véhicules et emplacements rattachés à leur site, bascule et vue consolidée.' },
    { icon: '🔔', title: 'Alertes & péremptions', text: 'Stocks sous le seuil et lots proches de la péremption remontés en temps réel (FEFO).' },
    { icon: '🕓', title: 'Traçabilité complète', text: 'Historique horodaté, protocoles figés et verrouillés, exports CSV — conformité et sérénité.' },
];

function priceLabel(p) {
    return p.price === 0 ? 'Gratuit' : `${p.price} € /mois`;
}
function limit(v) {
    return v === null || v === undefined ? 'illimité' : v;
}
</script>

<template>
    <Head title="Vulkain — inventaire opérationnel des services de secours" />

    <div class="min-h-screen bg-white text-gray-900">
        <!-- En-tête -->
        <header class="sticky top-0 z-20 border-b border-gray-100 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5">
                <a href="#top" class="flex items-center gap-2.5">
                    <Logo :size="34" />
                    <span class="font-display text-lg font-extrabold tracking-wide">VULKAIN</span>
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

        <!-- Hero forgé -->
        <section id="top" class="relative overflow-hidden bg-[#11151b] text-white"
            style="background-image: radial-gradient(120% 130% at 85% -15%, rgba(236,106,30,.30), transparent 55%);">
            <div class="mx-auto max-w-6xl px-5 py-20 sm:py-28">
                <span class="font-mono text-xs font-semibold uppercase tracking-[0.18em] text-[var(--spark)]">Inventaire opérationnel des services de secours</span>
                <h1 class="mt-6 max-w-3xl font-display text-4xl font-black leading-[1.03] tracking-tight sm:text-6xl" style="text-wrap:balance">
                    Le matériel prêt à partir, <span class="text-[var(--spark)]">à chaque départ.</span>
                </h1>
                <p class="mt-5 max-w-2xl text-lg text-gray-400">
                    Vulkain digitalise les inventaires, le suivi du matériel et des consommables, et la gestion des
                    anomalies de vos véhicules et de vos sites — pompiers, ambulanciers, sécurité civile.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a :href="registerUrl" class="rounded-lg bg-[var(--brand)] px-6 py-3 text-sm font-semibold text-white transition hover:brightness-110">
                        Démarrer l’essai gratuit
                    </a>
                    <a href="#fonctionnalites" class="rounded-lg border border-white/20 px-6 py-3 text-sm font-semibold text-gray-100 transition hover:border-white/50">
                        Découvrir les fonctionnalités
                    </a>
                </div>
                <div class="mt-12 flex flex-wrap items-center gap-x-8 gap-y-3 text-sm text-gray-400">
                    <span v-for="s in sectors" :key="s.label" class="inline-flex items-center gap-2"><span class="text-lg">{{ s.emoji }}</span> {{ s.label }}</span>
                </div>
            </div>
            <!-- Bandeau de preuve -->
            <div class="border-t border-white/10">
                <div class="mx-auto grid max-w-6xl grid-cols-1 divide-y divide-white/10 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div class="px-5 py-6"><p class="font-display text-2xl font-extrabold text-[var(--spark)]">3</p><p class="text-sm text-gray-400">secteurs métier couverts</p></div>
                    <div class="px-5 py-6"><p class="font-display text-2xl font-extrabold">FEFO</p><p class="text-sm text-gray-400">alertes de péremption</p></div>
                    <div class="px-5 py-6"><p class="font-display text-2xl font-extrabold">100%</p><p class="text-sm text-gray-400">protocoles tracés & figés</p></div>
                </div>
            </div>
        </section>

        <!-- Fonctionnalités -->
        <section id="fonctionnalites" class="border-b border-gray-100 bg-gray-50/60 py-20">
            <div class="mx-auto max-w-6xl px-5">
                <h2 class="text-center font-display text-3xl font-extrabold tracking-tight">Tout le cycle de vie de votre matériel</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-gray-600">Du catalogue à la vérification terrain, jusqu’à la réparation — une seule plateforme.</p>
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
        <section id="tarifs" class="border-b border-gray-100 py-20">
            <div class="mx-auto max-w-6xl px-5">
                <h2 class="text-center font-display text-3xl font-extrabold tracking-tight">Des formules claires</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-gray-600">Commencez gratuitement, faites évoluer votre abonnement quand vos besoins grandissent.</p>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <article v-for="(p, i) in plans" :key="p.value"
                        class="flex flex-col rounded-2xl border bg-white p-8 shadow-sm"
                        :class="i === 1 ? 'border-[var(--brand)] ring-1 ring-[var(--brand)]/30' : 'border-gray-200'">
                        <div class="flex items-center justify-between">
                            <h3 class="font-display text-lg font-bold">{{ p.label }}</h3>
                            <span v-if="i === 1" class="rounded-full bg-[var(--brand)]/10 px-2.5 py-0.5 text-xs font-semibold text-[var(--brand)]">Populaire</span>
                        </div>
                        <p class="mt-4 font-display text-3xl font-extrabold">{{ priceLabel(p) }}</p>
                        <ul class="mt-6 space-y-2 text-sm text-gray-600">
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> {{ limit(p.limits.users) }} utilisateurs</li>
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> {{ limit(p.limits.vehicles) }} véhicules</li>
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> {{ limit(p.limits.sites) }} site(s)</li>
                            <li class="flex items-center gap-2"><span class="text-[var(--brand)]">✓</span> Protocoles, matériel, événements</li>
                        </ul>
                        <a :href="registerUrl" class="mt-8 rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition"
                            :class="i === 1 ? 'bg-[var(--brand)] text-white hover:brightness-110' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'">
                            {{ p.price === 0 ? 'Commencer gratuitement' : 'Choisir ' + p.label }}
                        </a>
                    </article>
                </div>
                <p class="mt-6 text-center text-sm text-gray-500">Formule supérieure : nom de domaine personnalisé pour votre structure. Tarifs HT.</p>
            </div>
        </section>

        <!-- CTA final -->
        <section class="bg-[var(--brand)] py-16 text-white">
            <div class="mx-auto max-w-3xl px-5 text-center">
                <h2 class="font-display text-3xl font-extrabold tracking-tight">Prêt à fiabiliser vos inventaires ?</h2>
                <p class="mt-3 text-white/80">Lancez votre organisation en quelques minutes, sans carte bancaire.</p>
                <a :href="registerUrl" class="mt-8 inline-block rounded-lg bg-white px-6 py-3 text-sm font-semibold text-[var(--brand)] transition hover:bg-white/90">
                    Démarrer l’essai gratuit
                </a>
            </div>
        </section>

        <!-- Pied de page -->
        <footer class="py-10">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 text-sm text-gray-500 sm:flex-row">
                <div class="flex items-center gap-2.5">
                    <Logo :size="24" />
                    <span class="font-display font-bold tracking-wide">VULKAIN</span>
                    <span class="text-gray-400">· © {{ new Date().getFullYear() }}</span>
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
