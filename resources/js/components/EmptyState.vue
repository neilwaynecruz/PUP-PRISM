<script setup lang="ts">
import { Trash2, Package, Calendar, FileText } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';

type IconType = 'trash' | 'product' | 'booking' | 'requisition';

interface Props {
    icon?: IconType;
    title: string;
    description?: string;
    actionLabel?: string;
    actionHref?: string;
}

withDefaults(defineProps<Props>(), {
    icon: 'trash',
    description: '',
    actionLabel: '',
    actionHref: '',
});

const iconMap: Record<IconType, LucideIcon> = {
    trash: Trash2,
    product: Package,
    booking: Calendar,
    requisition: FileText,
};
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-4 py-12 text-center"
    >
        <div
            class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted/50"
        >
            <component
                :is="iconMap[icon]"
                class="h-8 w-8 text-muted-foreground/60"
            />
        </div>
        <h3 class="text-lg font-medium text-foreground">{{ title }}</h3>
        <p
            v-if="description"
            class="mt-2 max-w-sm text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="actionLabel && actionHref" class="mt-6">
            <a
                :href="actionHref"
                class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
            >
                {{ actionLabel }}
            </a>
        </div>
    </div>
</template>
