<script setup lang="ts">
import type { CSSProperties } from 'vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        minWidth?: CSSProperties['minWidth'];
    }>(),
    {
        minWidth: '800px',
    },
);

const tableStyle = computed<CSSProperties>(() => ({
    minWidth: props.minWidth,
}));
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-sm ring-1 ring-black/[0.02] dark:border-border/60 dark:bg-card/90 dark:ring-white/[0.03]"
    >
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-sm" :style="tableStyle">
                <slot />
            </table>
        </div>
    </div>
</template>
