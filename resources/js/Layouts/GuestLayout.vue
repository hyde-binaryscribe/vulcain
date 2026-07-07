<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const tenant = computed(() => page.props.tenant);
const profile = computed(() => tenant.value?.profile);
const brand = computed(() => profile.value?.theme || '#991b1b');
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-4 py-12" :style="{ '--brand': brand }">
        <div class="mb-6 flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--brand)] text-lg font-bold text-white">V</span>
            <div class="text-center">
                <p class="text-lg font-semibold tracking-tight text-gray-900">Vulcain</p>
                <p v-if="tenant" class="text-sm text-gray-500">{{ tenant.name }}</p>
            </div>
        </div>

        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <slot />
        </div>

        <p class="mt-6 text-xs text-gray-400">{{ profile?.tagline || 'Inventaire opérationnel' }}</p>
    </div>
</template>
