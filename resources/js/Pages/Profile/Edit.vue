<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    user: { type: Object, required: true },
    sessions: { type: Array, default: () => [] },
});

const profileForm = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    grade: props.user.grade,
    email: props.user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updateProfile() {
    profileForm.patch('/profile', { preserveScroll: true });
}

function updatePassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function revoke(id) {
    router.delete(`/sessions/${id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Profil" />
        <template #title>Mon profil</template>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Informations -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Informations personnelles</h2>
                <form class="mt-4 space-y-4" @submit.prevent="updateProfile">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Prénom" />
                            <TextInput v-model="profileForm.first_name" />
                            <InputError :message="profileForm.errors.first_name" />
                        </div>
                        <div>
                            <InputLabel value="Nom" />
                            <TextInput v-model="profileForm.last_name" />
                            <InputError :message="profileForm.errors.last_name" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Grade" />
                        <TextInput v-model="profileForm.grade" />
                        <InputError :message="profileForm.errors.grade" />
                    </div>
                    <div>
                        <InputLabel value="Adresse e-mail" />
                        <TextInput v-model="profileForm.email" type="email" />
                        <InputError :message="profileForm.errors.email" />
                    </div>
                    <div class="w-40">
                        <PrimaryButton :disabled="profileForm.processing">Enregistrer</PrimaryButton>
                    </div>
                </form>
            </section>

            <!-- Mot de passe -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Changer le mot de passe</h2>
                <form class="mt-4 space-y-4" @submit.prevent="updatePassword">
                    <div>
                        <InputLabel value="Mot de passe actuel" />
                        <TextInput v-model="passwordForm.current_password" type="password" />
                        <InputError :message="passwordForm.errors.current_password" />
                    </div>
                    <div>
                        <InputLabel value="Nouveau mot de passe" />
                        <TextInput v-model="passwordForm.password" type="password" />
                        <InputError :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <InputLabel value="Confirmez le mot de passe" />
                        <TextInput v-model="passwordForm.password_confirmation" type="password" />
                    </div>
                    <div class="w-40">
                        <PrimaryButton :disabled="passwordForm.processing">Mettre à jour</PrimaryButton>
                    </div>
                </form>
            </section>

            <!-- Sessions -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                <h2 class="text-base font-semibold text-gray-900">Sessions actives</h2>
                <p class="mt-1 text-sm text-gray-500">Révoquez les sessions que vous ne reconnaissez pas.</p>

                <ul class="mt-4 divide-y divide-gray-100">
                    <li v-for="s in sessions" :key="s.id" class="flex items-center justify-between py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-gray-800">
                                {{ s.ip_address || 'IP inconnue' }}
                                <span v-if="s.is_current" class="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">session actuelle</span>
                            </p>
                            <p class="truncate text-xs text-gray-500">{{ s.user_agent }}</p>
                            <p class="text-xs text-gray-400">Dernière activité {{ s.last_active }}</p>
                        </div>
                        <button
                            v-if="!s.is_current"
                            type="button"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 transition hover:bg-gray-50"
                            @click="revoke(s.id)"
                        >
                            Révoquer
                        </button>
                    </li>
                    <li v-if="sessions.length === 0" class="py-3 text-sm text-gray-500">Aucune session enregistrée.</li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
