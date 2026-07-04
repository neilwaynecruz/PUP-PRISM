<script setup lang="ts">
import { BookOpen, FileText, LoaderCircle, Search } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useInventoryNavigation } from '@/lib/inventoryNavigation';
import type { InventorySearchItem } from '@/lib/inventoryNavigation';
import { toUrl } from '@/lib/utils';
import { index as bookingsIndex } from '@/routes/inventory/bookings';
import { index as requisitionsIndex } from '@/routes/inventory/requisitions';
import { search as globalSearchRoute } from '@/routes';

type EntityResult = {
    type: string;
    id: number;
    title: string;
    subtitle: string | null;
    url: string;
};

const open = ref(false);
const query = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const entityResults = ref<EntityResult[]>([]);
const entityLoading = ref(false);
const entityError = ref<string | null>(null);
const { navigateTo } = useAppNavigation();
const { searchItems } = useInventoryNavigation();

const items = computed<InventorySearchItem[]>(() => {
    const currentPath =
        typeof window === 'undefined' ? '' : window.location.pathname;
    const baseItems = [...searchItems.value];

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

    return baseItems;
});

const filteredItems = computed(() => {
    const normalizedQuery = query.value.trim().toLowerCase();

    if (normalizedQuery === '') {
        return items.value;
    }

    return items.value.filter((item) => {
        const haystack = [item.title, item.description, ...item.keywords]
            .join(' ')
            .toLowerCase();

        return haystack.includes(normalizedQuery);
    });
});

const showEntityResults = computed(
    () => query.value.trim().length >= 2 && !entityLoading.value,
);

const entityTypeLabel: Record<string, string> = {
    product: 'Product',
    asset: 'Asset',
    requisition: 'Requisition',
    booking: 'Booking',
    purchase_order: 'Purchase order',
    user: 'User',
};

let entitySearchTimer: number | undefined;
let entitySearchAbort: AbortController | null = null;

watch(query, (value) => {
    window.clearTimeout(entitySearchTimer);

    const term = value.trim();

    if (term.length < 2) {
        entityResults.value = [];
        entityError.value = null;
        entityLoading.value = false;
        entitySearchAbort?.abort();
        entitySearchAbort = null;

        return;
    }

    entitySearchTimer = window.setTimeout(() => {
        void fetchEntityResults(term);
    }, 250);
});

async function fetchEntityResults(term: string): Promise<void> {
    entitySearchAbort?.abort();
    entitySearchAbort = new AbortController();
    entityLoading.value = true;
    entityError.value = null;

    try {
        const response = await fetch(
            globalSearchRoute.url({ query: { q: term } }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: entitySearchAbort.signal,
            },
        );

        if (!response.ok) {
            throw new Error('Search request failed.');
        }

        const payload = (await response.json()) as { data: EntityResult[] };
        entityResults.value = payload.data ?? [];
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        entityResults.value = [];
        entityError.value = 'Unable to search records right now.';
    } finally {
        entityLoading.value = false;
    }
}

function openDialog(): void {
    open.value = true;
    void nextTick(() => searchInput.value?.focus());
}

function closeDialog(): void {
    open.value = false;
    query.value = '';
    entityResults.value = [];
    entityError.value = null;
}

function activateItem(item: InventorySearchItem): void {
    closeDialog();

    if (
        item.title === 'New Booking Request' ||
        item.title === 'New Requisition'
    ) {
        const form = document.querySelector<HTMLElement>(
            '[data-shortcut="new"]',
        );

        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            form.querySelector<HTMLElement>('input, select, textarea')?.focus();
        }

        return;
    }

    navigateTo(item.href, item.title);
}

function activateEntity(item: EntityResult): void {
    closeDialog();
    navigateTo(item.url, item.title);
}

function itemKey(item: InventorySearchItem): string {
    return `${item.title}-${toUrl(item.href)}`;
}

onMounted(() => {
    window.addEventListener('app:open-global-search', openDialog);
    window.addEventListener('app:close-overlays', closeDialog);
});

onBeforeUnmount(() => {
    window.clearTimeout(entitySearchTimer);
    entitySearchAbort?.abort();
    window.removeEventListener('app:open-global-search', openDialog);
    window.removeEventListener('app:close-overlays', closeDialog);
});
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (value) => {
                if (!value) closeDialog();
            }
        "
    >
        <DialogContent class="max-w-2xl gap-0 overflow-hidden p-0">
            <DialogHeader class="border-b border-border/60 px-5 py-4">
                <DialogTitle>Global search</DialogTitle>
                <DialogDescription>
                    Search records and jump to modules. Press
                    <span class="font-medium">Esc</span> to close.
                </DialogDescription>
            </DialogHeader>

            <div class="border-b border-border/60 px-5 py-4">
                <Input
                    ref="searchInput"
                    v-model="query"
                    data-shortcut="search"
                    placeholder="Search products, assets, requisitions, bookings..."
                />
            </div>

            <div class="max-h-[420px] overflow-y-auto px-2 py-2">
                <div
                    v-if="entityLoading"
                    class="flex items-center gap-2 px-3 py-4 text-sm text-muted-foreground"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    Searching records...
                </div>

                <div
                    v-if="entityError"
                    class="px-3 py-2 text-sm text-rose-600"
                >
                    {{ entityError }}
                </div>

                <div v-if="showEntityResults && entityResults.length > 0" class="mb-3">
                    <div
                        class="px-3 py-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Records
                    </div>
                    <button
                        v-for="item in entityResults"
                        :key="`${item.type}-${item.id}`"
                        type="button"
                        class="flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition-colors hover:bg-muted/60"
                        @click="activateEntity(item)"
                    >
                        <div
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                        >
                            <Search class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ item.title }}</span>
                                <span
                                    class="rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase"
                                >
                                    {{ entityTypeLabel[item.type] ?? item.type }}
                                </span>
                            </div>
                            <div
                                v-if="item.subtitle"
                                class="text-sm text-muted-foreground"
                            >
                                {{ item.subtitle }}
                            </div>
                        </div>
                    </button>
                </div>

                <div
                    v-if="showEntityResults && entityResults.length === 0 && !entityError"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    No matching records found.
                </div>

                <div
                    class="px-3 py-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Pages & actions
                </div>

                <button
                    v-for="item in filteredItems"
                    :key="itemKey(item)"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition-colors hover:bg-muted/60"
                    @click="activateItem(item)"
                >
                    <div
                        class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
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
                    v-if="filteredItems.length === 0 && query.trim() !== ''"
                    class="px-3 py-8 text-center text-sm text-muted-foreground"
                >
                    No matching pages or actions.
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
