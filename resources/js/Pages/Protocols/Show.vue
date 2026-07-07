<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    protocol: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({ checked: 0, total: 0 }) },
    states: { type: Array, default: () => [] },
    serialStates: { type: Array, default: () => [] },
});

// État local (préserve la saisie pendant les enregistrements en tâche de fond).
const sections = reactive(JSON.parse(JSON.stringify(props.groups)));
const editable = props.protocol.editable;

const stateStyles = {
    conforme: 'bg-green-700 text-white border-green-700',
    manquant: 'bg-amber-500 text-white border-amber-500',
    hs: 'bg-red-700 text-white border-red-700',
    a_remplacer: 'bg-orange-600 text-white border-orange-600',
    present: 'bg-green-700 text-white border-green-700',
    absent: 'bg-red-700 text-white border-red-700',
    present_anomalie: 'bg-orange-600 text-white border-orange-600',
};

const checkedCount = computed(() =>
    sections.reduce((n, s) => n + s.items.filter((i) => i.checked).length, 0),
);
const totalCount = computed(() => sections.reduce((n, s) => n + s.items.length, 0));
const pct = computed(() => (totalCount.value ? Math.round((checkedCount.value / totalCount.value) * 100) : 0));

function save(item) {
    if (!editable) return;
    item.checked = true;
    const data = {
        observed_qty: item.observed_qty,
        state: item.state,
        observation: item.observation,
    };
    if (item.tracking_mode === 'lot') data.observed_expiry = item.observed_expiry;
    const opts = { preserveState: true, preserveScroll: true };
    if (item._photoFile) {
        data.photo = item._photoFile;
        opts.forceFormData = true;
    }
    router.patch(`/protocols/${props.protocol.id}/items/${item.id}`, data, opts);
}

function adjust(item, delta) {
    const base = Number.isFinite(item.observed_qty) ? item.observed_qty : (item.expected_qty ?? 0);
    item.observed_qty = Math.max(0, base + delta);
    save(item);
}

function setState(item, state) {
    item.state = state;
    if (item.tracking_mode === 'quantity' && (item.observed_qty === null || item.observed_qty === undefined)) {
        item.observed_qty = state === 'conforme' ? item.expected_qty : item.observed_qty;
    }
    save(item);
}

function useLastExpiry(item) {
    item.observed_expiry = item.last_known_expiry;
    save(item);
}

function onPhoto(item, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    item._photoFile = file;
    save(item);
}

function fmt(date) {
    if (!date) return '';
    const [y, m, d] = date.split('-');
    return `${d}/${m}/${y}`;
}
</script>

<template>
    <AppLayout>
        <Head title="Protocole" />
        <template #title>Protocole — {{ protocol.vehicle_name }}</template>

        <Link href="/protocols" class="text-sm text-[var(--brand)] hover:underline">← Protocoles</Link>

        <!-- En-tête + progression -->
        <div class="mt-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="mb-1 flex flex-wrap gap-1">
                        <span v-for="label in protocol.type_labels" :key="label" class="rounded-full bg-[var(--brand)]/10 px-2 py-0.5 text-xs font-medium text-[var(--brand)]">{{ label }}</span>
                    </div>
                    <p class="text-sm text-gray-500">{{ protocol.template_name }} · {{ protocol.verifier }} · {{ protocol.started_at }}</p>
                    <span v-if="protocol.status === 'validated'" class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Validé — lecture seule</span>
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
                        <!-- ===== MATÉRIEL SÉRIE ===== -->
                        <template v-if="item.tracking_mode === 'serial'">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ item.material_name }}</p>
                                    <p class="text-xs text-gray-500">N° série : <span class="font-mono font-medium text-gray-700">{{ item.serial_number ?? '—' }}</span></p>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="s in serialStates"
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
                            <!-- Anomalie : observation + photo facultative -->
                            <div v-if="item.state === 'present_anomalie' || item.state === 'absent'" class="mt-3 space-y-2">
                                <input
                                    v-model="item.observation"
                                    type="text"
                                    :disabled="!editable"
                                    placeholder="Décrire l'anomalie…"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-50"
                                    @change="save(item)"
                                />
                                <div class="flex items-center gap-3">
                                    <label class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                        📷 Photo (facultative)
                                        <input type="file" accept="image/*" class="hidden" :disabled="!editable" @change="onPhoto(item, $event)" />
                                    </label>
                                    <a v-if="item.photo_url" :href="item.photo_url" target="_blank" class="text-xs text-[var(--brand)] hover:underline">Voir la photo</a>
                                </div>
                            </div>
                        </template>

                        <!-- ===== CONSOMMABLE (lot / péremption) ===== -->
                        <template v-else-if="item.tracking_mode === 'lot'">
                            <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ item.material_name }}</p>
                                    <p class="text-xs text-gray-500">{{ item.reference }} · en stock (théorique) : {{ item.expected_qty }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" :disabled="!editable" class="h-10 w-10 rounded-lg border border-gray-300 text-lg font-bold text-gray-600 disabled:opacity-40" @click="adjust(item, -1)">−</button>
                                    <input v-model.number="item.observed_qty" type="number" :disabled="!editable" :placeholder="item.expected_qty" class="h-10 w-20 rounded-lg border border-gray-300 text-center text-lg font-semibold disabled:bg-gray-50" @change="save(item)" />
                                    <button type="button" :disabled="!editable" class="h-10 w-10 rounded-lg border border-gray-300 text-lg font-bold text-gray-600 disabled:opacity-40" @click="adjust(item, 1)">+</button>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Péremption la plus proche <span v-if="item.expiry_required" class="text-red-500">*</span></label>
                                    <input v-model="item.observed_expiry" type="date" :disabled="!editable" class="mt-1 rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-50" @change="save(item)" />
                                </div>
                                <button v-if="item.last_known_expiry && editable" type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50" @click="useLastExpiry(item)">
                                    Dernière connue : {{ fmt(item.last_known_expiry) }}
                                </button>
                                <span v-else-if="item.last_known_expiry" class="text-xs text-gray-400">Dernière connue : {{ fmt(item.last_known_expiry) }}</span>
                            </div>
                        </template>

                        <!-- ===== QUANTITÉ ===== -->
                        <template v-else>
                            <div class="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-center">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ item.material_name }}</p>
                                    <p class="text-xs text-gray-500">{{ item.reference }} · quantité prévue : {{ item.expected_qty }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" :disabled="!editable" class="h-10 w-10 rounded-lg border border-gray-300 text-lg font-bold text-gray-600 disabled:opacity-40" @click="adjust(item, -1)">−</button>
                                    <input v-model.number="item.observed_qty" type="number" :disabled="!editable" :placeholder="item.expected_qty" class="h-10 w-20 rounded-lg border border-gray-300 text-center text-lg font-semibold disabled:bg-gray-50" @change="save(item)" />
                                    <button type="button" :disabled="!editable" class="h-10 w-10 rounded-lg border border-gray-300 text-lg font-bold text-gray-600 disabled:opacity-40" @click="adjust(item, 1)">+</button>
                                </div>
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
                            <div class="mt-3">
                                <input v-model="item.observation" type="text" :disabled="!editable" placeholder="Observation (obligatoire si anomalie)" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-50" @change="save(item)" />
                            </div>
                        </template>
                    </article>
                </div>
            </section>

            <p v-if="sections.length === 0" class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                Ce protocole ne contient aucun élément (le périmètre était vide).
            </p>
        </div>
    </AppLayout>
</template>
