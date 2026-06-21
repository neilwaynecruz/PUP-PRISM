<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
    class?: string;
};

defineProps<Props>();
const { navigateTo, pendingPath } = useAppNavigation();

function handleNavigation(item: NavItem): void {
    navigateTo(item.href, item.title);
}

function isPendingItem(item: NavItem): boolean {
    return pendingPath.value === toUrl(item.href);
}
</script>

<template>
    <SidebarGroup
        :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`"
    >
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                        as-child
                    >
                        <Link
                            :href="item.href"
                            class="flex items-center gap-2"
                            @click.prevent="handleNavigation(item)"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <LoaderCircle
                                v-if="isPendingItem(item)"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
