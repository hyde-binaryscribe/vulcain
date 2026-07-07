<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: '' },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <h2 class="mb-6 text-xl font-semibold text-gray-900">Connexion</h2>

        <div v-if="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel value="Adresse e-mail" />
                <TextInput v-model="form.email" type="email" autofocus />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <InputLabel value="Mot de passe" />
                <TextInput v-model="form.password" type="password" />
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-gray-300 text-red-800 focus:ring-red-700/30" />
                    Se souvenir de moi
                </label>

                <Link v-if="canResetPassword" href="/forgot-password" class="text-sm text-red-800 hover:underline">
                    Mot de passe oublié ?
                </Link>
            </div>

            <PrimaryButton :disabled="form.processing">Se connecter</PrimaryButton>
        </form>
    </GuestLayout>
</template>
