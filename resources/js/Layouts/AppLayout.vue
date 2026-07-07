<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const profile = computed(() => tenant.value?.profile);
const brand = computed(() => profile.value?.theme || '#991b1b');
const flash = computed(() => page.props.flash?.status || page.props.status);

const permissions = computed(() => user.value?.permissions || []);

function can(permission) {
    return !permission || permissions.value.includes(permission);
}

const sidebarOpen = ref(false);

// Chaque entrée peut exiger une permission (menu adapté au rôle, vérifié aussi côté serveur).
const nav = computed(() =>
    [
        { label: 'Tableau de bord', href: '/dashboard', permission: null },
        { label: 'Protocoles', href: '/protocols', permission: 'protocols.perform' },
        { label: 'Véhicules', href: '/vehicles', permission: 'vehicles.manage' },
        { label: 'Emplacements', href: '/locations', permission: 'locations.manage' },
        { label: 'Matériel', href: '/materials', permission: 'catalog.manage' },
        { label: 'Modèles', href: '/templates', permission: 'templates.manage' },
        { label: 'Historique', href: '/activity', permission: 'history.view' },
        { label: 'Utilisateurs', href: '/users', permission: 'users.manage' },
        { label: 'Profil', href: '/profile', permission: null },
    ].filter((item) => can(item.permission)),
);

function isActive(href) {
    return page.url.startsWith(href);
}

function logout() {
    router.post('/logout');
}

const initials = computed(() => {
    const name = user.value?.name || '';
    return name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
});
</script>

<template>
    <div class="flex min-h-screen bg-gray-50" :style="{ '--brand': brand }">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full transform flex-col bg-gray-950 text-gray-200 transition-transform lg:static lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--brand)] font-bold text-white">V</span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-white">Vulcain</p>
                    <p class="text-xs text-gray-400">{{ profile?.label || 'Protocole' }}</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="isActive(item.href) ? 'bg-[var(--brand)] text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white'"
                    @click="sidebarOpen = false"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="shrink-0 border-t border-white/10 p-3">
                <button
                    type="button"
                    class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-gray-300 transition hover:bg-white/5 hover:text-white"
                    @click="logout"
                >
                    Se déconnecter
                </button>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-black/40 lg:hidden" @click="sidebarOpen = false" />

        <!-- Main -->
        <div class="flex min-h-full min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 lg:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <span class="block h-0.5 w-5 bg-current"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                    </button>
                    <div>
                        <p class="text-xs uppercase tracking-wide" :style="{ color: brand }">Inventaire opérationnel</p>
                        <h1 class="text-lg font-semibold text-gray-900"><slot name="title">Tableau de bord</slot></h1>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-gray-900">{{ user?.name }}</p>
                        <p class="text-xs text-gray-500">{{ tenant?.name }}</p>
                    </div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--brand)] text-sm font-semibold text-white">{{ initials }}</span>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">
                <div
                    v-if="flash"
                    class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ flash }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
