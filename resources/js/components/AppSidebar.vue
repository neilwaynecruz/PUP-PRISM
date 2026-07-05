<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
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
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-r border-sidebar-border/50"
    >
        <SidebarHeader class="px-3 py-5">
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
                            <AppLogoIcon class="size-11 shrink-0" />
                            <div class="grid min-w-0 flex-1">
                                <span
                                    class="truncate text-sm leading-none font-semibold tracking-[0.18em] uppercase"
                                >
                                    PUP PRISM
                                </span>
                                <span
                                    class="mt-1 truncate text-[0.62rem] leading-none font-medium tracking-[0.12em] text-sidebar-foreground/65 uppercase"
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

        <SidebarContent class="px-2 py-3 space-y-4">
            <NavMain
                v-for="group in groupedNavItems"
                :key="group.group"
                :title="group.group"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter class="px-2 pb-4">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
