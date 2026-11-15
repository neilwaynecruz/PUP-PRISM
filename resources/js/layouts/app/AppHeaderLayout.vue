<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import PwaStatus from '@/components/PwaStatus.vue';
import SessionTimeoutWarning from '@/components/SessionTimeoutWarning.vue';
import { Toaster } from '@/components/ui/sonner';
import { useInertiaToast } from '@/composables/useInertiaToast';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import type { BreadcrumbItem } from '@/types';

useInertiaToast();
useKeyboardShortcuts();
const page = usePage();

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="header">
        <a
            href="#main-content"
            tabindex="0"
            class="fixed top-3 left-3 z-[100] -translate-y-20 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg transition-transform focus:translate-y-0 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            Skip to main content
        </a>
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header">
            <div
                v-if="page.props.demoMode"
                role="note"
                aria-label="Synthetic demonstration environment"
                class="border-b border-border bg-accent/40 px-4 py-3 text-sm text-foreground sm:px-6"
            >
                <strong>Demonstration environment.</strong> Records are
                synthetic and are not evidence of actual institutional activity.
            </div>
            <slot />
        </AppContent>
        <Toaster />
        <SessionTimeoutWarning />
        <PwaStatus />
    </AppShell>
</template>
