<script setup>
import { onMounted, ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    vehicleId: { type: [Number, String], required: true },
    label: { type: String, default: '' },
});

const svg = ref('');
const url = ref('');

onMounted(async () => {
    url.value = `${window.location.origin}/t/vehicules/${props.vehicleId}/prise-de-service`;
    try {
        const QRCode = (await import('qrcode')).default;
        svg.value = await QRCode.toString(url.value, { type: 'svg', margin: 1, width: 220 });
    } catch (e) {
        svg.value = '';
    }
});

function printLabel() {
    const w = window.open('', '_blank', 'width=480,height=640');
    if (!w) return;
    w.document.write(`<!doctype html><html><head><title>QR ${props.label}</title>
        <style>
            body { font-family: system-ui, sans-serif; text-align: center; padding: 32px; }
            .name { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
            .sub { font-size: 12px; color: #666; margin: 0 0 20px; }
            svg { width: 260px; height: 260px; }
            .hint { font-size: 11px; color: #888; margin-top: 16px; }
        </style></head><body>
            <p class="name">${props.label || 'Véhicule'}</p>
            <p class="sub">Vulkain — accès véhicule</p>
            ${svg.value}
            <p class="hint">Scannez ce QR code pour ouvrir la fiche du véhicule.</p>
            <script>window.onload = function(){ window.print(); }<\/script>
        </body></html>`);
    w.document.close();
}
</script>

<template>
    <div class="flex flex-col items-center gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="flex items-center gap-2 self-start text-sm font-semibold uppercase tracking-wide text-gray-500">
            <Icon name="pin" :size="15" /> QR d'accès véhicule
        </h2>
        <div v-if="svg" class="h-40 w-40" v-html="svg"></div>
        <div v-else class="flex h-40 w-40 items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400">Génération…</div>
        <p class="break-all text-center text-[11px] text-gray-400">{{ url }}</p>
        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printLabel">
            <Icon name="printer" :size="15" /> Imprimer l'étiquette
        </button>
        <p class="text-center text-xs text-gray-500">À coller dans le véhicule. Le personnel scanne pour ouvrir la fiche.</p>
    </div>
</template>
