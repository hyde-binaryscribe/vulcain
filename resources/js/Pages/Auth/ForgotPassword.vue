<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    status: { type: String, default: '' },
});

const form = useForm({ email: '' });

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <GuestLayout>
        <Head title="Mot de passe oublié" />

        <h2 class="mb-2 text-xl font-semibold text-gray-900">Mot de passe oublié</h2>
        <p class="mb-6 text-sm text-gray-600">
            Indiquez votre adresse e-mail : nous vous enverrons un lien de réinitialisation.
        </p>

        <div v-if="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel value="Adresse e-mail" />
                <TextInput v-model="form.email" type="email" autofocus />
                <InputError :message="form.errors.email" />
            </div>

            <PrimaryButton :disabled="form.processing">Envoyer le lien</PrimaryButton>
        </form>

        <div class="mt-6 text-center">
            <Link href="/login" class="text-sm text-red-800 hover:underline">Retour à la connexion</Link>
        </div>
    </GuestLayout>
</template>
