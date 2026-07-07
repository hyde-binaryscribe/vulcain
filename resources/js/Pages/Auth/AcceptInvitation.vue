<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
    organisationName: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    first_name: '',
    last_name: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/accept-invitation', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Activer mon compte" />

        <h2 class="mb-1 text-xl font-semibold text-gray-900">Activer mon compte</h2>
        <p class="mb-6 text-sm text-gray-600">
            Vous rejoignez <strong>{{ organisationName }}</strong>. Définissez votre identité et votre mot de passe.
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <InputLabel value="Prénom" />
                    <TextInput v-model="form.first_name" autofocus />
                    <InputError :message="form.errors.first_name" />
                </div>
                <div>
                    <InputLabel value="Nom" />
                    <TextInput v-model="form.last_name" />
                    <InputError :message="form.errors.last_name" />
                </div>
            </div>

            <div>
                <InputLabel value="Adresse e-mail" />
                <TextInput v-model="form.email" type="email" />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <InputLabel value="Mot de passe" />
                <TextInput v-model="form.password" type="password" />
                <InputError :message="form.errors.password" />
                <p class="mt-1 text-xs text-gray-500">Au moins 10 caractères, avec lettres et chiffres.</p>
            </div>

            <div>
                <InputLabel value="Confirmez le mot de passe" />
                <TextInput v-model="form.password_confirmation" type="password" />
            </div>

            <PrimaryButton :disabled="form.processing">Activer mon compte</PrimaryButton>
        </form>
    </GuestLayout>
</template>
