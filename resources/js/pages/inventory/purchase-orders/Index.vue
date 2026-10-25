<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    Building2,
    Calendar,
    Eye,
    Plus,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Sparkles,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
import { formatPhilippinePeso } from '@/lib/utils';
import {
    create as purchaseOrdersCreate,
    index as purchaseOrdersIndex,
    show as purchaseOrdersShow,
} from '@/routes/inventory/purchase-orders';

type Option = {
    id: number;
    name: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type PurchaseOrderRow = {
    id: number;
    po_number: string;
    status: string;
    status_label: string;
    total_amount: number;
    expected_delivery_at: string | null;
    progress_pct: number;
    supplier: {
        id: number;
        name: string;
    } | null;
    requester: {
        name: string;
    } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const props = defineProps<{
    filters: {
        search: string;
        status: string;
        supplier_id: number | null;
        date_from: string;
        date_to: string;
    };
    purchaseOrders: Paginated<PurchaseOrderRow>;
    suppliers: Option[];
    statuses: StatusOption[];
    can: {
        create: boolean;
    };
}>();

defineOptions({
    name: 'InventoryPurchaseOrderIndexPage',
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: purchaseOrdersIndex() },
            { title: 'Purchase Orders', href: purchaseOrdersIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const supplierId = ref(
    props.filters.supplier_id ? String(props.filters.supplier_id) : '',
);
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const generatingSource = ref<'forecasts' | 'alerts' | null>(null);

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        status.value !== '' ||
        supplierId.value !== '' ||
        dateFrom.value !== '' ||
        dateTo.value !== '',
);

function resetFilters(): void {
    search.value = '';
    status.value = '';
    supplierId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
}

let refreshTimer: number | undefined;
let isComponentActive = true;

watch([search, status, supplierId, dateFrom, dateTo], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        if (
            !isComponentActive ||
            !shouldApplyDebouncedVisit('/inventory/purchase-orders')
        ) {
            return;
        }

        router.get(
            purchaseOrdersIndex().url,
            {
                search: search.value || undefined,
                status: status.value || undefined,
                supplier_id: supplierId.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 250);
});

onBeforeUnmount(() => {
    isComponentActive = false;
    window.clearTimeout(refreshTimer);
});

function generateDrafts(): void {
    if (generatingSource.value) {
        return;
    }

    generatingSource.value = 'alerts';
    router.post(
        PurchaseOrderController.generate().url,
        {},
        {
            onFinish: () => {
                generatingSource.value = null;
            },
        },
    );
}

function generateFromForecasts(): void {
    if (generatingSource.value) {
        return;
    }

    generatingSource.value = 'forecasts';
    router.post(
        PurchaseOrderController.generateFromForecasts().url,
        {},
        {
            onFinish: () => {
                generatingSource.value = null;
            },
        },
    );
}

function statusToneClass(statusValue: string): string {
    switch (statusValue.toLowerCase()) {
        case 'received':
            return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300';
        case 'sent':
            return 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:border-blue-400/20 dark:bg-blue-400/10 dark:text-blue-300';
        case 'partial':
            return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300';
        case 'cancelled':
            return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300';
        default:
            return 'border-border/60 bg-muted/60 text-muted-foreground';
    }
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Purchase Orders" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="purchase-orders-index-page"
    >
        <!-- Header & Action Toolbar -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <Heading
                    variant="small"
                    title="Purchase orders"
                    description="Track supplier procurement orders, expected delivery dates, and receiving fulfillment."
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Automated Draft Actions -->
                <div
                    class="flex items-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 p-0.5"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                        :disabled="generatingSource !== null"
                        @click="generateFromForecasts"
                    >
                        <TrendingUp
                            class="h-3.5 w-3.5 text-violet-600 dark:text-violet-400"
                        />
                        <span>{{
                            generatingSource === 'forecasts'
                                ? 'Creating Drafts…'
                                : 'Drafts from Forecasts'
                        }}</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                        :disabled="generatingSource !== null"
                        @click="generateDrafts"
                    >
                        <Sparkles
                            class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400"
                        />
                        <span>{{
                            generatingSource === 'alerts'
                                ? 'Creating Drafts…'
                                : 'Drafts from Alerts'
                        }}</span>
                    </Button>
                </div>

                <Button
                    v-if="can.create"
                    as-child
                    size="sm"
                    class="h-9 gap-1.5 rounded-lg px-3 text-xs"
                >
                    <Link :href="purchaseOrdersCreate()">
                        <Plus class="h-3.5 w-3.5" />
                        New purchase order
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Filter Bar Card -->
        <div class="rounded-xl border border-border/70 bg-card p-4 shadow-xs">
            <div class="mb-3 flex items-center justify-between">
                <div
                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-foreground uppercase"
                >
                    <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
                    <span>Search & Filters</span>
                    <span
                        v-if="hasActiveFilters"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-[0.65rem] font-bold text-primary"
                    >
                        Active
                    </span>
                </div>

                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click="resetFilters"
                >
                    <RotateCcw class="h-3 w-3" />
                    Clear filters
                </Button>
            </div>

            <fieldset
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <legend class="sr-only">Filter purchase orders</legend>
                <div class="grid gap-1">
                    <label
                        for="po-search"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Search
                    </label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <Input
                            id="po-search"
                            v-model="search"
                            name="purchase_order_search"
                            autocomplete="off"
                            placeholder="PO number or supplier…"
                            class="h-10 rounded-lg pl-9 text-sm"
                        />
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="po-status"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Status
                    </label>
                    <div class="relative">
                        <Activity
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <select
                            id="po-status"
                            v-model="status"
                            name="purchase_order_status"
                            class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        >
                            <option value="">All statuses</option>
                            <option
                                v-for="statusOption in statuses"
                                :key="statusOption.value"
                                :value="statusOption.value"
                            >
                                {{ statusOption.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="po-supplier"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Supplier
                    </label>
                    <div class="relative">
                        <Building2
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <select
                            id="po-supplier"
                            v-model="supplierId"
                            name="purchase_order_supplier"
                            class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        >
                            <option value="">All suppliers</option>
                            <option
                                v-for="supplier in suppliers"
                                :key="supplier.id"
                                :value="String(supplier.id)"
                            >
                                {{ supplier.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="po-expected-from"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Expected on or after
                    </label>
                    <div class="relative">
                        <Calendar
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <Input
                            id="po-expected-from"
                            v-model="dateFrom"
                            name="expected_date_from"
                            type="date"
                            class="h-10 rounded-lg pl-9 text-sm"
                        />
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="po-expected-to"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Expected on or before
                    </label>
                    <div class="relative">
                        <Calendar
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <Input
                            id="po-expected-to"
                            v-model="dateTo"
                            name="expected_date_to"
                            type="date"
                            class="h-10 rounded-lg pl-9 text-sm"
                        />
                    </div>
                </div>
            </fieldset>
        </div>

        <!-- Mobile Cards Presentation -->
        <div class="grid gap-3 md:hidden">
            <div
                v-if="purchaseOrders.data.length === 0"
                class="rounded-xl border border-dashed border-border/70 p-8 text-center"
            >
                <p class="text-sm font-semibold text-foreground">
                    No purchase orders found
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Try changing your search keywords, supplier, status, or
                    expected delivery dates.
                </p>
                <Button
                    v-if="hasActiveFilters"
                    variant="outline"
                    size="sm"
                    class="mt-3 gap-1 rounded-lg text-xs"
                    @click="resetFilters"
                >
                    <RotateCcw class="h-3 w-3" />
                    Clear filters
                </Button>
            </div>
            <article
                v-for="purchaseOrder in purchaseOrders.data"
                :key="purchaseOrder.id"
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <AdminCodeBadge :value="purchaseOrder.po_number" />
                        <p
                            class="mt-1.5 truncate text-sm font-medium text-foreground"
                        >
                            {{
                                purchaseOrder.supplier?.name ??
                                'Supplier not listed'
                            }}
                        </p>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                        :class="statusToneClass(purchaseOrder.status)"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current" />
                        {{ purchaseOrder.status_label }}
                    </span>
                </div>
                <dl class="mt-4 grid gap-2 text-xs sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Expected delivery</dt>
                        <dd class="mt-0.5 font-medium text-foreground">
                            {{ formatDate(purchaseOrder.expected_delivery_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Order total</dt>
                        <dd
                            class="mt-0.5 font-mono font-semibold text-foreground"
                        >
                            {{
                                formatPhilippinePeso(purchaseOrder.total_amount)
                            }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted-foreground">
                            Receiving progress
                        </dt>
                        <dd class="mt-1 flex items-center gap-2">
                            <div
                                class="h-1.5 flex-1 rounded-full bg-muted"
                                role="progressbar"
                                aria-label="Receiving progress"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-valuenow="purchaseOrder.progress_pct"
                            >
                                <div
                                    class="h-1.5 rounded-full bg-primary"
                                    :style="{
                                        width: `${purchaseOrder.progress_pct}%`,
                                    }"
                                />
                            </div>
                            <span
                                class="text-xs font-medium text-muted-foreground tabular-nums"
                                >{{ purchaseOrder.progress_pct }}%</span
                            >
                        </dd>
                    </div>
                </dl>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="mt-4 h-9 w-full gap-1.5 text-xs"
                >
                    <Link :href="purchaseOrdersShow(purchaseOrder.id)">
                        <Eye class="h-3.5 w-3.5" />
                        View Details
                    </Link>
                </Button>
            </article>
        </div>

        <!-- Desktop Table Presentation -->
        <div
            class="hidden overflow-hidden rounded-xl border border-border/70 bg-card shadow-xs md:block"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">
                        Purchase orders with suppliers, expected delivery dates,
                        totals, statuses, and receiving progress
                    </caption>
                    <thead
                        class="border-b border-border/60 bg-muted/40 text-left"
                    >
                        <tr
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase [&>th]:px-4 [&>th]:py-3"
                        >
                            <th scope="col">PO number</th>
                            <th scope="col">Supplier</th>
                            <th scope="col">Requester</th>
                            <th scope="col">Expected delivery</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Total</th>
                            <th scope="col" class="text-right">
                                Receiving progress
                            </th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-if="purchaseOrders.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-12 text-center text-sm text-muted-foreground"
                            >
                                No purchase orders are available in this view.
                                Try changing your search or filter parameters.
                            </td>
                        </tr>

                        <tr
                            v-for="purchaseOrder in purchaseOrders.data"
                            :key="purchaseOrder.id"
                            class="transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3.5"
                        >
                            <td>
                                <AdminCodeBadge
                                    :value="purchaseOrder.po_number"
                                />
                            </td>
                            <td class="font-medium text-foreground">
                                {{ purchaseOrder.supplier?.name ?? '—' }}
                            </td>
                            <td class="text-xs text-muted-foreground">
                                {{ purchaseOrder.requester?.name ?? '—' }}
                            </td>
                            <td class="text-xs text-muted-foreground">
                                {{
                                    formatDate(
                                        purchaseOrder.expected_delivery_at,
                                    )
                                }}
                            </td>
                            <td>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                    :class="
                                        statusToneClass(purchaseOrder.status)
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-current"
                                    />
                                    {{ purchaseOrder.status_label }}
                                </span>
                            </td>
                            <td
                                class="text-right font-mono font-semibold text-foreground"
                            >
                                {{
                                    formatPhilippinePeso(
                                        purchaseOrder.total_amount,
                                    )
                                }}
                            </td>
                            <td class="text-right">
                                <div
                                    class="inline-flex min-w-28 items-center justify-end gap-2"
                                >
                                    <div
                                        class="h-1.5 w-16 overflow-hidden rounded-full bg-muted"
                                        role="progressbar"
                                        aria-label="Receiving progress"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        :aria-valuenow="
                                            purchaseOrder.progress_pct
                                        "
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary"
                                            :style="{
                                                width: `${purchaseOrder.progress_pct}%`,
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="font-mono text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ purchaseOrder.progress_pct }}%
                                    </span>
                                </div>
                            </td>
                            <td class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="h-8 gap-1 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <Link
                                        :href="
                                            purchaseOrdersShow(purchaseOrder.id)
                                        "
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                        <span>View</span>
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination :links="purchaseOrders.links" />
    </div>
</template>
