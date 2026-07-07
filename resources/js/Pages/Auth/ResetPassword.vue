<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Nouveau mot de passe" />

        <h2 class="mb-6 text-xl font-semibold text-gray-900">Nouveau mot de passe</h2>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel value="Adresse e-mail" />
                <TextInput v-model="form.email" type="email" />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <InputLabel value="Nouveau mot de passe" />
                <TextInput v-model="form.password" type="password" autofocus />
                <InputError :message="form.errors.password" />
                <p class="mt-1 text-xs text-gray-500">Au moins 10 caractères, avec lettres et chiffres.</p>
            </div>

            <div>
                <InputLabel value="Confirmez le mot de passe" />
                <TextInput v-model="form.password_confirmation" type="password" />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton :disabled="form.processing">Réinitialiser</PrimaryButton>
        </form>
    </GuestLayout>
</template>
