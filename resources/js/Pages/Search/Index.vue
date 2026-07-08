<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    q: { type: String, default: '' },
    groups: { type: Array, default: () => [] },
});

const query = ref(props.q);

function search() {
    router.get('/search', { q: query.value }, { preserveState: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Recherche" />
        <template #title>Recherche</template>

        <form class="mb-6 flex gap-2" @submit.prevent="search">
            <input
                v-model="query"
                type="search"
                placeholder="Rechercher matériel, véhicule, protocole, événement…"
                class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10"
                autofocus
            />
            <button type="submit" class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">Rechercher</button>
        </form>

        <div v-if="groups.length" class="space-y-6">
            <section v-for="g in groups" :key="g.label" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <h2 class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ g.label }}</h2>
                <ul class="divide-y divide-gray-100">
                    <li v-for="(r, i) in g.results" :key="i">
                        <Link :href="r.href" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-gray-50">
                            <span class="font-medium text-gray-900">{{ r.label }}</span>
                            <span class="text-xs text-gray-400">{{ r.sub }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>

        <p v-else-if="q" class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
            Aucun résultat pour « {{ q }} ».
        </p>
        <p v-else class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-400">
            Saisis un terme pour lancer la recherche.
        </p>
    </AppLayout>
</template>
