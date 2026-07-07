<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({ status: { type: String, default: '' } });

const form = useForm({ email: '', password: '' });

function submit() {
    form.post('/platform/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Desk — Connexion" />
    <div class="flex min-h-full flex-col items-center justify-center bg-slate-900 px-4 py-12" style="--brand: #4338ca">
        <div class="mb-6 flex items-center gap-2 text-white">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold">V</span>
            <span class="text-lg font-semibold">Vulcain <span class="text-indigo-300">Desk</span></span>
        </div>

        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="mb-1 text-xl font-semibold text-gray-900">Espace exploitant</h1>
            <p class="mb-6 text-sm text-gray-500">Gestion des organisations et de la plateforme.</p>

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
                <PrimaryButton :disabled="form.processing">Se connecter</PrimaryButton>
            </form>
        </div>
    </div>
</template>
