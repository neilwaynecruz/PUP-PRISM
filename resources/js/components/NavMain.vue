<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{
        title: string;
        items: NavItem[];
        forceOpen?: boolean;
    }>(),
    {
        forceOpen: false,
    },
);

const { isCurrentUrl } = useCurrentUrl();
const { closeOverlays, pendingPath } = useAppNavigation();

const storageKey = `prism_nav_collapsed_${props.title.toLowerCase().replace(/\s+/g, '_')}`;

function getInitialState(): boolean {
    try {
        const stored = localStorage.getItem(storageKey);

        if (stored !== null) {
            return stored !== 'true';
        }
    } catch {
        // Fallback to open
    }

    return true;
}

const isOpen = ref(getInitialState());

const hasActiveItem = computed(() => {
    return props.items.some((item) => isCurrentUrl(item.href));
});

// Auto-expand if the active URL is inside this group or if search is active
watch(
    [hasActiveItem, () => props.forceOpen],
    ([active, force]) => {
        if (active || force) {
            isOpen.value = true;
        }
    },
    { immediate: true },
);

function toggleGroup(): void {
    isOpen.value = !isOpen.value;

    try {
        localStorage.setItem(storageKey, String(!isOpen.value));
    } catch {
        // LocalStorage unavailable
    }
}

function handleNavigation(): void {
    closeOverlays();
}

function isPendingItem(item: NavItem): boolean {
    return pendingPath.value === toUrl(item.href);
}
</script>

<template>
    <SidebarGroup
        class="py-1 group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:py-1"
    >
        <!-- Group Header Toggle (Visible in expanded sidebar mode only) -->
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-[10px] font-semibold tracking-wider text-muted-foreground/80 uppercase transition-colors group-data-[collapsible=icon]:hidden hover:text-foreground focus:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            :aria-expanded="isOpen"
            @click="toggleGroup"
        >
            <span class="truncate">{{ title }}</span>
            <ChevronDown
                class="h-3 w-3 shrink-0 text-muted-foreground/60 transition-transform duration-200"
                :class="{ '-rotate-90': !isOpen }"
            />
        </button>

        <!-- Subtle separator between navigation sections in collapsed icon mode -->
        <div
            class="mx-auto my-1.5 hidden h-px w-5 bg-sidebar-border/60 group-data-[collapsible=icon]:block first:group-data-[collapsible=icon]:hidden"
            role="separator"
        />

        <!-- Menu items: always accessible in icon mode, toggleable in expanded mode -->
        <SidebarMenu
            class="mt-1 gap-1 group-data-[collapsible=icon]:mt-0 group-data-[collapsible=icon]:!flex group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center"
            :class="{ hidden: !isOpen }"
        >
            <SidebarMenuItem
                v-for="item in items"
                :key="item.title"
                class="group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center"
            >
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="group relative transition-colors duration-200 group-data-[collapsible=icon]:!size-9 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:!p-0 data-[active=true]:bg-primary/10 data-[active=true]:font-semibold data-[active=true]:text-primary data-[active=true]:shadow-xs dark:data-[active=true]:bg-primary/15"
                >
                    <Link
                        :href="item.href"
                        class="flex items-center gap-3 group-data-[collapsible=icon]:h-full group-data-[collapsible=icon]:w-full group-data-[collapsible=icon]:justify-center"
                        @click="handleNavigation()"
                    >
                        <component
                            :is="item.icon"
                            aria-hidden="true"
                            class="h-[18px] w-[18px] shrink-0 text-muted-foreground/75 group-hover:text-foreground group-data-[active=true]:text-primary"
                        />
                        <span
                            class="text-xs font-medium group-data-[collapsible=icon]:hidden"
                            >{{ item.title }}</span
                        >
                        <LoaderCircle
                            v-if="isPendingItem(item)"
                            aria-hidden="true"
                            class="ml-auto h-3.5 w-3.5 animate-spin text-primary group-data-[collapsible=icon]:hidden motion-reduce:animate-none"
                        />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
