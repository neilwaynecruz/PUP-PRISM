<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import GlobalSearchDialog from '@/components/GlobalSearchDialog.vue';
import PwaStatus from '@/components/PwaStatus.vue';
import SessionTimeoutWarning from '@/components/SessionTimeoutWarning.vue';
import { Toaster } from '@/components/ui/sonner';
import { useInertiaToast } from '@/composables/useInertiaToast';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import type { BreadcrumbItem } from '@/types';

useInertiaToast();
useKeyboardShortcuts();

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <a
            href="#main-content"
            class="fixed top-3 left-3 z-[100] -translate-y-20 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg transition-transform focus:translate-y-0 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            Skip to main content
        </a>
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <GlobalSearchDialog />
        <Toaster />
        <SessionTimeoutWarning />
        <PwaStatus />
    </AppShell>
</template>
