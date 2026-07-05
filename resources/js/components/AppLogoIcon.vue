<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/android-icon-192x192.png"
        alt="PRISM logo"
        :class="className"
        class="block shrink-0 object-contain"
        decoding="async"
        loading="eager"
        v-bind="$attrs"
    />
</template>
