<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-3">
        <AppLogoIcon class="size-11 shrink-0" />

        <div class="grid min-w-0 flex-1 text-left">
            <span
                class="truncate text-[0.85rem] leading-none font-semibold tracking-[0.18em] text-foreground uppercase"
            >
                PUP PRISM
            </span>
            <span
                class="mt-1 truncate text-[0.68rem] leading-none font-medium tracking-[0.08em] text-muted-foreground uppercase"
            >
                Inventory Operations
            </span>
        </div>
    </div>
</template>
