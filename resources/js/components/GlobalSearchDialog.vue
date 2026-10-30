<script setup lang="ts">
import {
    BookOpen,
    FileText,
    LoaderCircle,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import type { ComponentPublicInstance } from 'vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
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
import { search as globalSearchRoute } from '@/routes';
import { index as bookingsIndex } from '@/routes/inventory/bookings';
import { index as requisitionsIndex } from '@/routes/inventory/requisitions';

type EntityResult = {
    type: string;
    id: number;
    title: string;
    subtitle: string | null;
    url: string;
};

const open = ref(false);
const query = ref('');
// The shared Input component renders a bare <input>, so its root element is the focus target.
const searchInput = ref<ComponentPublicInstance | null>(null);
const entityResults = ref<EntityResult[]>([]);
const entityLoading = ref(false);
const entityError = ref<string | null>(null);
// The term whose request last finished; results only describe the query when they match.
const completedTerm = ref<string | null>(null);
const activeIndex = ref(-1);
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

const currentTerm = computed(() => query.value.trim());

const searchPending = computed(
    () =>
        currentTerm.value.length >= 2 &&
        completedTerm.value !== currentTerm.value,
);

const showEntityResults = computed(
    () =>
        currentTerm.value.length >= 2 &&
        completedTerm.value === currentTerm.value,
);

type ResultOption =
    | { kind: 'entity'; key: string; entity: EntityResult }
    | { kind: 'page'; key: string; item: InventorySearchItem };

/** Every selectable result in display order, used for keyboard navigation. */
const options = computed<ResultOption[]>(() => [
    ...(showEntityResults.value
        ? entityResults.value.map((entity) => ({
              kind: 'entity' as const,
              key: `entity-${entity.type}-${entity.id}`,
              entity,
          }))
        : []),
    ...filteredItems.value.map((item) => ({
        kind: 'page' as const,
        key: `page-${itemKey(item)}`,
        item,
    })),
]);

const activeOptionId = computed(() => {
    const option = options.value[activeIndex.value];

    return option ? `global-search-option-${option.key}` : undefined;
});

function optionId(key: string): string {
    return `global-search-option-${key}`;
}

function isActive(key: string): boolean {
    return options.value[activeIndex.value]?.key === key;
}

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
let latestRequestId = 0;

watch(query, (value) => {
    window.clearTimeout(entitySearchTimer);
    activeIndex.value = -1;

    const term = value.trim();

    if (term.length < 2) {
        latestRequestId += 1;
        entitySearchAbort?.abort();
        entitySearchAbort = null;
        entityResults.value = [];
        entityError.value = null;
        entityLoading.value = false;
        completedTerm.value = null;

        return;
    }

    if (term === completedTerm.value) {
        return;
    }

    entitySearchTimer = window.setTimeout(() => {
        void fetchEntityResults(term);
    }, 250);
});

async function fetchEntityResults(term: string): Promise<void> {
    entitySearchAbort?.abort();
    const controller = new AbortController();
    entitySearchAbort = controller;
    const requestId = ++latestRequestId;
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
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            throw new Error(
                `Search request failed with status ${response.status}.`,
            );
        }

        const payload = (await response.json()) as { data?: EntityResult[] };

        if (requestId !== latestRequestId) {
            return;
        }

        entityResults.value = payload.data ?? [];
    } catch (error) {
        // Superseded requests are aborted or ignored; only the newest one may update state.
        if (requestId !== latestRequestId) {
            return;
        }

        entityResults.value = [];
        entityError.value =
            error instanceof TypeError
                ? 'Records could not be searched. Check your connection and try again.'
                : 'Records could not be searched right now. Try again in a moment.';
    } finally {
        if (requestId === latestRequestId) {
            entityLoading.value = false;
            completedTerm.value = term;
        }
    }
}

function retrySearch(): void {
    if (currentTerm.value.length >= 2) {
        completedTerm.value = null;
        void fetchEntityResults(currentTerm.value);
    }
}

function moveActive(step: number): void {
    const total = options.value.length;

    if (total === 0) {
        activeIndex.value = -1;

        return;
    }

    activeIndex.value =
        activeIndex.value === -1 && step < 0
            ? total - 1
            : (activeIndex.value + step + total) % total;

    void nextTick(() => {
        if (activeOptionId.value) {
            document
                .getElementById(activeOptionId.value)
                ?.scrollIntoView({ block: 'nearest' });
        }
    });
}

function handleInputKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            moveActive(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            moveActive(-1);
            break;
        case 'Home':
            if (options.value.length > 0) {
                event.preventDefault();
                activeIndex.value = 0;
            }

            break;
        case 'End':
            if (options.value.length > 0) {
                event.preventDefault();
                activeIndex.value = options.value.length - 1;
            }

            break;
        case 'Enter': {
            // Enter opens the highlighted result, or the first one when nothing is highlighted
            // and record results have finished loading.
            const option =
                options.value[activeIndex.value] ??
                (searchPending.value ? undefined : options.value[0]);

            if (!option) {
                return;
            }

            event.preventDefault();

            if (option.kind === 'entity') {
                activateEntity(option.entity);
            } else {
                activateItem(option.item);
            }

            break;
        }
    }
}

function focusSearchInput(): void {
    const element = searchInput.value?.$el;

    if (element instanceof HTMLInputElement) {
        element.focus();
    }
}

function openDialog(): void {
    open.value = true;
    void nextTick(focusSearchInput);
}

function closeDialog(): void {
    window.clearTimeout(entitySearchTimer);
    entitySearchAbort?.abort();
    latestRequestId += 1;
    open.value = false;
    query.value = '';
    entityResults.value = [];
    entityError.value = null;
    entityLoading.value = false;
    completedTerm.value = null;
    activeIndex.value = -1;
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
                    type="search"
                    role="combobox"
                    aria-label="Search records and pages"
                    aria-autocomplete="list"
                    aria-controls="global-search-results"
                    :aria-expanded="options.length > 0"
                    :aria-activedescendant="activeOptionId"
                    autocomplete="off"
                    data-shortcut="search"
                    data-testid="global-search-input"
                    placeholder="Search products, assets, requisitions, bookings..."
                    @keydown="handleInputKeydown"
                />
                <p class="mt-2 text-xs text-muted-foreground">
                    Use the arrow keys to move through results and Enter to open
                    one.
                </p>
            </div>

            <div
                id="global-search-results"
                role="listbox"
                aria-label="Search results"
                class="max-h-105 overflow-y-auto px-2 py-2"
            >
                <div class="sr-only" aria-live="polite">
                    <template v-if="searchPending">Searching records…</template>
                    <template v-else-if="showEntityResults && !entityError">
                        {{ entityResults.length }} matching records.
                    </template>
                </div>

                <div
                    v-if="searchPending"
                    class="flex items-center gap-2 px-3 py-4 text-sm text-muted-foreground"
                    data-testid="global-search-loading"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    Searching records...
                </div>

                <div
                    v-if="entityError && !searchPending"
                    role="alert"
                    class="mx-1 mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                    data-testid="global-search-error"
                >
                    <span>{{ entityError }}</span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold hover:bg-destructive/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="retrySearch"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        Try again
                    </button>
                </div>

                <div
                    v-if="showEntityResults && entityResults.length > 0"
                    class="mb-3"
                >
                    <div
                        class="px-3 py-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Records
                    </div>
                    <button
                        v-for="item in entityResults"
                        :id="optionId(`entity-${item.type}-${item.id}`)"
                        :key="`${item.type}-${item.id}`"
                        type="button"
                        role="option"
                        tabindex="-1"
                        :aria-selected="
                            isActive(`entity-${item.type}-${item.id}`)
                        "
                        class="flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition-colors hover:bg-muted/60"
                        :class="{
                            'bg-muted ring-1 ring-primary/30': isActive(
                                `entity-${item.type}-${item.id}`,
                            ),
                        }"
                        @click="activateEntity(item)"
                        @mousemove="
                            activeIndex = options.findIndex(
                                (option) =>
                                    option.key ===
                                    `entity-${item.type}-${item.id}`,
                            )
                        "
                    >
                        <div
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                        >
                            <Search class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{
                                    item.title
                                }}</span>
                                <span
                                    class="rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase"
                                >
                                    {{
                                        entityTypeLabel[item.type] ?? item.type
                                    }}
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
                    v-if="
                        showEntityResults &&
                        entityResults.length === 0 &&
                        !entityError
                    "
                    class="px-3 py-2 text-sm text-muted-foreground"
                    data-testid="global-search-empty"
                >
                    No records match “{{ currentTerm }}”. Try a SKU, tag code,
                    PO number, or name.
                </div>

                <div
                    class="px-3 py-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Pages & actions
                </div>

                <button
                    v-for="item in filteredItems"
                    :id="optionId(`page-${itemKey(item)}`)"
                    :key="itemKey(item)"
                    type="button"
                    role="option"
                    tabindex="-1"
                    :aria-selected="isActive(`page-${itemKey(item)}`)"
                    class="flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition-colors hover:bg-muted/60"
                    :class="{
                        'bg-muted ring-1 ring-primary/30': isActive(
                            `page-${itemKey(item)}`,
                        ),
                    }"
                    @click="activateItem(item)"
                    @mousemove="
                        activeIndex = options.findIndex(
                            (option) => option.key === `page-${itemKey(item)}`,
                        )
                    "
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
