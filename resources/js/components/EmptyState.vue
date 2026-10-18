<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Trash2, Package, Calendar, FileText } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { useId } from 'vue';

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

const titleId = useId();
</script>

<template>
    <section
        :aria-labelledby="titleId"
        class="flex flex-col items-center justify-center px-4 py-12 text-center"
    >
        <div
            class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted/50"
        >
            <component
                :is="iconMap[icon]"
                aria-hidden="true"
                class="h-8 w-8 text-muted-foreground/60"
            />
        </div>
        <h2 :id="titleId" class="text-lg font-semibold text-foreground">
            {{ title }}
        </h2>
        <p
            v-if="description"
            class="mt-2 max-w-sm text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="actionLabel && actionHref" class="mt-6">
            <Link
                :href="actionHref"
                class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:min-h-9"
            >
                {{ actionLabel }}
            </Link>
        </div>
    </section>
</template>
