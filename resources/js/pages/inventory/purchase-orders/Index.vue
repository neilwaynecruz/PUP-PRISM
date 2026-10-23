<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
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
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'sent':
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'partial':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'cancelled':
            return 'bg-rose-500/10 text-rose-700 dark:text-rose-300';
        default:
            return 'bg-muted text-muted-foreground';
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
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="purchase-orders-index-page"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Purchase orders"
                description="Track supplier orders, expected deliveries, and how much has been received."
            />

            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-lg"
                    :disabled="generatingSource !== null"
                    @click="generateFromForecasts"
                >
                    {{
                        generatingSource === 'forecasts'
                            ? 'Creating Drafts…'
                            : 'Create Drafts from Forecasts'
                    }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-lg"
                    :disabled="generatingSource !== null"
                    @click="generateDrafts"
                >
                    {{
                        generatingSource === 'alerts'
                            ? 'Creating Drafts…'
                            : 'Create Drafts from Alerts'
                    }}
                </Button>
                <Button v-if="can.create" as-child size="sm" class="rounded-lg">
                    <Link :href="purchaseOrdersCreate()"
                        >New purchase order</Link
                    >
                </Button>
            </div>
        </div>

        <fieldset class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <legend class="sr-only">Filter purchase orders</legend>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                Search
                <Input
                    v-model="search"
                    name="purchase_order_search"
                    autocomplete="off"
                    placeholder="PO number or supplier…"
                    class="h-10 rounded-lg"
                />
            </label>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                Status
                <select
                    v-model="status"
                    name="purchase_order_status"
                    class="h-11 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:h-10"
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
            </label>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                Supplier
                <select
                    v-model="supplierId"
                    name="purchase_order_supplier"
                    class="h-11 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:h-10"
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
            </label>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                Expected on or after
                <Input
                    v-model="dateFrom"
                    name="expected_date_from"
                    type="date"
                    class="h-10 rounded-lg"
                />
            </label>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                Expected on or before
                <Input
                    v-model="dateTo"
                    name="expected_date_to"
                    type="date"
                    class="h-10 rounded-lg"
                />
            </label>
        </fieldset>

        <div class="grid gap-3 md:hidden">
            <div
                v-if="purchaseOrders.data.length === 0"
                class="rounded-xl border border-dashed border-border/60 px-4 py-10 text-center"
            >
                <p class="text-sm font-medium">No purchase orders found</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Try changing the search, supplier, status, or expected
                    delivery dates.
                </p>
            </div>
            <article
                v-for="purchaseOrder in purchaseOrders.data"
                :key="purchaseOrder.id"
                class="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold">
                            {{ purchaseOrder.po_number }}
                        </h2>
                        <p class="truncate text-sm text-muted-foreground">
                            {{
                                purchaseOrder.supplier?.name ??
                                'Supplier not listed'
                            }}
                        </p>
                    </div>
                    <span
                        class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="statusToneClass(purchaseOrder.status)"
                    >
                        {{ purchaseOrder.status_label }}
                    </span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Expected delivery</dt>
                        <dd class="font-medium">
                            {{ formatDate(purchaseOrder.expected_delivery_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Order total</dt>
                        <dd class="font-medium tabular-nums">
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
                                class="h-2 flex-1 rounded-full bg-muted"
                                role="progressbar"
                                aria-label="Receiving progress"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-valuenow="purchaseOrder.progress_pct"
                            >
                                <div
                                    class="h-2 rounded-full bg-primary"
                                    :style="{
                                        width: `${purchaseOrder.progress_pct}%`,
                                    }"
                                />
                            </div>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                >{{ purchaseOrder.progress_pct }}%</span
                            >
                        </dd>
                    </div>
                </dl>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="mt-4 w-full"
                >
                    <Link :href="purchaseOrdersShow(purchaseOrder.id)"
                        >View Details</Link
                    >
                </Button>
            </article>
        </div>

        <div
            class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm md:block"
        >
            <table class="min-w-full text-sm">
                <caption class="sr-only">
                    Purchase orders with suppliers, expected delivery dates,
                    totals, statuses, and receiving progress
                </caption>
                <thead class="bg-muted/40 text-left">
                    <tr
                        class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
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
                            class="px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            No purchase orders are available in this view. Try
                            changing the search, supplier, status, or dates.
                        </td>
                    </tr>

                    <tr
                        v-for="purchaseOrder in purchaseOrders.data"
                        :key="purchaseOrder.id"
                        class="transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3"
                    >
                        <td class="font-medium">
                            {{ purchaseOrder.po_number }}
                        </td>
                        <td>{{ purchaseOrder.supplier?.name ?? '—' }}</td>
                        <td class="text-muted-foreground">
                            {{ purchaseOrder.requester?.name ?? '—' }}
                        </td>
                        <td class="text-muted-foreground">
                            {{ formatDate(purchaseOrder.expected_delivery_at) }}
                        </td>
                        <td>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="statusToneClass(purchaseOrder.status)"
                            >
                                {{ purchaseOrder.status_label }}
                            </span>
                        </td>
                        <td class="text-right font-mono">
                            {{
                                formatPhilippinePeso(purchaseOrder.total_amount)
                            }}
                        </td>
                        <td class="text-right">
                            <div
                                class="inline-flex min-w-24 items-center gap-2"
                            >
                                <div
                                    class="h-2 flex-1 rounded-full bg-muted"
                                    role="progressbar"
                                    aria-label="Receiving progress"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    :aria-valuenow="purchaseOrder.progress_pct"
                                >
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :style="{
                                            width: `${purchaseOrder.progress_pct}%`,
                                        }"
                                    />
                                </div>
                                <span class="text-xs text-muted-foreground">
                                    {{ purchaseOrder.progress_pct }}%
                                </span>
                            </div>
                        </td>
                        <td class="text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link
                                    :href="purchaseOrdersShow(purchaseOrder.id)"
                                >
                                    View Details
                                </Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="purchaseOrders.links.length"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, index) in purchaseOrders.links"
                :key="index"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="link.active ? 'bg-primary/10 text-primary' : ''"
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                >
                    <span v-html="link.label" />
                </Link>
                <span v-else v-html="link.label" />
            </Button>
        </div>
    </div>
</template>
