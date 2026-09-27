<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    baseDomain: { type: String, default: 'app.vulkain.eu' },
    registerUrl: { type: String, default: '/inscription' },
});

const slug = ref('');
const clean = computed(() => slug.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, ''));
const target = computed(() => (clean.value ? `${clean.value}.${props.baseDomain}` : ''));

function go() {
    if (!clean.value) return;
    window.location.href = `${window.location.protocol}//${clean.value}.${props.baseDomain}/login`;
}
</script>

<template>
    <GuestLayout>
        <Head title="Accéder à mon espace" />

        <h2 class="text-xl font-semibold text-gray-900">Accéder à votre espace</h2>
        <p class="mt-1 text-sm text-gray-500">Saisissez l’identifiant de votre organisation.</p>

        <form class="mt-6 space-y-4" @submit.prevent="go">
            <div>
                <InputLabel value="Identifiant de l’organisation" />
                <TextInput v-model="slug" placeholder="mon-organisation" autofocus />
                <p v-if="target" class="mt-1 text-xs text-gray-500">Vous serez redirigé vers <span class="font-medium text-gray-700">{{ target }}</span></p>
            </div>
            <PrimaryButton :disabled="!clean">Continuer</PrimaryButton>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            Pas encore de compte ?
            <a :href="registerUrl" class="font-medium text-[var(--brand)] hover:underline">Créer une organisation</a>
        </p>
    </GuestLayout>
</template>
