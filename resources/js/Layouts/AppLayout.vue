<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SearchPalette from '@/Components/SearchPalette.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const profile = computed(() => tenant.value?.profile);
const brand = computed(() => profile.value?.theme || '#991b1b');
const flash = computed(() => page.props.flash?.status || page.props.status);
const flashError = computed(() => page.props.flash?.error);

const permissions = computed(() => user.value?.permissions || []);

function can(permission) {
    return !permission || permissions.value.includes(permission);
}

const sidebarOpen = ref(false);

// Navigation groupée. Chaque entrée peut exiger une permission (menu adapté au
// rôle, revérifié côté serveur). Un groupe vide est masqué.
const navGroups = computed(() =>
    [
        {
            label: 'Exploitation',
            items: [
                { label: 'Tableau de bord', href: '/dashboard', icon: '🏠', permission: null },
                { label: 'Protocoles', href: '/protocols', icon: '✅', permission: 'protocols.perform' },
                { label: 'Événements', href: '/events', icon: '🗂', permission: 'anomalies.manage' },
            ],
        },
        {
            label: 'Parc & stock',
            items: [
                { label: 'Sites', href: '/sites', icon: '🏢', permission: 'sites.manage' },
                { label: 'Véhicules', href: '/vehicles', icon: '🚑', permission: 'vehicles.manage' },
                { label: 'Emplacements', href: '/locations', icon: '📍', permission: 'locations.manage' },
                { label: 'Matériel', href: '/materials', icon: '🧰', permission: 'catalog.manage' },
                { label: 'Pharmacie', href: '/pharmacy', icon: '💊', permission: 'pharmacy.manage' },
            ],
        },
        {
            label: 'Configuration',
            items: [
                { label: 'Modèles de protocole', href: '/templates', icon: '📋', permission: 'templates.manage' },
                { label: 'Utilisateurs', href: '/users', icon: '👥', permission: 'users.manage' },
                { label: 'Réglages', href: '/settings', icon: '⚙', permission: 'settings.manage' },
            ],
        },
        {
            label: 'Suivi',
            items: [
                { label: 'Historique', href: '/activity', icon: '🕓', permission: 'history.view' },
                { label: 'Profil', href: '/profile', icon: '👤', permission: null },
            ],
        },
    ]
        .map((group) => ({ ...group, items: group.items.filter((item) => can(item.permission)) }))
        .filter((group) => group.items.length > 0),
);

function isActive(href) {
    return page.url.startsWith(href);
}

function logout() {
    router.post('/logout');
}

// Multi-sites : sélecteur de site actif.
const siteContext = computed(() => page.props.siteContext || { options: [], current: null });
function switchSite(e) {
    router.post('/site-switch', { site_id: e.target.value || null });
}

// Palette de recherche (temps réel, Ctrl/Cmd+K).
const paletteOpen = ref(false);
function onGlobalKey(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        paletteOpen.value = true;
    }
}
onMounted(() => window.addEventListener('keydown', onGlobalKey));
onUnmounted(() => window.removeEventListener('keydown', onGlobalKey));

// Notifications
const notifications = computed(() => page.props.notifications || { unread: 0, items: [] });
const showNotifs = ref(false);
function openNotif(item) {
    showNotifs.value = false;
    router.post(`/notifications/${item.id}/read`, {}, { preserveScroll: true });
}
function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true, onSuccess: () => (showNotifs.value = false) });
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

            <nav class="flex-1 space-y-4 overflow-y-auto p-3">
                <div v-for="group in navGroups" :key="group.label">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-500">{{ group.label }}</p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="isActive(item.href) ? 'bg-[var(--brand)] text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white'"
                            @click="sidebarOpen = false"
                        >
                            <span class="w-4 text-center text-xs opacity-80">{{ item.icon }}</span>
                            {{ item.label }}
                        </Link>
                    </div>
                </div>
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
                    <select
                        v-if="siteContext.options.length > 1"
                        :value="siteContext.current ?? ''"
                        class="ml-2 hidden rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-gray-700 sm:block"
                        title="Site actif"
                        @change="switchSite"
                    >
                        <option value="">🏢 Tous les sites</option>
                        <option v-for="s in siteContext.options" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="hidden items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-400 hover:bg-gray-50 md:flex"
                        @click="paletteOpen = true"
                    >
                        <span>🔍</span>
                        <span>Rechercher…</span>
                        <kbd class="rounded border border-gray-200 px-1.5 py-0.5 text-[10px] text-gray-400">⌘K</kbd>
                    </button>
                    <div class="relative">
                        <button type="button" class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100" @click="showNotifs = !showNotifs">
                            <span class="text-lg">🔔</span>
                            <span v-if="notifications.unread > 0" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ notifications.unread }}</span>
                        </button>
                        <div v-if="showNotifs" class="fixed inset-0 z-20" @click="showNotifs = false" />
                        <div v-if="showNotifs" class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2">
                                <span class="text-sm font-semibold text-gray-900">Notifications</span>
                                <button v-if="notifications.unread > 0" class="text-xs text-[var(--brand)] hover:underline" @click="markAllRead">Tout marquer lu</button>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                <button
                                    v-for="n in notifications.items"
                                    :key="n.id"
                                    type="button"
                                    class="block w-full border-b border-gray-50 px-4 py-2.5 text-left hover:bg-gray-50"
                                    :class="n.read ? 'opacity-60' : ''"
                                    @click="openNotif(n)"
                                >
                                    <p class="text-sm text-gray-800">{{ n.message }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-400">{{ n.at }}</p>
                                </button>
                                <p v-if="notifications.items.length === 0" class="px-4 py-6 text-center text-xs text-gray-400">Aucune notification.</p>
                            </div>
                        </div>
                    </div>
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
                <div
                    v-if="flashError"
                    class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ flashError }}
                </div>

                <slot />
            </main>
        </div>

        <SearchPalette :open="paletteOpen" @close="paletteOpen = false" />
    </div>
</template>
