<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LoaderCircle, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useInventoryNavigation } from '@/lib/inventoryNavigation';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';

const { groupedNavItems } = useInventoryNavigation();
const { closeOverlays, pendingPath } = useAppNavigation();

const navSearchQuery = ref('');

const filteredGroupedNavItems = computed(() => {
    const q = navSearchQuery.value.trim().toLowerCase();

    if (!q) {
        return groupedNavItems.value;
    }

    return groupedNavItems.value
        .map((group) => {
            const matches = group.items.filter((item) =>
                item.title.toLowerCase().includes(q),
            );

            return {
                ...group,
                items: matches,
            };
        })
        .filter((group) => group.items.length > 0);
});

function goToFirstMatch(): void {
    const firstItem = filteredGroupedNavItems.value[0]?.items[0];

    if (!firstItem) {
        return;
    }

    closeOverlays();
    router.visit(toUrl(firstItem.href));
}
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-r border-sidebar-border/50"
    >
        <SidebarHeader class="border-b border-sidebar-border/40 px-3 py-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="transition-colors duration-200 hover:bg-sidebar-accent/60"
                    >
                        <Link
                            :href="dashboard()"
                            class="flex items-center gap-3"
                            @click="closeOverlays()"
                        >
                            <AppLogoIcon class="size-10 shrink-0" />
                            <div class="grid min-w-0 flex-1">
                                <span
                                    class="truncate text-sm leading-none font-bold tracking-[0.16em] text-sidebar-foreground uppercase"
                                >
                                    PUP PRISM
                                </span>
                                <span
                                    class="mt-1 truncate text-[0.62rem] leading-none font-medium tracking-[0.10em] text-sidebar-foreground/60 uppercase"
                                >
                                    Inventory Operations
                                </span>
                            </div>
                            <LoaderCircle
                                v-if="pendingPath === toUrl(dashboard())"
                                class="h-4 w-4 animate-spin text-primary"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!-- Quick navigation search input (Visible in full sidebar mode) -->
        <div class="px-3 pt-3 pb-1 group-data-[collapsible=icon]:hidden">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-sidebar-foreground/45"
                />
                <input
                    v-model="navSearchQuery"
                    type="search"
                    aria-label="Search navigation"
                    placeholder="Find page or module..."
                    class="h-8 w-full rounded-lg border border-sidebar-border/60 bg-sidebar-accent/25 pr-7 pl-8 text-xs text-sidebar-foreground transition placeholder:text-sidebar-foreground/45 focus:border-primary/50 focus:bg-sidebar-accent/40 focus:ring-1 focus:ring-primary/40 focus:outline-none"
                    @keydown.enter.prevent="goToFirstMatch"
                />
                <button
                    v-if="navSearchQuery"
                    type="button"
                    aria-label="Clear navigation search"
                    class="absolute top-1/2 right-0 flex size-8 -translate-y-1/2 items-center justify-center text-sidebar-foreground/50 hover:text-sidebar-foreground"
                    @click="navSearchQuery = ''"
                >
                    <X class="h-3 w-3" />
                </button>
            </div>
        </div>

        <SidebarContent
            class="scrollbar-thin space-y-2 overflow-y-auto px-2 py-2"
        >
            <div
                v-if="navSearchQuery && filteredGroupedNavItems.length === 0"
                role="status"
                class="px-3 py-6 text-center text-xs text-sidebar-foreground/60"
            >
                No pages match "{{ navSearchQuery }}"
            </div>

            <NavMain
                v-for="group in filteredGroupedNavItems"
                :key="group.group"
                :title="group.group"
                :items="group.items"
                :force-open="Boolean(navSearchQuery.trim())"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/40 px-2 pt-2 pb-4">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
