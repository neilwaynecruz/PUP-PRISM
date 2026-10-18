<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    level?: 1 | 2 | 3;
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
    level: 1,
});
</script>

<template>
    <header :class="variant === 'small' ? 'space-y-1' : 'mb-8 space-y-1.5'">
        <component
            :is="`h${level}`"
            :class="
                variant === 'small'
                    ? 'text-xl font-semibold tracking-tight text-foreground'
                    : 'font-display text-2xl font-bold tracking-tight text-balance text-foreground md:text-3xl'
            "
        >
            {{ title }}
        </component>
        <p
            v-if="description"
            class="max-w-3xl text-sm leading-relaxed text-pretty text-muted-foreground"
        >
            {{ description }}
        </p>
    </header>
</template>
