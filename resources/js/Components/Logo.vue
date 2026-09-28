<script setup>
/**
 * Logo Vulkain — plaque forgée + chevron « V » de braise (qui se lit aussi
 * comme le ✓ de vérification) surmonté d'une étincelle.
 * Variante couleur par défaut ; `mono` pour un aplat monochrome (currentColor).
 */
defineProps({
    size: { type: [Number, String], default: 32 },
    word: { type: Boolean, default: false },
    wordClass: { type: String, default: '' },
    mono: { type: Boolean, default: false },
});

// Identifiant unique par instance (évite les collisions de dégradés SVG).
const uid = Math.random().toString(36).slice(2, 8);
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <svg :width="size" :height="size" viewBox="0 0 64 64" role="img" aria-label="Vulkain">
            <template v-if="!mono">
                <defs>
                    <linearGradient :id="`vk-e-${uid}`" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#F7883A" />
                        <stop offset="1" stop-color="#C6362B" />
                    </linearGradient>
                    <linearGradient :id="`vk-p-${uid}`" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#20272F" />
                        <stop offset="1" stop-color="#0C0F13" />
                    </linearGradient>
                </defs>
                <rect x="3" y="3" width="58" height="58" rx="15" :fill="`url(#vk-p-${uid})`" />
                <rect x="3" y="3" width="58" height="58" rx="15" fill="none" stroke="#2A333D" stroke-width="1" />
                <path d="M17 21 L32 45 L47 21" fill="none" :stroke="`url(#vk-e-${uid})`" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M32 9 l4.4 6.6 -4.4 4.4 -4.4 -4.4 z" fill="#F7883A" />
            </template>
            <template v-else>
                <rect x="3" y="3" width="58" height="58" rx="15" fill="none" stroke="currentColor" stroke-width="2.5" />
                <path d="M17 21 L32 45 L47 21" fill="none" stroke="currentColor" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M32 9 l4.4 6.6 -4.4 4.4 -4.4 -4.4 z" fill="currentColor" />
            </template>
        </svg>
        <span v-if="word" class="font-display font-extrabold tracking-wide" :class="wordClass">VULKAIN</span>
    </span>
</template>
