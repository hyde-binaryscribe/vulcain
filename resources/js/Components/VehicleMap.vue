<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    // Positions les plus récentes d'abord : [{ lat, lon, device_time }]
    positions: { type: Array, default: () => [] },
});

const el = ref(null);
let map = null;
let layer = null;

function draw() {
    if (!map) return;
    if (layer) layer.remove();
    layer = L.layerGroup().addTo(map);

    const pts = props.positions.filter((p) => p.lat != null && p.lon != null);
    if (pts.length === 0) return;

    const latlngs = pts.map((p) => [p.lat, p.lon]);
    if (latlngs.length > 1) {
        L.polyline(latlngs, { color: '#C6362B', weight: 3, opacity: 0.5 }).addTo(layer);
    }
    const last = pts[0];
    L.circleMarker([last.lat, last.lon], { radius: 9, color: '#fff', weight: 2, fillColor: '#C6362B', fillOpacity: 1 })
        .addTo(layer)
        .bindPopup(`Dernière position<br>${last.device_time ?? ''}`);
    map.setView([last.lat, last.lon], 14);
}

onMounted(() => {
    map = L.map(el.value, { scrollWheelZoom: false }).setView([46.6, 2.5], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);
    draw();
});
watch(() => props.positions, draw, { deep: true });
onBeforeUnmount(() => { if (map) { map.remove(); map = null; } });
</script>

<template>
    <div ref="el" class="h-72 w-full rounded-xl border border-gray-200" style="z-index: 0"></div>
</template>
