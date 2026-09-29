<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    categories: { type: Array, default: () => [] },
});

const form = useForm({ name: '' });
function addCategory() {
    form.post('/material-categories', { preserveScroll: true, onSuccess: () => form.reset() });
}
function removeCategory(c) {
    const warn = c.types_count > 0
        ? `Supprimer la catégorie « ${c.name} » ? ${c.types_count} type(s) y sont rattachés (ils perdront leur catégorie).`
        : `Supprimer la catégorie « ${c.name} » ?`;
    if (confirm(warn)) router.delete(`/material-categories/${c.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Catégories de matériel" />
        <template #title>Catégories de matériel</template>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <p class="mb-3 text-sm text-gray-500">
                    Les catégories regroupent les types de matériel (ex. Diagnostic, Consommables, Oxygène…).
                    Assignez ensuite un type à une catégorie dans <Link href="/material-types" class="text-[var(--brand)] hover:underline">Types de matériel</Link>.
                </p>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Catégorie</th>
                                <th class="px-4 py-3">Types rattachés</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in categories" :key="c.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ c.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ c.types_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="removeCategory(c)">Supprimer</button>
                                </td>
                            </tr>
                            <tr v-if="categories.length === 0"><td colspan="3" class="px-4 py-8 text-center text-gray-500">Aucune catégorie. Créez-en une pour classer vos types de matériel.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="flex items-center gap-2 text-base font-semibold text-gray-900"><Icon name="tag" :size="18" class="text-gray-400" /> Nouvelle catégorie</h2>
                <form class="mt-4 space-y-4" @submit.prevent="addCategory">
                    <div>
                        <TextInput v-model="form.name" class="w-full" placeholder="Ex. Diagnostic, Consommables, Oxygène…" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <button type="submit" :disabled="form.processing || !form.name" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60">Créer</button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
