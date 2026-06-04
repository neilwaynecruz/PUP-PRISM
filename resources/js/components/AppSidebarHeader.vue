<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

function openGlobalSearch(): void {
    window.dispatchEvent(new CustomEvent('app:open-global-search'));
}
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-sidebar-border/50 bg-card/50 px-5 backdrop-blur-sm transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2.5">
            <SidebarTrigger class="-ml-1 text-muted-foreground hover:text-foreground transition-colors" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
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
            <span class="hidden rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground md:inline-flex">Ctrl+K</span>
        </Button>
    </header>
</template>
