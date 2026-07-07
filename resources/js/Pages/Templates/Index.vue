<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    frequencies: { type: Array, default: () => [] },
});

const form = useForm({
    vehicle_id: props.vehicles[0]?.id ?? '',
    name: '',
    frequency: 'weekly',
    is_active: true,
});

function create() {
    form.post('/templates');
}
function remove(t) {
    if (confirm(`Supprimer le modèle « ${t.name} » ?`)) {
        router.delete(`/templates/${t.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Modèles d'inventaire" />
        <template #title>Modèles d'inventaire</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-3">Modèle</th><th class="px-4 py-3">Véhicule</th><th class="px-4 py-3">Fréquence</th><th class="px-4 py-3">Éléments</th><th class="px-4 py-3 text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in templates" :key="t.id">
                                <td class="px-4 py-3">
                                    <Link :href="`/templates/${t.id}/edit`" class="font-medium text-gray-900 hover:text-[var(--brand)] hover:underline">{{ t.name }}</Link>
                                    <span class="ml-1 text-xs text-gray-400">v{{ t.version }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ t.vehicle }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.frequency_label }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.items_count }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <Link :href="`/templates/${t.id}/edit`" class="rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">Éditer</Link>
                                    <button class="ml-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(t)">Suppr.</button>
                                </td>
                            </tr>
                            <tr v-if="templates.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun modèle. Créez-en un →</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Nouveau modèle</h2>
                <form class="mt-4 space-y-4" @submit.prevent="create">
                    <div>
                        <InputLabel value="Véhicule" />
                        <select v-model="form.vehicle_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                        <InputError :message="form.errors.vehicle_id" />
                    </div>
                    <div>
                        <InputLabel value="Nom" />
                        <TextInput v-model="form.name" placeholder="Inventaire hebdomadaire VSAV" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Fréquence" />
                        <select v-model="form.frequency" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Créer</button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
