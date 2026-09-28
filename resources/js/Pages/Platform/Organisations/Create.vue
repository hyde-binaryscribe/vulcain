<script setup>
import { watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    sectors: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    slug: '',
    sector: props.sectors[0]?.value ?? '',
    admin_email: '',
    // Par défaut : identifiants générés (l'envoi d'e-mails n'est pas encore configuré).
    provisioning_mode: 'credentials',
});

// Suggère un sous-domaine à partir du nom.
watch(
    () => form.name,
    (value) => {
        if (!form.slugTouched) {
            form.slug = value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 63);
        }
    },
);

function submit() {
    form.post('/platform/organisations');
}
</script>

<template>
    <PlatformLayout>
        <Head title="Desk — Nouvelle organisation" />

        <div class="mb-6">
            <Link href="/platform" class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline"><Icon name="arrow-left" :size="16" /> Retour</Link>
            <h1 class="mt-2 text-xl font-semibold text-slate-900">Nouvelle organisation</h1>
            <p class="text-sm text-slate-500">Crée le client, provisionne ses rôles et met en route son premier administrateur.</p>
        </div>

        <form class="max-w-xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <InputLabel value="Nom de l’organisation" />
                <TextInput v-model="form.name" autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <InputLabel value="Identifiant" />
                <TextInput v-model="form.slug" @input="form.slugTouched = true" />
                <InputError :message="form.errors.slug" />
                <p class="mt-1 text-xs text-gray-500">Identifiant interne unique (minuscules, chiffres, tirets).</p>
            </div>

            <div>
                <InputLabel value="Secteur" />
                <select
                    v-model="form.sector"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/30"
                >
                    <option v-for="s in sectors" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <InputError :message="form.errors.sector" />
            </div>

            <div>
                <InputLabel value="E-mail du premier administrateur" />
                <TextInput v-model="form.admin_email" type="email" />
                <InputError :message="form.errors.admin_email" />
            </div>

            <div>
                <InputLabel value="Mise en route du compte administrateur" />
                <div class="mt-2 space-y-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3" :class="form.provisioning_mode === 'credentials' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'">
                        <input v-model="form.provisioning_mode" type="radio" value="credentials" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Générer les identifiants</span>
                            <span class="block text-xs text-slate-500">Crée le compte et un mot de passe temporaire, affiché une fois — à communiquer manuellement. Aucun e-mail requis.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3" :class="form.provisioning_mode === 'invitation' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'">
                        <input v-model="form.provisioning_mode" type="radio" value="invitation" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Envoyer une invitation par e-mail</span>
                            <span class="block text-xs text-slate-500">L'administrateur définit lui-même son mot de passe (nécessite un SMTP configuré).</span>
                        </span>
                    </label>
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-indigo-600 px-4 py-2.5 font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
            >
                {{ form.provisioning_mode === 'credentials' ? 'Créer et générer les identifiants' : 'Créer et inviter' }}
            </button>
        </form>
    </PlatformLayout>
</template>
