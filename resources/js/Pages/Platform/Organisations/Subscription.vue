<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    organisation: { type: Object, required: true },
    subscription: { type: Object, required: true },
    usage: { type: Object, required: true },
    plans: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const form = useForm({
    plan: props.subscription.plan,
    status: props.subscription.status,
    current_period_end: props.subscription.current_period_end ?? '',
    notes: props.subscription.notes ?? '',
});

const selectedPlan = computed(() => props.plans.find((p) => p.value === form.plan));

function fmtLimit(v) {
    return v === null || v === undefined ? '∞' : v;
}
function usageRows() {
    const limits = selectedPlan.value?.limits ?? {};
    return [
        { key: 'users', label: 'Utilisateurs', used: props.usage.users.used, limit: limits.users },
        { key: 'vehicles', label: 'Véhicules', used: props.usage.vehicles.used, limit: limits.vehicles },
        { key: 'sites', label: 'Sites', used: props.usage.sites.used, limit: limits.sites },
    ];
}
function over(row) {
    return row.limit !== null && row.limit !== undefined && row.used > row.limit;
}

function save() {
    form.transform((d) => ({ ...d, current_period_end: d.current_period_end || null }))
        .patch(`/platform/organisations/${props.organisation.id}/subscription`, { preserveScroll: true });
}
</script>

<template>
    <PlatformLayout>
        <Head :title="`Abonnement — ${organisation.name}`" />

        <Link href="/platform" class="text-sm text-indigo-600 hover:underline">← Organisations</Link>

        <div class="mt-3 mb-6">
            <h1 class="text-xl font-semibold text-slate-900">Abonnement — {{ organisation.name }}</h1>
            <p class="text-sm text-slate-500">{{ organisation.sector_label }} · {{ organisation.slug }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Réglages abonnement -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-base font-semibold text-slate-900">Réglages</h2>
                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Plan</label>
                        <select v-model="form.plan" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }} — {{ p.price }} €/mois</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Statut</label>
                        <select v-model="form.status" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Prochaine échéance</label>
                        <input v-model="form.current_period_end" type="date" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600">Notes commerciales</label>
                        <textarea v-model="form.notes" rows="3" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Enregistrer l'abonnement</button>
                    </div>
                </form>
            </section>

            <!-- Usage vs limites du plan sélectionné -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Usage vs limites</h2>
                <p class="mb-3 text-xs text-slate-500">Plan {{ selectedPlan?.label }}</p>
                <ul class="space-y-3">
                    <li v-for="row in usageRows()" :key="row.key">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">{{ row.label }}</span>
                            <span :class="over(row) ? 'font-semibold text-red-600' : 'text-slate-800'">{{ row.used }} / {{ fmtLimit(row.limit) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full"
                                :class="over(row) ? 'bg-red-500' : 'bg-indigo-500'"
                                :style="{ width: (row.limit ? Math.min(100, Math.round((row.used / row.limit) * 100)) : (row.used ? 12 : 0)) + '%' }"
                            ></div>
                        </div>
                    </li>
                </ul>
                <p v-if="usageRows().some(over)" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                    L'usage dépasse les limites de ce plan.
                </p>
            </section>
        </div>
    </PlatformLayout>
</template>
