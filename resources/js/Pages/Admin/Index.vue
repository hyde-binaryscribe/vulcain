<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    counts: { type: Object, default: () => ({}) },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.user?.permissions || []);
const sector = computed(() => page.props.tenant?.profile?.sector);
function can(p) {
    return !p || permissions.value.includes(p);
}

const groups = computed(() => {
    const raw = [
        {
            label: 'Flotte',
            items: [
                { label: 'Types de véhicule', href: '/vehicle-types', icon: 'tag', desc: 'VSAV, Ambulance type A/B, VSL…', count: props.counts.vehicle_types, permission: 'vehicles.manage' },
                { label: 'Modèles de véhicule', href: '/vehicle-models', icon: 'vehicle', desc: "Gabarits d'emplacements générés à la création.", count: props.counts.vehicle_models, permission: 'vehicles.manage' },
                { label: 'Protocoles de service', href: '/protocoles-service', icon: 'clock', desc: 'Checklists de prise & fin de service.', count: props.counts.service_protocols, permission: 'vehicles.manage' },
                ...(sector.value === 'ambulance_privee'
                    ? [{ label: 'Protocoles de désinfection', href: '/disinfection-protocols', icon: 'protocol', desc: 'Bibliothèque ARS + périodicités.', count: props.counts.disinfection_protocols, permission: 'vehicles.manage' }]
                    : []),
            ],
        },
        {
            label: 'Matériel',
            items: [
                { label: 'Types de matériel', href: '/material-types', icon: 'tag', desc: 'Thermomètre, Compresse 5×5… + mode de suivi.', count: props.counts.material_types, permission: 'catalog.manage' },
                { label: 'Catégories de matériel', href: '/material-categories', icon: 'bookmark', desc: 'Rangement logique du catalogue.', count: props.counts.material_categories, permission: 'catalog.manage' },
                { label: 'Modèles de protocole', href: '/templates', icon: 'template', desc: 'Gabarits de vérification réutilisables.', count: props.counts.protocol_templates, permission: 'templates.manage' },
            ],
        },
        {
            label: 'Organisation & accès',
            items: [
                { label: 'Réglages', href: '/settings', icon: 'settings', desc: "Identité, seuils d'alerte, options de l'organisation.", count: null, permission: 'settings.manage' },
                { label: 'Utilisateurs & rôles', href: '/users', icon: 'users', desc: 'Invitations, permissions, réinitialisations.', count: props.counts.users, permission: 'users.manage' },
                { label: 'Historique', href: '/activity', icon: 'clock', desc: "Journal d'activité horodaté de l'organisation.", count: null, permission: 'history.view' },
            ],
        },
    ];
    return raw
        .map((g) => ({ ...g, items: g.items.filter((it) => can(it.permission)) }))
        .filter((g) => g.items.length > 0);
});
</script>

<template>
    <AppLayout>
        <Head title="Administration" />
        <template #title>Administration</template>

        <p class="mb-6 max-w-2xl text-sm text-gray-500">
            Paramétrage de l'organisation — référentiels, accès et réglages. Hors du menu quotidien.
        </p>

        <div v-for="g in groups" :key="g.label" class="mb-8">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ g.label }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="it in g.items"
                    :key="it.href"
                    :href="it.href"
                    class="flex items-start gap-3.5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-[var(--brand)]/40 hover:shadow"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-[var(--brand)]">
                        <Icon :name="it.icon" :size="18" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-gray-900">{{ it.label }}</span>
                        <span class="mt-0.5 block text-xs leading-snug text-gray-500">{{ it.desc }}</span>
                    </span>
                    <span v-if="it.count != null" class="shrink-0 text-sm font-bold tabular-nums text-gray-300">{{ it.count }}</span>
                </Link>
            </div>
        </div>

        <p v-if="groups.length === 0" class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
            Aucun paramétrage accessible avec vos permissions.
        </p>
    </AppLayout>
</template>
