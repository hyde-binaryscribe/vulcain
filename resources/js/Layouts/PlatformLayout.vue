<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Logo from '@/Components/Logo.vue';

const page = usePage();
const admin = computed(() => page.props.platformAuth?.admin);
const flash = computed(() => page.props.flash?.status);

function logout() {
    router.post('/platform/logout');
}
</script>

<template>
    <!-- Accent neutre « Desk », distinct du branding des organisations. -->
    <div class="min-h-screen bg-slate-100" style="--brand: #4338ca">
        <header class="border-b border-slate-200 bg-slate-900 text-white">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
                <Link href="/platform" class="flex items-center gap-2">
                    <Logo :size="30" />
                    <span class="font-display font-extrabold tracking-wide">VULKAIN <span class="font-sans text-sm font-medium text-indigo-300">Desk</span></span>
                </Link>
                <div class="flex items-center gap-4">
                    <nav class="hidden items-center gap-1 sm:flex">
                        <Link href="/platform" class="rounded-lg px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 hover:text-white">Organisations</Link>
                        <Link v-if="!admin?.is_group_manager" href="/platform/groups" class="rounded-lg px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 hover:text-white">Groupes</Link>
                    </nav>
                    <span class="hidden text-sm text-slate-300 sm:block">{{ admin?.name }}</span>
                    <button type="button" class="rounded-lg border border-white/20 px-3 py-1.5 text-sm hover:bg-white/10" @click="logout">
                        Se déconnecter
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <div v-if="flash" class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ flash }}
            </div>
            <slot />
        </main>
    </div>
</template>
