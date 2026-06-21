<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { toUrl } from '@/lib/utils';
import { useInventoryNavigation } from '@/lib/inventoryNavigation';
import { dashboard } from '@/routes';

const { footerNavItems, mainNavItems } = useInventoryNavigation();
const { navigateTo, pendingPath } = useAppNavigation();
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
                            @click.prevent="navigateTo(dashboard(), 'Dashboard')"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm"
                            >
                                <AppLogoIcon class="h-5 w-5 fill-current" />
                            </div>
                            <span
                                class="font-display text-lg font-semibold tracking-tight"
                                >PRISM</span
                            >
                            <LoaderCircle
                                v-if="pendingPath === toUrl(dashboard())"
                                class="h-4 w-4 animate-spin text-primary"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-2">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="px-2 pb-4">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
