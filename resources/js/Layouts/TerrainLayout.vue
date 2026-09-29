<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import InstallPrompt from '@/Components/InstallPrompt.vue';

const page = usePage();
const status = computed(() => page.props.flash?.status || page.props.status);
const currentPath = computed(() => {
    try { return new URL(page.url, 'http://x').pathname; } catch (e) { return page.url; }
});
function active(prefix, exact = false) {
    const p = currentPath.value;
    return exact ? p === prefix : (p === prefix || p.startsWith(prefix + '/'));
}

const myOpenEvents = computed(() => page.props.notifications?.myOpenEvents ?? 0);
const nav = computed(() => [
    { label: 'Accueil', href: '/t', icon: 'dashboard', exact: true, badge: 0 },
    { label: 'Scanner', href: '/t/scanner', icon: 'camera', exact: false, badge: 0 },
    { label: 'Événements', href: '/t/mes-evenements', icon: 'protocol', exact: false, badge: myOpenEvents.value },
    { label: 'Congés', href: '/t/conges', icon: 'calendar', exact: false, badge: 0 },
]);
</script>

<template>
    <div class="flex min-h-full flex-col bg-gray-100 text-gray-900" style="min-height: 100dvh">
        <!-- Barre haute -->
        <header
            class="sticky top-0 z-30 flex items-center justify-between gap-3 bg-[var(--forge,#12161C)] px-4 text-white"
            style="padding-top: calc(0.75rem + env(safe-area-inset-top, 0px)); padding-bottom: 0.75rem"
        >
            <Link href="/t" class="flex items-center gap-2">
                <img src="/favicon.svg" alt="" class="h-7 w-7" />
                <span class="font-semibold tracking-tight" style="font-family: 'Archivo', system-ui, sans-serif">Vulkain</span>
            </Link>
            <div class="flex items-center gap-1">
                <slot name="actions" />
                <Link href="/dashboard" class="rounded-lg p-2 text-white/60 hover:bg-white/10 hover:text-white" title="Version complète">
                    <Icon name="external" :size="18" />
                </Link>
            </div>
        </header>

        <!-- Toast statut -->
        <Transition
            enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100"
            leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0"
        >
            <div v-if="status" class="mx-4 mt-3 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow">
                {{ status }}
            </div>
        </Transition>

        <!-- Titre -->
        <div v-if="$slots.title" class="px-4 pb-1 pt-4">
            <h1 class="text-xl font-bold tracking-tight text-gray-900" style="font-family: 'Archivo', system-ui, sans-serif">
                <slot name="title" />
            </h1>
        </div>

        <!-- Contenu -->
        <main class="flex-1 px-4 pb-28 pt-3">
            <slot />
        </main>

        <!-- Bouton flottant : signaler une anomalie -->
        <Link
            href="/t/anomalie"
            class="fixed right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--brand,#C6362B)] text-white shadow-lg transition hover:brightness-110"
            style="bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px))"
            title="Signaler une anomalie"
        >
            <Icon name="bell" :size="24" />
        </Link>

        <!-- Navigation basse -->
        <nav
            class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-gray-200 bg-white"
            style="padding-bottom: env(safe-area-inset-bottom, 0px)"
        >
            <Link
                v-for="item in nav"
                :key="item.href"
                :href="item.href"
                class="flex flex-col items-center gap-0.5 py-2.5 text-xs font-medium transition"
                :class="active(item.href, item.exact) ? 'text-[var(--brand,#C6362B)]' : 'text-gray-500 hover:text-gray-800'"
            >
                <span class="relative">
                    <Icon :name="item.icon" :size="22" />
                    <span v-if="item.badge > 0" class="absolute -right-2.5 -top-1.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--brand,#C6362B)] px-1 text-[10px] font-bold text-white">{{ item.badge > 9 ? '9+' : item.badge }}</span>
                </span>
                {{ item.label }}
            </Link>
        </nav>

        <InstallPrompt />
    </div>
</template>
