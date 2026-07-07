<script setup>
defineProps({
    logs: { type: Array, default: () => [] },
    dense: { type: Boolean, default: false },
});

const actionStyles = {
    Création: 'bg-green-100 text-green-800',
    Modification: 'bg-blue-100 text-blue-800',
    Suppression: 'bg-red-100 text-red-800',
};
</script>

<template>
    <ul class="divide-y divide-gray-100">
        <li v-for="log in logs" :key="log.id" class="py-3">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 rounded-full px-2 py-0.5 text-xs font-medium" :class="actionStyles[log.action] || 'bg-gray-100 text-gray-700'">
                    {{ log.action }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-gray-800">
                        <span class="font-medium">{{ log.subject_type }}</span> — {{ log.description }}
                    </p>
                    <p class="text-xs text-gray-500">{{ log.actor }} · {{ log.at }}</p>
                    <div v-if="!dense && log.changes && log.changes.new" class="mt-1 flex flex-wrap gap-1">
                        <span v-for="(val, key) in log.changes.new" :key="key" class="rounded bg-gray-50 px-1.5 py-0.5 text-xs text-gray-600">
                            {{ key }} : <span class="text-gray-400 line-through">{{ log.changes.old?.[key] ?? '—' }}</span> → {{ val }}
                        </span>
                    </div>
                </div>
            </div>
        </li>
        <li v-if="logs.length === 0" class="py-3 text-sm text-gray-500">Aucune activité enregistrée.</li>
    </ul>
</template>
