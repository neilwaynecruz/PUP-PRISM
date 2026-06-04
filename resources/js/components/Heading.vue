<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? 'mb-1' : 'mb-8 space-y-1.5'">
        <h2
            :class="
                variant === 'small'
                    ? 'text-lg font-semibold tracking-tight text-foreground'
                    : 'font-display text-2xl font-bold tracking-tight text-foreground md:text-3xl'
            "
        >
            {{ title }}
        </h2>
        <p
            v-if="description"
            class="text-sm leading-relaxed text-muted-foreground/80"
        >
            {{ description }}
        </p>
    </header>
</template>
