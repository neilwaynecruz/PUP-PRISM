<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        class="inline-flex flex-wrap gap-1 rounded-xl border border-border/70 bg-muted/60 p-1"
        role="group"
        aria-label="Appearance theme"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            @click="updateAppearance(value)"
            :class="[
                'flex min-h-11 items-center rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors sm:min-h-9',
                appearance === value
                    ? 'bg-card text-foreground shadow-xs ring-1 ring-border/60'
                    : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-0.5 h-4 w-4" />
            <span class="ml-1.5">{{ label }}</span>
        </button>
    </div>
</template>
