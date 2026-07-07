<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    protocols: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
});

const selectedTemplate = ref(props.templates[0]?.id ?? '');
const starting = ref(false);

function start() {
    if (!selectedTemplate.value) return;
    starting.value = true;
    router.post('/protocols', { protocol_template_id: selectedTemplate.value }, {
        onFinish: () => (starting.value = false),
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Protocoles" />
        <template #title>Protocoles</template>

        <!-- Démarrer -->
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Démarrer un protocole</h2>
            <div class="mt-3 flex flex-wrap items-end gap-2">
                <div class="min-w-64 flex-1">
                    <select v-model="selectedTemplate" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option v-for="t in templates" :key="t.id" :value="t.id">
                            {{ t.name }} — {{ t.vehicle }}<span v-if="t.type_labels && t.type_labels.length"> ({{ t.type_labels.join(', ') }})</span>
                        </option>
                        <option v-if="templates.length === 0" value="">Aucun modèle disponible</option>
                    </select>
                </div>
                <button
                    :disabled="!selectedTemplate || starting"
                    class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-50"
                    @click="start"
                >
                    Démarrer
                </button>
            </div>
        </div>

        <!-- Historique -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">Véhicule</th><th class="px-4 py-3">Modèle</th><th class="px-4 py-3">Types</th><th class="px-4 py-3">Vérificateur</th><th class="px-4 py-3">Démarré</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="i in protocols" :key="i.id">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ i.vehicle_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ i.template_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="label in i.type_labels" :key="label" class="rounded-full bg-[var(--brand)]/10 px-2 py-0.5 text-xs font-medium text-[var(--brand)]">{{ label }}</span>
                                <span v-if="!i.type_labels || i.type_labels.length === 0" class="text-xs text-gray-400">—</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ i.verifier ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ i.started_at }}</td>
                        <td class="px-4 py-3">
                            <span v-if="i.status === 'validated'" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Validé</span>
                            <span v-else class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Brouillon</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/protocols/${i.id}`" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-50">
                                {{ i.status === 'validated' ? 'Consulter' : (i.is_owner ? 'Reprendre' : 'Voir') }}
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="protocols.length === 0"><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucun protocole.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
