<script setup lang="ts">
defineProps<{
    warnings: string[];
    usage: Record<string, number>;
}>();
</script>

<template>
    <div
        v-if="warnings.length > 0 || Object.keys(usage).length > 0"
        class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
    >
        <p class="font-semibold text-amber-800 dark:text-amber-300">Usage impact</p>
        <ul v-if="warnings.length" class="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
            <li v-for="warning in warnings" :key="warning">{{ warning }}</li>
        </ul>
        <div v-if="Object.keys(usage).length" class="mt-3 flex flex-wrap gap-2">
            <span
                v-for="(count, key) in usage"
                :key="key"
                class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium capitalize"
            >
                {{ String(key).replace(/_/g, ' ') }}: {{ count }}
            </span>
        </div>
    </div>
</template>
