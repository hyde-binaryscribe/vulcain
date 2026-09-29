<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    damages: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
});
const emit = defineEmits(['add', 'select']);

const views = [
    { key: 'gauche', label: 'Côté gauche' },
    { key: 'droite', label: 'Côté droit' },
    { key: 'avant', label: 'Avant' },
    { key: 'arriere', label: 'Arrière' },
    { key: 'dessus', label: 'Dessus' },
];
const active = ref('gauche');

const pointsForView = computed(() =>
    props.damages
        .map((d, i) => ({ ...d, num: i + 1 }))
        .filter((d) => d.view === active.value),
);
const openCount = (key) => props.damages.filter((d) => d.view === key && d.status === 'ouverte').length;

function onCanvasClick(e) {
    if (!props.editable) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const x = Math.min(100, Math.max(0, ((e.clientX - rect.left) / rect.width) * 100));
    const y = Math.min(100, Math.max(0, ((e.clientY - rect.top) / rect.height) * 100));
    emit('add', { view: active.value, x: Math.round(x * 100) / 100, y: Math.round(y * 100) / 100 });
}
</script>

<template>
    <div>
        <!-- Onglets de vue -->
        <div class="-mx-1 flex gap-1 overflow-x-auto pb-1">
            <button
                v-for="v in views"
                :key="v.key"
                type="button"
                class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium"
                :class="active === v.key ? 'bg-[var(--brand,#C6362B)] text-white' : 'bg-gray-100 text-gray-600'"
                @click="active = v.key"
            >
                {{ v.label }}
                <span v-if="openCount(v.key)" class="rounded-full px-1.5 text-[10px] font-bold"
                    :class="active === v.key ? 'bg-white/25 text-white' : 'bg-red-100 text-red-700'">{{ openCount(v.key) }}</span>
            </button>
        </div>

        <!-- Schéma -->
        <div
            class="relative mt-2 w-full select-none rounded-xl border border-gray-200 bg-gray-50 text-gray-300"
            style="aspect-ratio: 320 / 200"
            :class="editable ? 'cursor-crosshair' : ''"
            @click="onCanvasClick"
        >
            <svg viewBox="0 0 320 200" class="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid meet" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round">
                <!-- Côté (gauche = avant à gauche ; droite = miroir) -->
                <g v-if="active === 'gauche' || active === 'droite'" :transform="active === 'droite' ? 'translate(320,0) scale(-1,1)' : ''">
                    <path d="M18,142 V96 C18,80 30,68 52,66 L96,66 L122,44 H250 C276,44 293,62 295,96 V142" />
                    <line x1="18" y1="142" x2="295" y2="142" />
                    <path d="M122,66 L140,48 H185 V66 Z" />
                    <line x1="205" y1="48" x2="205" y2="66" />
                    <circle cx="82" cy="150" r="17" fill="var(--surface,#fff)" />
                    <circle cx="240" cy="150" r="17" fill="var(--surface,#fff)" />
                </g>
                <!-- Avant -->
                <g v-else-if="active === 'avant'">
                    <rect x="104" y="26" width="112" height="150" rx="16" />
                    <rect x="118" y="36" width="84" height="46" rx="8" />
                    <circle cx="122" cy="120" r="9" />
                    <circle cx="198" cy="120" r="9" />
                    <rect x="112" y="150" width="96" height="16" rx="6" />
                    <line x1="140" y1="100" x2="180" y2="100" />
                </g>
                <!-- Arrière -->
                <g v-else-if="active === 'arriere'">
                    <rect x="104" y="26" width="112" height="150" rx="16" />
                    <line x1="160" y1="34" x2="160" y2="168" />
                    <rect x="116" y="44" width="88" height="40" rx="6" />
                    <rect x="116" y="110" width="12" height="26" rx="3" />
                    <rect x="192" y="110" width="12" height="26" rx="3" />
                    <rect x="112" y="150" width="96" height="16" rx="6" />
                </g>
                <!-- Dessus -->
                <g v-else>
                    <rect x="28" y="52" width="264" height="96" rx="30" />
                    <path d="M96,52 C110,74 110,126 96,148" />
                    <line x1="160" y1="52" x2="160" y2="148" />
                </g>
            </svg>

            <!-- Points -->
            <button
                v-for="p in pointsForView"
                :key="p.id"
                type="button"
                class="absolute flex h-6 w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white text-[11px] font-bold text-white shadow"
                :class="p.status === 'ouverte' ? 'bg-red-600' : 'bg-green-600'"
                :style="{ left: p.pos_x + '%', top: p.pos_y + '%' }"
                :title="p.description"
                @click.stop="emit('select', p)"
            >{{ p.num }}</button>
        </div>

        <p v-if="editable" class="mt-1.5 text-center text-[11px] text-gray-400">Touchez le schéma à l'endroit du choc pour signaler une anomalie.</p>
    </div>
</template>
