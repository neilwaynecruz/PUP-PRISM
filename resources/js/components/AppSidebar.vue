<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    LayoutGrid,
    Package,
    Truck,
    BarChart3,
    ArrowLeftRight,
    FileText,
    History,
    Settings,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
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
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/inventory/audit-logs';
import { index as bookingsIndex } from '@/routes/inventory/bookings';
import { index as handoverIndex } from '@/routes/inventory/handover';
import { index as movementsIndex } from '@/routes/inventory/movements';
import { index as productsIndex } from '@/routes/inventory/products';
import { index as receivingIndex } from '@/routes/inventory/receiving';
import { index as requisitionsIndex } from '@/routes/inventory/requisitions';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Products',
        href: productsIndex(),
        icon: Package,
    },
    {
        title: 'Handover',
        href: handoverIndex(),
        icon: ArrowLeftRight,
    },
    {
        title: 'Bookings',
        href: bookingsIndex(),
        icon: BookOpen,
    },
    {
        title: 'Requisitions',
        href: requisitionsIndex(),
        icon: FileText,
    },
    {
        title: 'Receiving',
        href: receivingIndex(),
        icon: Truck,
    },
    {
        title: 'Stock movements',
        href: movementsIndex(),
        icon: BarChart3,
    },
    {
        title: 'Audit logs',
        href: auditLogsIndex(),
        icon: History,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Settings',
        href: '/settings/profile',
        icon: Settings,
    },
];
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
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm"
                            >
                                <AppLogo class="h-5 w-5" />
                            </div>
                            <span
                                class="font-display text-lg font-semibold tracking-tight"
                                >PRISM</span
                            >
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
