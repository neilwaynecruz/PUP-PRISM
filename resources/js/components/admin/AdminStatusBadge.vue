<script setup lang="ts">
defineProps<{
    active: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex min-w-20 items-center justify-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold"
        :class="
            active
                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300'
                : 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300'
        "
    >
        <span
            class="h-1.5 w-1.5 rounded-full"
            :class="
                active
                    ? 'bg-emerald-500 dark:bg-emerald-300'
                    : 'bg-rose-500 dark:bg-rose-300'
            "
        />
        {{ active ? 'Active' : 'Inactive' }}
    </span>
</template>
