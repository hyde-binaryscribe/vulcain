<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    sectors: { type: Array, default: () => [] },
    baseDomain: { type: String, default: 'app.vulkain.eu' },
});

const form = useForm({
    name: '',
    slug: '',
    sector: props.sectors[0]?.value ?? '',
    admin_name: '',
    admin_email: '',
    password: '',
    password_confirmation: '',
});

// Suggère un sous-domaine à partir du nom de l'organisation.
function slugify(v) {
    return v.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 63);
}
function onNameInput() {
    if (!form.slug || form.slug === slugify(form.name.slice(0, -1))) {
        form.slug = slugify(form.name);
    }
}

const fullDomain = computed(() => `${form.slug || 'mon-organisation'}.${props.baseDomain}`);

function submit() {
    form.post('/inscription', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <GuestLayout>
        <Head title="Créer un compte" />

        <h2 class="text-xl font-semibold text-gray-900">Créer votre organisation</h2>
        <p class="mt-1 text-sm text-gray-500">Essai gratuit — aucune carte bancaire requise.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel value="Nom de l’organisation" />
                <TextInput v-model="form.name" placeholder="Ambulances de Châtel-Guyon" @input="onNameInput" />
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <InputLabel value="Adresse de votre espace" />
                <div class="flex items-center gap-1">
                    <TextInput v-model="form.slug" placeholder="mon-organisation" class="flex-1" />
                </div>
                <p class="mt-1 text-xs text-gray-500">Votre espace : <span class="font-medium text-gray-700">{{ fullDomain }}</span></p>
                <InputError :message="form.errors.slug" />
            </div>

            <div>
                <InputLabel value="Secteur" />
                <select v-model="form.sector" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:border-[var(--brand)] focus:ring-2 focus:ring-black/10">
                    <option v-for="s in sectors" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <InputError :message="form.errors.sector" />
            </div>

            <hr class="border-gray-100" />

            <div>
                <InputLabel value="Votre nom" />
                <TextInput v-model="form.admin_name" placeholder="Alexandre Jouanneau" />
                <InputError :message="form.errors.admin_name" />
            </div>

            <div>
                <InputLabel value="Adresse e-mail" />
                <TextInput v-model="form.admin_email" type="email" />
                <InputError :message="form.errors.admin_email" />
            </div>

            <div>
                <InputLabel value="Mot de passe" />
                <TextInput v-model="form.password" type="password" />
                <p class="mt-1 text-xs text-gray-400">Au moins 10 caractères, avec lettres et chiffres.</p>
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <InputLabel value="Confirmer le mot de passe" />
                <TextInput v-model="form.password_confirmation" type="password" />
            </div>

            <PrimaryButton :disabled="form.processing">Créer mon organisation</PrimaryButton>
        </form>
    </GuestLayout>
</template>
