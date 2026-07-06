<script setup lang="ts">
defineProps<{
    value: number;
}>();
</script>

<template>
    <span
        class="inline-flex min-w-9 items-center justify-center rounded-full border border-border/50 bg-primary/8 px-2.5 py-1 font-mono text-xs font-semibold text-primary shadow-xs dark:bg-primary/12"
    >
        {{ value }}
    </span>
</template>
