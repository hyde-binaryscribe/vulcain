<script setup>
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    node: { type: Object, required: true },
    kinds: { type: Array, default: () => [] },
    depth: { type: Number, default: 0 },
});
const emit = defineEmits(['remove']);

function addChild() {
    if (!Array.isArray(props.node.children)) props.node.children = [];
    props.node.children.push({ name: '', kind: 'mobile', children: [] });
}
function removeChild(index) {
    props.node.children.splice(index, 1);
}
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-2.5">
        <div class="flex items-center gap-2">
            <input
                v-model="node.name"
                type="text"
                placeholder="Nom de l'emplacement"
                class="min-w-0 flex-1 rounded-md border-gray-300 px-2 py-1.5 text-sm"
            />
            <select v-model="node.kind" class="rounded-md border-gray-300 px-2 py-1.5 text-sm">
                <option v-for="k in kinds" :key="k.value" :value="k.value">{{ k.label }}</option>
            </select>
            <button
                type="button"
                class="shrink-0 rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                title="Supprimer"
                @click="emit('remove')"
            >
                <Icon name="x" :size="15" />
            </button>
        </div>

        <div v-if="node.children && node.children.length" class="mt-2 space-y-2 border-l-2 border-gray-200 pl-3">
            <VehicleModelNode
                v-for="(child, i) in node.children"
                :key="i"
                :node="child"
                :kinds="kinds"
                :depth="depth + 1"
                @remove="removeChild(i)"
            />
        </div>

        <button
            v-if="depth < 3"
            type="button"
            class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-[var(--brand)]"
            @click="addChild"
        >
            <Icon name="plus" :size="13" /> Sous-emplacement
        </button>
    </div>
</template>
