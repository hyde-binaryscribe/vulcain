<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    template: { type: Object, required: true },
    materials: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    frequencies: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
});

// Réglages du modèle (dont la cible / périmètre).
const settings = useForm({
    name: props.template.name,
    types: [...(props.template.types ?? [])],
    scope_type: props.template.scope_type,
    scope_id: props.template.scope_id ?? '',
    include_children: props.template.include_children,
    frequency: props.template.frequency,
    custom_days: props.template.custom_days ?? '',
    is_active: props.template.is_active,
});

const scopeChoices = computed(() => (settings.scope_type === 'location' ? props.locations : props.vehicles));

function toggleType(value) {
    const i = settings.types.indexOf(value);
    if (i === -1) settings.types.push(value);
    else settings.types.splice(i, 1);
}
function onScopeTypeChange() {
    settings.scope_id = '';
}
function saveSettings() {
    settings.transform((d) => ({ ...d, custom_days: d.custom_days || null }))
        .patch(`/templates/${props.template.id}`, { preserveScroll: true });
}

// Périmètre : inclusion / exclusion des matériels.
const included = reactive(Object.fromEntries(props.materials.map((m) => [m.id, !m.excluded])));
function saveExclusions() {
    const excluded = props.materials.filter((m) => !included[m.id]).map((m) => m.id);
    router.patch(`/templates/${props.template.id}/exclusions`, { excluded_material_ids: excluded }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="template.name" />
        <template #title>{{ template.name }}</template>

        <Link href="/templates" class="text-sm text-[var(--brand)] hover:underline">← Modèles</Link>

        <div class="mt-3 grid gap-6 lg:grid-cols-3">
            <!-- Réglages -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Réglages</h2>
                <p class="text-xs text-gray-500">Cible : {{ template.target }} · v{{ template.version }}</p>
                <form class="mt-4 space-y-4" @submit.prevent="saveSettings">
                    <div><InputLabel value="Nom" /><TextInput v-model="settings.name" /></div>
                    <div>
                        <InputLabel value="Types de protocole" />
                        <div class="mt-1 space-y-1.5">
                            <label v-for="opt in typeOptions" :key="opt.value" class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300" :checked="settings.types.includes(opt.value)" @change="toggleType(opt.value)" />
                                {{ opt.label }}
                            </label>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Cible" />
                        <select v-model="settings.scope_type" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" @change="onScopeTypeChange">
                            <option v-for="s in scopeOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel :value="settings.scope_type === 'location' ? 'Emplacement' : 'Véhicule'" />
                        <select v-model="settings.scope_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="">Choisir…</option>
                            <option v-for="c in scopeChoices" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <label v-if="settings.scope_type === 'location'" class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="settings.include_children" type="checkbox" class="rounded border-gray-300" />
                        Inclure les emplacements enfants
                    </label>
                    <div>
                        <InputLabel value="Fréquence" />
                        <select v-model="settings.frequency" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>
                    <div v-if="settings.frequency === 'custom'">
                        <InputLabel value="Tous les (jours)" />
                        <TextInput v-model="settings.custom_days" type="number" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="settings.is_active" type="checkbox" class="rounded border-gray-300" /> Actif
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110">Enregistrer</button>
                </form>
            </section>

            <!-- Périmètre -->
            <section class="lg:col-span-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Matériel du périmètre</h3>
                            <p class="text-xs text-gray-500">Décoche un matériel pour l'exclure du protocole.</p>
                        </div>
                        <button class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white" @click="saveExclusions">Enregistrer le périmètre</button>
                    </div>
                </div>

                <div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-3">Inclus</th><th class="px-4 py-3">Matériel</th><th class="px-4 py-3">Emplacement</th><th class="px-4 py-3">Suivi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in materials" :key="m.id" :class="included[m.id] ? '' : 'opacity-50'">
                                <td class="px-4 py-3">
                                    <input v-model="included[m.id]" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ m.name }}</div>
                                    <div class="text-xs text-gray-400">{{ m.reference }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ m.location ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ m.tracking_mode }}</td>
                            </tr>
                            <tr v-if="materials.length === 0"><td colspan="4" class="px-4 py-8 text-center text-gray-500">Aucun matériel dans ce périmètre. Range du matériel dans les emplacements ciblés.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
