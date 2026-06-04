<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import GlobalSearchDialog from '@/components/GlobalSearchDialog.vue';
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
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <GlobalSearchDialog />
        <Toaster />
        <SessionTimeoutWarning />
    </AppShell>
</template>
