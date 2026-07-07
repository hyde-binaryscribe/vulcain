<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    inventory: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({ checked: 0, total: 0 }) },
    states: { type: Array, default: () => [] },
});

// État local (préserve la saisie pendant les enregistrements en tâche de fond).
const sections = reactive(JSON.parse(JSON.stringify(props.groups)));
const editable = props.inventory.editable;

const stateStyles = {
    conforme: 'bg-green-700 text-white border-green-700',
    manquant: 'bg-amber-500 text-white border-amber-500',
    hs: 'bg-red-700 text-white border-red-700',
    a_remplacer: 'bg-orange-600 text-white border-orange-600',
};

const checkedCount = computed(() =>
    sections.reduce((n, s) => n + s.items.filter((i) => i.checked).length, 0),
);
const totalCount = computed(() => sections.reduce((n, s) => n + s.items.length, 0));
const pct = computed(() => (totalCount.value ? Math.round((checkedCount.value / totalCount.value) * 100) : 0));

function save(item) {
    if (!editable) return;
    item.checked = true;
    router.patch(`/inventories/${props.inventory.id}/items/${item.id}`, {
        observed_qty: item.observed_qty,
        state: item.state,
        observation: item.observation,
    }, { preserveState: true, preserveScroll: true });
}

function adjust(item, delta) {
    const base = Number.isFinite(item.observed_qty) ? item.observed_qty : (item.expected_qty ?? 0);
    item.observed_qty = Math.max(0, base + delta);
    save(item);
}

function setState(item, state) {
    item.state = state;
    if (item.observed_qty === null || item.observed_qty === undefined) {
        item.observed_qty = state === 'conforme' ? item.expected_qty : item.observed_qty;
    }
    save(item);
}
</script>

<template>
    <AppLayout>
        <Head title="Inventaire" />
        <template #title>Inventaire — {{ inventory.vehicle_name }}</template>

        <Link href="/inventories" class="text-sm text-[var(--brand)] hover:underline">← Inventaires</Link>

        <!-- En-tête + progression -->
        <div class="mt-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-500">{{ inventory.template_name }} · {{ inventory.verifier }} · {{ inventory.started_at }}</p>
                    <span v-if="inventory.status === 'validated'" class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Validé — lecture seule</span>
                    <span v-else-if="!editable" class="mt-1 inline-block rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">Consultation</span>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">{{ checkedCount }} / {{ totalCount }} contrôlés</p>
                    <div class="mt-1 h-2 w-40 overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full rounded-full bg-[var(--brand)] transition-all" :style="{ width: pct + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matériel par emplacement -->
        <div class="mt-6 space-y-8">
            <section v-for="section in sections" :key="section.location">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ section.location }}</h3>
                <div class="space-y-3">
                    <article
                        v-for="item in section.items"
                        :key="item.id"
                        class="rounded-2xl border bg-white p-4 shadow-sm"
                        :class="item.checked ? 'border-green-200' : 'border-gray-200'"
                    >
                        <div class="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-center">
                            <!-- Identité -->
                            <div>
                                <p class="font-semibold text-gray-900">{{ item.material_name }}</p>
                                <p class="text-xs text-gray-500">{{ item.reference }} · quantité prévue : {{ item.expected_qty }}</p>
                            </div>

                            <!-- Quantité présente -->
                            <div class="flex items-center gap-2">
                                <button type="button" :disabled="!editable" class="h-10 w-10 rounded-lg border border-gray-300 text-lg font-bold text-gray-600 disabled:opacity-40" @click="adjust(item, -1)">−</button>
                                <input
                                    v-model.number="item.observed_qty"
                                    type="number"
                                    :disabled="!editable"
                                    :placeholder="item.expected_qty"
                                    class="h-10 w-20 rounded-lg border border-gray-300 text-center text-lg font-semibold disabled:bg-gray-50"
                                    @change="save(item)"
                                />
                                <button type="button" :disabled="!editable" class="h-10 w-10 rounded-lg border border-gray-300 text-lg font-bold text-gray-600 disabled:opacity-40" @click="adjust(item, 1)">+</button>
                            </div>

                            <!-- États -->
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="s in states"
                                    :key="s.value"
                                    type="button"
                                    :disabled="!editable"
                                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition disabled:opacity-50"
                                    :class="item.state === s.value ? stateStyles[s.value] : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                    @click="setState(item, s.value)"
                                >
                                    {{ s.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Observation -->
                        <div class="mt-3">
                            <input
                                v-model="item.observation"
                                type="text"
                                :disabled="!editable"
                                placeholder="Observation (obligatoire si anomalie)"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-50"
                                @change="save(item)"
                            />
                        </div>
                    </article>
                </div>
            </section>

            <p v-if="sections.length === 0" class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                Cet inventaire ne contient aucun élément (le modèle était vide).
            </p>
        </div>
    </AppLayout>
</template>
