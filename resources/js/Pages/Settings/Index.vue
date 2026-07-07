<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({ track_expiry_in_mobile: true }) },
});

const form = useForm({
    track_expiry_in_mobile: props.settings.track_expiry_in_mobile,
});

function save() {
    form.patch('/settings', { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Réglages" />
        <template #title>Réglages</template>

        <div class="max-w-2xl space-y-6">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Suivi des péremptions</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Configure le suivi des dates de péremption des consommables selon la nature de l'emplacement.
                </p>

                <label class="mt-5 flex items-start gap-3">
                    <input v-model="form.track_expiry_in_mobile" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Suivre les péremptions dans les emplacements mobiles (véhicules)</span>
                        <span class="block text-xs text-gray-500">
                            Le suivi à bord d'un véhicule est parfois difficile à tenir. Désactive cette option si tu préfères
                            ne pas exiger les péremptions du consommable embarqué (les emplacements fixes restent suivis).
                        </span>
                    </span>
                </label>

                <div class="mt-6">
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60"
                        @click="save"
                    >
                        Enregistrer
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
