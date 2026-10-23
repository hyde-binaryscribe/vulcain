<script setup>
import { ref } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({ track_expiry_in_mobile: true }) },
    kanbanColumns: { type: Array, default: () => [] },
    // Lien Itineris : null hors transport sanitaire.
    itineris: { type: Object, default: null },
    // Notifications sortantes (lien intra-Chatel) : null hors transport sanitaire.
    webhooks: { type: Object, default: null },
});

// Lien Itineris : jetons d'accès à l'API v1 (valeur montrée une seule fois).
const tokenForm = useForm({ name: 'Itineris' });
const copied = ref(false);
function createToken() {
    tokenForm.post('/settings/api-tokens', { preserveScroll: true });
}
function revokeToken(t) {
    if (!confirm(`Révoquer le jeton « ${t.name} » ? Itineris ne pourra plus lire les véhicules avec ce jeton.`)) return;
    router.delete(`/settings/api-tokens/${t.id}`, { preserveScroll: true });
}
async function copyToken() {
    await navigator.clipboard?.writeText(props.itineris.new_token);
    copied.value = true;
}
// Notifications sortantes : secret de signature montré une seule fois.
const webhookForm = useForm({ name: 'intra-Chatel', url: '' });
const secretCopied = ref(false);
function createWebhook() {
    webhookForm.post('/settings/webhooks', { preserveScroll: true, onSuccess: () => webhookForm.reset('url') });
}
function testWebhook(e) {
    router.post(`/settings/webhooks/${e.id}/test`, {}, { preserveScroll: true });
}
function deleteWebhook(e) {
    if (!confirm(`Supprimer l'adresse « ${e.name} » ? Elle ne recevra plus les prises et fins de service.`)) return;
    router.delete(`/settings/webhooks/${e.id}`, { preserveScroll: true });
}
async function copySecret() {
    await navigator.clipboard?.writeText(props.webhooks.new_secret);
    secretCopied.value = true;
}
function fmt(iso) {
    return iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

const form = useForm({
    track_expiry_in_mobile: props.settings.track_expiry_in_mobile,
    bags_enabled: props.settings.bags_enabled,
    vehicle_access_qr_only: props.settings.vehicle_access_qr_only,
    fuel_tracking_enabled: props.settings.fuel_tracking_enabled,
    body_inspection_enabled: props.settings.body_inspection_enabled,
    maintenance_alert_days: props.settings.maintenance_alert_days ?? 14,
    maintenance_alert_km: props.settings.maintenance_alert_km ?? 500,
    expiry_alert_days: props.settings.expiry_alert_days ?? 30,
    anomaly_entry_column_id: props.settings.anomaly_entry_column_id ?? '',
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
                    <input v-model="form.body_inspection_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Activer le contrôle carrosserie</span>
                        <span class="block text-xs text-gray-500">
                            Schéma du véhicule et pointage des anomalies (choc, rayure, bris) avec photo.
                            Désactivé, la section carrosserie est masquée sur les fiches véhicule et l'appli terrain.
                        </span>
                    </span>
                </label>

                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm font-medium text-gray-900">Alertes d'échéances</p>
                    <p class="text-xs text-gray-500">Délai avant une échéance à partir duquel l'alerte (orange) apparaît.</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-600">Entretien — jours avant</span>
                            <input v-model.number="form.maintenance_alert_days" type="number" min="1" max="365" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm" />
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-600">Entretien — km avant</span>
                            <input v-model.number="form.maintenance_alert_km" type="number" min="1" max="100000" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm" />
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-600">Péremptions & dates — jours avant</span>
                            <input v-model.number="form.expiry_alert_days" type="number" min="1" max="365" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm" />
                        </label>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">Ex. 14 jours et 500 km pour l'entretien, 30 jours pour les péremptions et documents.</p>
                </div>

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

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Événements (Kanban)</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Colonne dans laquelle arrivent les nouvelles anomalies signalées. Les colonnes et tableaux
                    se gèrent depuis la page <span class="font-medium text-gray-700">Événements</span>.
                </p>

                <label class="mt-4 block">
                    <span class="block text-sm font-medium text-gray-900">Colonne d'entrée des anomalies</span>
                    <select v-model="form.anomaly_entry_column_id" class="mt-1 block w-full max-w-sm rounded-lg border-gray-300 px-3 py-2.5 text-sm">
                        <option value="">1re colonne par défaut</option>
                        <option v-for="c in kanbanColumns" :key="c.id" :value="c.id">{{ c.label }}</option>
                    </select>
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

            <section v-if="itineris" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Lien Itineris</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Itineris (régulation) lit vos véhicules pour savoir qu'il parle des mêmes que Vulkain.
                    Créez un jeton, puis collez-le dans Itineris › Paramètres › Vulkain avec l'adresse ci-dessous.
                </p>
                <p class="mt-3 text-sm text-gray-700">Adresse de l'API : <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs">{{ itineris.api_url }}</code></p>

                <div v-if="itineris.new_token" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">Copiez ce jeton maintenant : il ne sera plus affiché.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <code class="break-all rounded bg-white px-2 py-1 font-mono text-xs text-gray-900">{{ itineris.new_token }}</code>
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="copyToken">
                            {{ copied ? 'Copié' : 'Copier' }}
                        </button>
                    </div>
                </div>

                <table v-if="itineris.tokens.length" class="mt-5 w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3 font-semibold">Nom</th>
                            <th class="py-2 pr-3 font-semibold">Fin</th>
                            <th class="py-2 pr-3 font-semibold">Créé</th>
                            <th class="py-2 pr-3 font-semibold">Dernier usage</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in itineris.tokens" :key="t.id" class="border-t border-gray-100" :class="t.revoked_at ? 'text-gray-400' : 'text-gray-800'">
                            <td class="py-2 pr-3">{{ t.name }}</td>
                            <td class="py-2 pr-3 font-mono text-xs">…{{ t.last_four }}</td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ fmt(t.created_at) }}<span v-if="t.created_by" class="font-sans"> · {{ t.created_by }}</span></td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ fmt(t.last_used_at) }}</td>
                            <td class="py-2 text-right">
                                <span v-if="t.revoked_at" class="text-xs">Révoqué</span>
                                <button v-else type="button" class="text-xs font-semibold text-red-700 hover:underline" @click="revokeToken(t)">Révoquer</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-5 flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-900">Nom du jeton</span>
                        <input v-model="tokenForm.name" type="text" maxlength="100" class="mt-1 block w-64 rounded-lg border-gray-300 px-3 py-2.5 text-sm" />
                    </label>
                    <button
                        type="button"
                        :disabled="tokenForm.processing || !tokenForm.name.trim()"
                        class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60"
                        @click="createToken"
                    >
                        Créer un jeton
                    </button>
                </div>
                <p v-if="tokenForm.errors.name" class="mt-1 text-xs text-red-700">{{ tokenForm.errors.name }}</p>
            </section>

            <section v-if="webhooks" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Notifications sortantes</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Vulkain prévient une autre application (intra-Chatel…) à chaque prise et fin de service.
                    Chaque envoi est signé avec le secret : collez-le dans l'application destinataire pour qu'elle vérifie la signature.
                </p>

                <div v-if="webhooks.new_secret" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">Copiez ce secret maintenant : il ne sera plus affiché.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <code class="break-all rounded bg-white px-2 py-1 font-mono text-xs text-gray-900">{{ webhooks.new_secret }}</code>
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="copySecret">
                            {{ secretCopied ? 'Copié' : 'Copier' }}
                        </button>
                    </div>
                </div>

                <table v-if="webhooks.endpoints.length" class="mt-5 w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3 font-semibold">Nom</th>
                            <th class="py-2 pr-3 font-semibold">Adresse</th>
                            <th class="py-2 pr-3 font-semibold">Dernier envoi</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in webhooks.endpoints" :key="e.id" class="border-t border-gray-100 text-gray-800">
                            <td class="py-2 pr-3">{{ e.name }}</td>
                            <td class="max-w-xs truncate py-2 pr-3 font-mono text-xs" :title="e.url">{{ e.url }}</td>
                            <td class="py-2 pr-3 text-xs">
                                <span v-if="e.last_failure_at && (!e.last_success_at || e.last_failure_at > e.last_success_at)" class="text-red-700" :title="e.last_error">Échec {{ fmt(e.last_failure_at) }}</span>
                                <span v-else-if="e.last_success_at" class="text-green-700">Reçu {{ fmt(e.last_success_at) }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="whitespace-nowrap py-2 text-right">
                                <button type="button" class="mr-3 text-xs font-semibold text-gray-700 hover:underline" @click="testWebhook(e)">Tester</button>
                                <button type="button" class="text-xs font-semibold text-red-700 hover:underline" @click="deleteWebhook(e)">Supprimer</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-5 flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-900">Nom</span>
                        <input v-model="webhookForm.name" type="text" maxlength="100" class="mt-1 block w-48 rounded-lg border-gray-300 px-3 py-2.5 text-sm" />
                    </label>
                    <label class="block min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900">Adresse de réception</span>
                        <input v-model="webhookForm.url" type="url" maxlength="500" placeholder="https://intra-chatel.fr/api/vulkain/webhook" class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" />
                    </label>
                    <button
                        type="button"
                        :disabled="webhookForm.processing || !webhookForm.name.trim() || !webhookForm.url.trim()"
                        class="rounded-lg bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60"
                        @click="createWebhook"
                    >
                        Ajouter
                    </button>
                </div>
                <p v-if="webhookForm.errors.url || webhookForm.errors.name" class="mt-1 text-xs text-red-700">{{ webhookForm.errors.url || webhookForm.errors.name }}</p>
            </section>
        </div>
    </AppLayout>
</template>
