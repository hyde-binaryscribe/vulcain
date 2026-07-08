<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const query = ref('');
const groups = ref([]);
const loading = ref(false);
const activeIndex = ref(0);
const inputRef = ref(null);
let debounce = null;
let requestId = 0;

// Liste à plat pour la navigation clavier.
const flat = computed(() => groups.value.flatMap((g) => g.results));

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        groups.value = [];
        activeIndex.value = 0;
        nextTick(() => inputRef.value?.focus());
    }
});

watch(query, (q) => {
    clearTimeout(debounce);
    if (!q.trim()) {
        groups.value = [];
        return;
    }
    debounce = setTimeout(() => fetchResults(q), 180);
});

async function fetchResults(q) {
    const id = ++requestId;
    loading.value = true;
    try {
        const res = await fetch(`/search/suggest?q=${encodeURIComponent(q)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        const data = await res.json();
        if (id === requestId) {
            groups.value = data.groups || [];
            activeIndex.value = 0;
        }
    } catch {
        if (id === requestId) groups.value = [];
    } finally {
        if (id === requestId) loading.value = false;
    }
}

function go(item) {
    if (!item) return;
    emit('close');
    router.visit(item.href);
}

function onKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, flat.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        go(flat.value[activeIndex.value]);
    } else if (e.key === 'Escape') {
        emit('close');
    }
}

// Index global (à plat) d'un résultat pour le surlignage.
function flatIndex(groupIdx, resIdx) {
    let n = 0;
    for (let i = 0; i < groupIdx; i++) n += groups.value[i].results.length;
    return n + resIdx;
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-[10vh]" @click.self="emit('close')">
        <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center gap-2 border-b border-gray-100 px-4">
                <span class="text-gray-400">🔍</span>
                <input
                    ref="inputRef"
                    v-model="query"
                    type="text"
                    placeholder="Rechercher matériel, véhicule, protocole, événement…"
                    class="w-full border-0 py-3.5 text-sm focus:outline-none focus:ring-0"
                    @keydown="onKeydown"
                />
                <kbd class="hidden rounded border border-gray-200 px-1.5 py-0.5 text-[10px] text-gray-400 sm:block">Esc</kbd>
            </div>

            <div class="max-h-[55vh] overflow-y-auto">
                <div v-if="loading && groups.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">Recherche…</div>

                <div v-for="(g, gi) in groups" :key="g.label" class="py-1">
                    <p class="px-4 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ g.label }}</p>
                    <button
                        v-for="(r, ri) in g.results"
                        :key="gi + '-' + ri"
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2 text-left text-sm"
                        :class="flatIndex(gi, ri) === activeIndex ? 'bg-[var(--brand)]/10' : 'hover:bg-gray-50'"
                        @mouseenter="activeIndex = flatIndex(gi, ri)"
                        @click="go(r)"
                    >
                        <span class="font-medium text-gray-900">{{ r.label }}</span>
                        <span class="ml-3 truncate text-xs text-gray-400">{{ r.sub }}</span>
                    </button>
                </div>

                <div v-if="!loading && query && groups.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                    Aucun résultat pour « {{ query }} ».
                </div>
                <div v-if="!query" class="px-4 py-6 text-center text-xs text-gray-400">
                    Tape pour rechercher. ↑↓ pour naviguer, Entrée pour ouvrir.
                </div>
            </div>
        </div>
    </div>
</template>
