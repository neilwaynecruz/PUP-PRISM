<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

defineProps<{
    title: string;
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const { navigateTo, pendingPath } = useAppNavigation();

function handleNavigation(item: NavItem): void {
    navigateTo(item.href, item.title);
}

function isPendingItem(item: NavItem): boolean {
    return pendingPath.value === toUrl(item.href);
}
</script>

<template>
    <SidebarGroup class="py-0">
        <SidebarGroupLabel
            class="px-3 text-[10px] font-semibold tracking-widest text-muted-foreground/70 uppercase"
        >
            {{ title }}
        </SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="group relative transition-all duration-200 data-[active=true]:bg-primary/10 data-[active=true]:text-primary data-[active=true]:shadow-sm dark:data-[active=true]:bg-primary/15"
                >
                    <Link
                        :href="item.href"
                        class="flex items-center gap-3"
                        @click.prevent="handleNavigation(item)"
                    >
                        <component
                            :is="item.icon"
                            class="h-[18px] w-[18px] shrink-0 transition-transform duration-200 group-hover:scale-110"
                        />
                        <span class="font-medium">{{ item.title }}</span>
                        <LoaderCircle
                            v-if="isPendingItem(item)"
                            class="ml-auto h-3.5 w-3.5 animate-spin text-primary"
                        />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
