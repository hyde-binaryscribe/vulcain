<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: { type: String, default: 'Vulcain' },
    phase: { type: String, default: '' },
});
</script>

<template>
    <Head title="Accueil" />
    <div class="flex min-h-full flex-col items-center justify-center px-6 py-16">
        <div class="w-full max-w-xl rounded-2xl border border-gray-200 bg-white p-10 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-800 font-bold text-white">
                    V
                </span>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight text-gray-900">{{ appName }}</h1>
                    <p class="text-sm text-gray-500">Inventaire sapeurs-pompiers — SaaS multi-tenant</p>
                </div>
            </div>

            <p class="mt-6 text-sm leading-relaxed text-gray-600">
                Socle technique opérationnel : Laravel + Inertia + Vue&nbsp;3 + Tailwind,
                PostgreSQL et Redis, prêt pour la fondation multi-locataire.
            </p>

            <div v-if="phase" class="mt-6 inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-800">
                {{ phase }}
            </div>
        </div>
    </div>
</template>
