<script setup lang="ts">
defineProps<{
    value: string | null;
}>();
</script>

<template>
    <span
        class="inline-flex min-w-12 items-center justify-center rounded-md border border-border/60 bg-muted/55 px-2.5 py-1 font-mono text-xs font-semibold tracking-wide text-muted-foreground shadow-xs dark:bg-muted/35"
    >
        {{ value ?? '—' }}
    </span>
</template>
