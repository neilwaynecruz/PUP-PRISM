<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(defineProps<{
    rows?: number;
    columns?: number;
}>(), {
    rows: 5,
    columns: 6,
});
</script>

<template>
    <div class="w-full">
        <!-- Header skeleton -->
        <div class="flex gap-3 border-b border-border/60 pb-3">
            <Skeleton
                v-for="c in columns"
                :key="`h-${c}`"
                class="h-4"
                :class="c === 1 ? 'w-12' : c === columns ? 'w-20' : 'flex-1'"
            />
        </div>
        <!-- Row skeletons -->
        <div v-for="r in rows" :key="`r-${r}`" class="flex items-center gap-3 border-b border-border/40 py-3">
            <Skeleton
                v-for="c in columns"
                :key="`c-${r}-${c}`"
                class="h-3"
                :class="c === 1 ? 'w-12' : c === columns ? 'w-16' : 'flex-1'"
            />
        </div>
    </div>
</template>
