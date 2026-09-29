<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const deferred = ref(null);
const visible = ref(false);
const mode = ref('android'); // 'android' (invite native) | 'ios' (instructions)
const DISMISS_KEY = 'vulkain.pwa.install.dismissed';

// iOS n'émet pas « beforeinstallprompt » : l'installation se fait à la main via
// Partager → « Sur l'écran d'accueil ». On détecte iOS hors mode installé pour
// afficher les instructions.
function isIosSafari() {
    const ua = navigator.userAgent || '';
    const iOS = /iphone|ipad|ipod/i.test(ua)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS
    if (!iOS) return false;
    // Autres navigateurs iOS (Chrome/Firefox) ne proposent pas « écran d'accueil ».
    return !/crios|fxios|edgios|opt\//i.test(ua);
}
function isStandalone() {
    return window.matchMedia?.('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function dismissed() {
    try {
        return localStorage.getItem(DISMISS_KEY) === '1';
    } catch (e) {
        return false;
    }
}
function remember() {
    try {
        localStorage.setItem(DISMISS_KEY, '1');
    } catch (e) { /* stockage indisponible : on ignore */ }
}

function onBeforeInstall(e) {
    e.preventDefault();
    deferred.value = e;
    mode.value = 'android';
    if (!dismissed()) visible.value = true;
}
function onInstalled() {
    visible.value = false;
    deferred.value = null;
}

async function install() {
    if (!deferred.value) return;
    deferred.value.prompt();
    try {
        await deferred.value.userChoice;
    } finally {
        visible.value = false;
        deferred.value = null;
    }
}
function close() {
    visible.value = false;
    remember();
}

onMounted(() => {
    window.addEventListener('beforeinstallprompt', onBeforeInstall);
    window.addEventListener('appinstalled', onInstalled);

    // iOS : pas d'événement natif -> on affiche les instructions manuelles.
    if (isIosSafari() && !isStandalone() && !dismissed()) {
        mode.value = 'ios';
        visible.value = true;
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstall);
    window.removeEventListener('appinstalled', onInstalled);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div
            v-if="visible"
            class="fixed inset-x-3 z-50 mx-auto max-w-md rounded-2xl border border-white/10 bg-[var(--forge,#12161C)] p-4 text-white shadow-2xl"
            style="bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px))"
        >
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--brand,#C6362B)]/15 ring-1 ring-[var(--brand,#C6362B)]/40">
                    <img src="/favicon.svg" alt="" class="h-6 w-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">Installer Vulkain</p>
                    <p v-if="mode !== 'ios'" class="mt-0.5 text-xs text-white/60">Accès rapide depuis l'écran d'accueil, en plein écran.</p>
                    <p v-else class="mt-0.5 text-xs text-white/60">
                        Dans Safari : appuyez sur
                        <span class="mx-0.5 inline-flex translate-y-0.5 items-center">
                            <!-- Icône « Partager » iOS -->
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-label="Partager">
                                <path d="M12 15V3" /><path d="m8 7 4-4 4 4" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7" />
                            </svg>
                        </span>
                        <span class="font-medium text-white/80">Partager</span>, puis « Sur l'écran d'accueil ».
                    </p>
                </div>
                <button class="shrink-0 rounded-lg p-1 text-white/50 hover:bg-white/10 hover:text-white" title="Plus tard" @click="close">
                    <Icon name="x" :size="16" />
                </button>
            </div>
            <div class="mt-3 flex justify-end gap-2">
                <button class="rounded-lg px-3 py-1.5 text-xs font-medium text-white/70 hover:bg-white/10" @click="close">
                    {{ mode === 'ios' ? 'Compris' : 'Plus tard' }}
                </button>
                <button v-if="mode !== 'ios'" class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--brand,#C6362B)] px-3 py-1.5 text-xs font-semibold hover:brightness-110" @click="install">
                    <Icon name="download" :size="14" /> Installer
                </button>
            </div>
        </div>
    </Transition>
</template>
