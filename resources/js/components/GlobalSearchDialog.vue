<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { BookOpen, FileText, History, LayoutGrid, Package, Settings, Truck } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/inventory/audit-logs';
import { index as bookingsIndex } from '@/routes/inventory/bookings';
import { index as productsIndex, create as productsCreate } from '@/routes/inventory/products';
import { index as receivingIndex } from '@/routes/inventory/receiving';
import { index as requisitionsIndex } from '@/routes/inventory/requisitions';
import type { NavItem } from '@/types';

type SearchItem = NavItem & {
    description: string;
    keywords: string[];
    roles?: string[];
};

const page = usePage();
const open = ref(false);
const query = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

const items = computed<SearchItem[]>(() => {
    const currentPath =
        typeof window === 'undefined' ? '' : window.location.pathname;
    const userRoles = (page.props.auth?.roles ?? []) as string[];

    const baseItems: SearchItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
            description: 'Overview, alerts, and operational widgets',
            keywords: ['dashboard', 'overview', 'alerts'],
        },
        {
            title: 'Products',
            href: productsIndex(),
            icon: Package,
            description: 'Browse inventory items and stock levels',
            keywords: ['products', 'inventory', 'stock', 'catalog'],
            roles: ['Admin', 'Supply Head', 'Property Custodian'],
        },
        {
            title: 'New Product',
            href: productsCreate(),
            icon: Package,
            description: 'Create a new product record',
            keywords: ['new', 'create', 'product'],
            roles: ['Admin', 'Supply Head'],
        },
        {
            title: 'Bookings',
            href: bookingsIndex(),
            icon: BookOpen,
            description: 'Request, review, and track asset bookings',
            keywords: ['bookings', 'calendar', 'schedule', 'reserve'],
            roles: ['Admin', 'Supply Head', 'Property Custodian'],
        },
        {
            title: 'Requisitions',
            href: requisitionsIndex(),
            icon: FileText,
            description: 'Submit and process issuance requests',
            keywords: ['requisitions', 'issue', 'requests'],
            roles: ['Admin', 'Supply Head', 'Property Custodian'],
        },
        {
            title: 'Receiving',
            href: receivingIndex(),
            icon: Truck,
            description: 'Receive new stock and assets',
            keywords: ['receiving', 'deliveries', 'stock in'],
            roles: ['Admin', 'Supply Head'],
        },
        {
            title: 'Audit Log',
            href: auditLogsIndex(),
            icon: History,
            description: 'Review operational changes and approval history',
            keywords: ['audit', 'logs', 'history', 'changes'],
            roles: ['Admin'],
        },
        {
            title: 'Settings',
            href: '/settings/profile',
            icon: Settings,
            description: 'Profile, appearance, and account settings',
            keywords: ['settings', 'profile', 'security', 'appearance'],
        },
    ];

    if (currentPath.includes('/inventory/bookings')) {
        baseItems.unshift({
            title: 'New Booking Request',
            href: bookingsIndex(),
            icon: BookOpen,
            description: 'Jump to the booking request form on this page',
            keywords: ['new', 'create', 'booking', 'request'],
        });
    }

    if (currentPath.includes('/inventory/requisitions')) {
        baseItems.unshift({
            title: 'New Requisition',
            href: requisitionsIndex(),
            icon: FileText,
            description: 'Jump to the quick requisition form on this page',
            keywords: ['new', 'create', 'requisition', 'request'],
        });
    }

    return baseItems.filter((item) => {
        if (!item.roles || item.roles.length === 0) {
            return true;
        }

        return item.roles.some((role) => userRoles.includes(role));
    });
});

const filteredItems = computed(() => {
    const normalizedQuery = query.value.trim().toLowerCase();

    if (normalizedQuery === '') {
        return items.value;
    }

    return items.value.filter((item) => {
        const haystack = [
            item.title,
            item.description,
            ...item.keywords,
        ].join(' ').toLowerCase();

        return haystack.includes(normalizedQuery);
    });
});

function openDialog(): void {
    open.value = true;
    void nextTick(() => searchInput.value?.focus());
}

function closeDialog(): void {
    open.value = false;
    query.value = '';
}

function activateItem(item: SearchItem): void {
    closeDialog();

    if (
        item.title === 'New Booking Request'
        || item.title === 'New Requisition'
    ) {
        const form = document.querySelector<HTMLElement>('[data-shortcut="new"]');

        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            form.querySelector<HTMLElement>('input, select, textarea')?.focus();
        }

        return;
    }

    router.get(toUrl(item.href));
}

onMounted(() => {
    window.addEventListener('app:open-global-search', openDialog);
    window.addEventListener('app:close-overlays', closeDialog);
});

onUnmounted(() => {
    window.removeEventListener('app:open-global-search', openDialog);
    window.removeEventListener('app:close-overlays', closeDialog);
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => { if (!value) closeDialog(); }">
        <DialogContent class="max-w-2xl gap-0 overflow-hidden p-0">
            <DialogHeader class="border-b border-border/60 px-5 py-4">
                <DialogTitle>Global search</DialogTitle>
                <DialogDescription>
                    Jump to key modules and common actions. Press <span class="font-medium">Esc</span> to close.
                </DialogDescription>
            </DialogHeader>

            <div class="border-b border-border/60 px-5 py-4">
                <Input
                    ref="searchInput"
                    v-model="query"
                    data-shortcut="search"
                    placeholder="Search pages and actions..."
                />
            </div>

            <div class="max-h-[420px] overflow-y-auto px-2 py-2">
                <button
                    v-for="item in filteredItems"
                    :key="`${item.title}-${toUrl(item.href)}`"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition-colors hover:bg-muted/60"
                    @click="activateItem(item)"
                >
                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <component :is="item.icon" class="h-4 w-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ item.title }}</div>
                        <div class="text-sm text-muted-foreground">
                            {{ item.description }}
                        </div>
                    </div>
                </button>

                <div
                    v-if="filteredItems.length === 0"
                    class="px-3 py-8 text-center text-sm text-muted-foreground"
                >
                    No matching pages or actions.
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
