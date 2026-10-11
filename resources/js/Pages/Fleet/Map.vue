<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    vehicles: { type: Array, default: () => [] },
    located: { type: Number, default: 0 },
});

const located = computed(() => props.vehicles.filter((v) => v.position));
const unlocated = computed(() => props.vehicles.filter((v) => !v.position));

// Couleur du marqueur : rouge si défaut OBD, bleu si en mouvement, gris sinon.
function markerColor(v) {
    if (v.position?.fault_count > 0) return '#d03b3b';
    if ((v.position?.speed ?? 0) > 3) return '#2a78d6';
    return '#6b7280';
}

const el = ref(null);
let map = null;
const markers = {};

function popupHtml(v) {
    const p = v.position;
    const spd = p.speed != null ? `${p.speed} km/h` : '—';
    const dtc = p.fault_count > 0 ? `<br><b style="color:#d03b3b">Défaut OBD${p.dtcs ? ' : ' + p.dtcs : ''}</b>` : '';
    return `<b>${v.name}</b><br>${p.ago ?? ''} · ${spd}${dtc}<br><a href="/vehicles/${v.id}#telematics">Fiche ›</a>`;
}

function focus(v) {
    if (!map || !v.position) return;
    map.setView([v.position.lat, v.position.lon], 15);
    markers[v.id]?.openPopup();
}

onMounted(() => {
    map = L.map(el.value, { scrollWheelZoom: true }).setView([46.6, 2.5], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);

    const bounds = [];
    located.value.forEach((v) => {
        const m = L.circleMarker([v.position.lat, v.position.lon], {
            radius: 9, color: '#fff', weight: 2, fillColor: markerColor(v), fillOpacity: 1,
        }).addTo(map).bindPopup(popupHtml(v));
        markers[v.id] = m;
        bounds.push([v.position.lat, v.position.lon]);
    });
    if (bounds.length === 1) map.setView(bounds[0], 14);
    else if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40] });
});
onBeforeUnmount(() => { if (map) { map.remove(); map = null; } });
</script>

<template>
    <AppLayout>
        <Head title="Suivi flotte" />
        <template #title>Suivi flotte</template>

        <div v-if="vehicles.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
            Aucun véhicule équipé d'un boîtier télématique. Renseignez l'IMEI sur une fiche véhicule pour le suivre ici.
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-[320px_1fr]">
            <!-- Liste -->
            <div class="order-2 lg:order-1">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ located.length }} localisé(s) · {{ vehicles.length }} équipé(s)</p>
                <div class="space-y-2">
                    <button
                        v-for="v in located"
                        :key="v.id"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 text-left shadow-sm transition hover:border-[var(--brand)]/40"
                        @click="focus(v)"
                    >
                        <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: markerColor(v) }"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-gray-900">{{ v.name }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ v.position.ago }} · {{ v.position.speed != null ? v.position.speed + ' km/h' : '—' }}</span>
                        </span>
                        <span v-if="v.position.fault_count > 0" class="shrink-0 rounded-full bg-red-100 px-1.5 py-0.5 text-[11px] font-bold text-red-700">OBD</span>
                    </button>
                    <div v-for="v in unlocated" :key="v.id" class="flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white/60 p-3">
                        <span class="h-3 w-3 shrink-0 rounded-full bg-gray-300"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-700">{{ v.name }}</span>
                            <span class="block text-xs text-gray-400">En attente de position</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Carte -->
            <div class="order-1 lg:order-2">
                <div ref="el" class="w-full rounded-2xl border border-gray-200" style="height: 72vh; z-index: 0"></div>
                <p class="mt-2 text-xs text-gray-400">Dernière position connue de chaque véhicule · fond © OpenStreetMap. Clic sur un véhicule pour le centrer.</p>
            </div>
        </div>
    </AppLayout>
</template>
