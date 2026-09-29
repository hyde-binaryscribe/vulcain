<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({ track_expiry_in_mobile: true }) },
});

const form = useForm({
    track_expiry_in_mobile: props.settings.track_expiry_in_mobile,
    bags_enabled: props.settings.bags_enabled,
    vehicle_access_qr_only: props.settings.vehicle_access_qr_only,
    fuel_tracking_enabled: props.settings.fuel_tracking_enabled,
    service_start_steps: [...(props.settings.service_start_steps ?? [])],
});

function addStep() {
    form.service_start_steps.push('');
}
function removeStep(i) {
    form.service_start_steps.splice(i, 1);
}

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

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Emplacements</h2>
                <p class="mt-1 text-sm text-gray-500">Options d'organisation des emplacements de matériel.</p>

                <label class="mt-5 flex items-start gap-3">
                    <input v-model="form.bags_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Activer les sacs</span>
                        <span class="block text-xs text-gray-500">
                            Ajoute la nature d'emplacement « Sac » (sacs de secours à bord d'un véhicule).
                            Les sacs déjà créés restent visibles même si l'option est désactivée.
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

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Accès terrain aux véhicules</h2>
                <p class="mt-1 text-sm text-gray-500">Contrôle la manière dont le personnel de terrain accède aux fiches véhicule dans l'application terrain.</p>

                <label class="mt-5 flex items-start gap-3">
                    <input v-model="form.fuel_tracking_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Activer le suivi du carburant</span>
                        <span class="block text-xs text-gray-500">
                            Enregistrement des pleins (litrage, prix, kilométrage) et calcul de la consommation.
                            Désactivé, la section carburant est masquée sur les fiches véhicule.
                        </span>
                    </span>
                </label>

                <label class="mt-5 flex items-start gap-3">
                    <input v-model="form.vehicle_access_qr_only" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Accès par QR code uniquement</span>
                        <span class="block text-xs text-gray-500">
                            Masque la liste des véhicules dans l'application terrain : le personnel ouvre une fiche
                            uniquement en scannant le QR placé à bord. Les gestionnaires (droit « véhicules »)
                            conservent la liste complète.
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

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Procédure de prise de service</h2>
                    <button type="button" class="text-sm font-medium text-[var(--brand)] hover:brightness-110" @click="addStep">+ Étape</button>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    Checklist déroulée par le personnel à la prise de service (après scan du QR ou choix du véhicule),
                    avant d'accéder à la fiche.
                </p>

                <div class="mt-4 space-y-2">
                    <div v-for="(step, i) in form.service_start_steps" :key="i" class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">{{ i + 1 }}.</span>
                        <input v-model="form.service_start_steps[i]" type="text" placeholder="Intitulé de l'étape" class="min-w-0 flex-1 rounded-lg border-gray-300 px-3 py-2 text-sm" />
                        <button type="button" class="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" @click="removeStep(i)">✕</button>
                    </div>
                    <p v-if="form.service_start_steps.length === 0" class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-400">
                        Aucune étape : la procédure par défaut du secteur sera utilisée.
                    </p>
                </div>

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
