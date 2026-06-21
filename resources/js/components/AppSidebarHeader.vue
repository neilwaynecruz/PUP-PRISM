<script setup lang="ts">
import { LoaderCircle, Search } from 'lucide-vue-next';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { isNavigating, pendingLabel } = useAppNavigation();

function openGlobalSearch(): void {
    window.dispatchEvent(new CustomEvent('app:open-global-search'));
}
</script>

<template>
    <header
        class="relative flex h-14 shrink-0 items-center justify-between gap-3 border-b border-sidebar-border/50 bg-card/50 px-5 backdrop-blur-sm transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div
            v-if="isNavigating"
            class="absolute inset-x-0 top-0 h-0.5 overflow-hidden bg-primary/10"
        >
            <div class="h-full w-1/3 animate-pulse rounded-full bg-primary" />
        </div>

        <div class="flex items-center gap-2.5">
            <SidebarTrigger
                class="-ml-1 text-muted-foreground transition-colors hover:text-foreground"
            />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-2">
            <div
                v-if="isNavigating"
                role="status"
                aria-live="polite"
                class="hidden items-center gap-2 rounded-full border border-primary/15 bg-background/95 px-3 py-1 text-xs font-medium text-foreground shadow-sm backdrop-blur md:inline-flex"
            >
                <LoaderCircle class="h-3.5 w-3.5 animate-spin text-primary" />
                <span>
                    {{ pendingLabel ? `Opening ${pendingLabel}...` : 'Loading module...' }}
                </span>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="inline-flex items-center gap-2 rounded-lg border-dashed"
                @click="openGlobalSearch"
            >
                <Search class="h-4 w-4" />
                <span class="hidden sm:inline">Search</span>
                <span
                    class="hidden rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground md:inline-flex"
                    >Ctrl+K</span
                >
            </Button>
        </div>
    </header>
</template>
