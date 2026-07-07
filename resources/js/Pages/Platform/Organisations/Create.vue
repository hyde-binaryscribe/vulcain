<script setup>
import { watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    sectors: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    slug: '',
    sector: props.sectors[0]?.value ?? '',
    admin_email: '',
});

// Suggère un sous-domaine à partir du nom.
watch(
    () => form.name,
    (value) => {
        if (!form.slugTouched) {
            form.slug = value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 63);
        }
    },
);

function submit() {
    form.post('/platform/organisations');
}
</script>

<template>
    <PlatformLayout>
        <Head title="Desk — Nouvelle organisation" />

        <div class="mb-6">
            <Link href="/platform" class="text-sm text-indigo-600 hover:underline">← Retour</Link>
            <h1 class="mt-2 text-xl font-semibold text-slate-900">Nouvelle organisation</h1>
            <p class="text-sm text-slate-500">Crée le client, provisionne ses rôles et invite son premier administrateur.</p>
        </div>

        <form class="max-w-xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <InputLabel value="Nom de l’organisation" />
                <TextInput v-model="form.name" autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <InputLabel value="Sous-domaine" />
                <div class="flex items-center">
                    <TextInput v-model="form.slug" class="rounded-r-none" @input="form.slugTouched = true" />
                    <span class="rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-500">.vulcain</span>
                </div>
                <InputError :message="form.errors.slug" />
            </div>

            <div>
                <InputLabel value="Secteur" />
                <select
                    v-model="form.sector"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/30"
                >
                    <option v-for="s in sectors" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <InputError :message="form.errors.sector" />
            </div>

            <div>
                <InputLabel value="E-mail du premier administrateur" />
                <TextInput v-model="form.admin_email" type="email" />
                <InputError :message="form.errors.admin_email" />
                <p class="mt-1 text-xs text-gray-500">Une invitation lui sera envoyée pour définir son mot de passe.</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-indigo-600 px-4 py-2.5 font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
            >
                Créer et inviter
            </button>
        </form>
    </PlatformLayout>
</template>
