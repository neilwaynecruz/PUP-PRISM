<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDownRight,
    ArrowUpDown,
    ArrowUpRight,
    BookOpen,
    ClipboardCheck,
    Download,
    Eye,
    FileSpreadsheet,
    Filter,
    RotateCcw,
    Search,
    SlidersHorizontal,
} from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    onBeforeUnmount,
    reactive,
    ref,
    watch,
} from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Inventory/StockMovementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProductSelectDialog from '@/components/inventory/ProductSelectDialog.vue';
import type { ProductLookupItem } from '@/components/inventory/ProductSelectDialog.vue';
import { Badge } from '@/components/ui/badge';

const QrScannerDialog = defineAsyncComponent(
    () => import('@/components/inventory/QrScannerDialog.vue'),
);
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { addCalendarDays, manilaCalendarDate } from '@/lib/dateFormatting';
import { index } from '@/routes/inventory/movements';

type MovementRow = {
    id: number;
    movement_type: string;
    reason_code?: string | null;
    qty_delta?: number | null;
    qty_before?: number | null;
    qty_after?: number | null;
    counted_qty?: number | null;
    variance_qty?: number | null;
    performed_at?: string | null;
    ip_address?: string | null;
    notes?: string | null;
    product?: {
        id: number;
        sku: string;
        name: string;
        variant?: string | null;
        unit?: string | null;
    } | null;
    stock_lot?: {
        id: number;
        reference_no?: string | null;
        received_at?: string | null;
        expires_at?: string | null;
    } | null;
    asset?: { id: number; tag_code: string; status: string } | null;
    performed_by?: { id: number; name: string; email: string } | null;
    accountable_position?: {
        title: string;
        code: string;
        department?: string | null;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedMovements = {
    data: MovementRow[];
    links: PaginationLink[];
    current_page?: number;
    last_page?: number;
    total?: number;
    meta?: {
        current_page?: number;
        last_page?: number;
        total?: number;
    };
};

type UserOption = {
    id: number;
    name: string;
    email: string;
};

type ProductOption = {
    id: number;
    sku: string;
    name: string;
    category?: string | null;
    on_hand_qty?: number;
    reorder_threshold?: number;
};

type ReasonCodeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    filters: {
        type: string;
        search: string;
        date_from?: string | null;
        date_to?: string | null;
        performed_by?: number | null;
        sort: string;
        direction: string;
    };
    movements: PaginatedMovements;
    users: UserOption[];
    products: ProductOption[];
    adjustmentReasonCodes: ReasonCodeOption[];
    cycleCountReasonCodes: ReasonCodeOption[];
    exportUrls: {
        csv: string;
        pdf: string;
    };
}>();

defineOptions({
    name: 'StockMovementIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Movements', href: StockMovementController.index() },
        ],
    },
});

const totalMovementCount = computed(
    () =>
        props.movements.total ??
        props.movements.meta?.total ??
        props.movements.data.length,
);

const currentMovementPage = computed(
    () =>
        props.movements.current_page ?? props.movements.meta?.current_page ?? 1,
);

const lastMovementPage = computed(
    () => props.movements.last_page ?? props.movements.meta?.last_page ?? 1,
);

const filterState = reactive({
    type: props.filters.type ?? '',
    search: props.filters.search ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    performed_by: props.filters.performed_by
        ? String(props.filters.performed_by)
        : '',
    sort: props.filters.sort ?? 'performed_at',
    direction: props.filters.direction ?? 'desc',
});

// ============================================================================
// Manual Adjustment & Cycle Count Interactive State
// ============================================================================
const adjustProductId = ref('');
const adjustQtyDelta = ref('');
const adjustReasonCode = ref(props.adjustmentReasonCodes[0]?.value ?? '');
const adjustNotes = ref('');

const cycleProductId = ref('');
const cycleCountedQty = ref('');
const cycleReasonCode = ref(props.cycleCountReasonCodes[0]?.value ?? '');
const cycleNotes = ref('');

const isCatalogOpen = ref(false);
const catalogTarget = ref<'adjust' | 'cycle'>('adjust');

const selectedAdjustProduct = computed<ProductOption | null>(() => {
    const id = Number(adjustProductId.value || 0);

    if (!id) {
        return null;
    }

    return props.products.find((p) => p.id === id) ?? null;
});

const projectedAdjustStock = computed<number | null>(() => {
    if (!selectedAdjustProduct.value || adjustQtyDelta.value === '') {
        return null;
    }

    const delta = Number(adjustQtyDelta.value);

    if (!Number.isFinite(delta)) {
        return null;
    }

    return (selectedAdjustProduct.value.on_hand_qty ?? 0) + delta;
});

const selectedCycleProduct = computed<ProductOption | null>(() => {
    const id = Number(cycleProductId.value || 0);

    if (!id) {
        return null;
    }

    return props.products.find((p) => p.id === id) ?? null;
});

const calculatedCycleVariance = computed<number | null>(() => {
    if (!selectedCycleProduct.value || cycleCountedQty.value === '') {
        return null;
    }

    const counted = Number(cycleCountedQty.value);

    if (!Number.isFinite(counted)) {
        return null;
    }

    return counted - (selectedCycleProduct.value.on_hand_qty ?? 0);
});

function openCatalogFor(target: 'adjust' | 'cycle'): void {
    catalogTarget.value = target;
    isCatalogOpen.value = true;
}

function handleCatalogSelect(lookupItem: ProductLookupItem): void {
    const matched = props.products.find(
        (p) =>
            p.id === lookupItem.id ||
            p.sku.toLowerCase() === lookupItem.sku.toLowerCase(),
    );

    if (!matched) {
        return;
    }

    if (catalogTarget.value === 'adjust') {
        adjustProductId.value = String(matched.id);
    } else {
        cycleProductId.value = String(matched.id);
    }
}

function handleScanFor(target: 'adjust' | 'cycle', rawCode: string): void {
    const cleaned = rawCode.trim().toLowerCase();
    const matched = props.products.find((p) => p.sku.toLowerCase() === cleaned);

    if (!matched) {
        return;
    }

    if (target === 'adjust') {
        adjustProductId.value = String(matched.id);
    } else {
        cycleProductId.value = String(matched.id);
    }
}

// ============================================================================
// Filters, Sorting & Date Presets
// ============================================================================
const activeFilterCount = computed(() => {
    let count = 0;

    if (filterState.type) {
        count += 1;
    }

    if (filterState.search.trim()) {
        count += 1;
    }

    if (filterState.date_from) {
        count += 1;
    }

    if (filterState.date_to) {
        count += 1;
    }

    if (filterState.performed_by) {
        count += 1;
    }

    return count;
});

function applyFilters(): void {
    router.get(
        index().url,
        {
            type: filterState.type || undefined,
            search: filterState.search || undefined,
            date_from: filterState.date_from || undefined,
            date_to: filterState.date_to || undefined,
            performed_by: filterState.performed_by
                ? Number(filterState.performed_by)
                : undefined,
            sort:
                filterState.sort !== 'performed_at'
                    ? filterState.sort
                    : undefined,
            direction:
                filterState.direction !== 'desc'
                    ? filterState.direction
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function resetFilters(): void {
    filterState.type = '';
    filterState.search = '';
    filterState.date_from = '';
    filterState.date_to = '';
    filterState.performed_by = '';
    filterState.sort = 'performed_at';
    filterState.direction = 'desc';
    applyFilters();
}

function applyDatePreset(
    preset: 'today' | '7d' | '30d' | 'month' | 'all',
): void {
    const todayIso = manilaCalendarDate();

    if (preset === 'all') {
        filterState.date_from = '';
        filterState.date_to = '';
    } else if (preset === 'today') {
        filterState.date_from = todayIso;
        filterState.date_to = todayIso;
    } else if (preset === '7d') {
        filterState.date_from = addCalendarDays(todayIso, -7);
        filterState.date_to = todayIso;
    } else if (preset === '30d') {
        filterState.date_from = addCalendarDays(todayIso, -30);
        filterState.date_to = todayIso;
    } else if (preset === 'month') {
        filterState.date_from = `${todayIso.slice(0, 7)}-01`;
        filterState.date_to = todayIso;
    }

    applyFilters();
}

function toggleSort(column: string): void {
    if (filterState.sort === column) {
        filterState.direction =
            filterState.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filterState.sort = column;
        filterState.direction = 'desc';
    }

    applyFilters();
}

function sortIndicator(column: string): string {
    if (filterState.sort !== column) {
        return '';
    }

    return filterState.direction === 'asc' ? '↑' : '↓';
}

function ariaSortFor(column: string): 'ascending' | 'descending' | 'none' {
    if (filterState.sort !== column) {
        return 'none';
    }

    return filterState.direction === 'asc' ? 'ascending' : 'descending';
}

// ============================================================================
// Page KPI Stats & Formatting
// ============================================================================
const pageStats = computed(() => {
    let inflows = 0;
    let outflows = 0;
    let reconciliations = 0;

    props.movements.data.forEach((m) => {
        if (m.movement_type === 'receive' || m.movement_type === 'return') {
            inflows += 1;
        } else if (
            m.movement_type === 'issue' ||
            m.movement_type === 'dispose'
        ) {
            outflows += 1;
        } else {
            reconciliations += 1;
        }
    });

    return { inflows, outflows, reconciliations };
});

function movementLabel(type: string): string {
    const labels: Record<string, string> = {
        receive: 'Stock Received',
        issue: 'Stock Issued',
        transfer: 'Asset Transfer',
        return: 'Asset Returned',
        dispose: 'Disposed',
        maintenance: 'Maintenance',
        adjustment: 'Adjustment',
        cycle_count: 'Cycle Count',
    };

    return labels[type] ?? type.replaceAll('_', ' ');
}

function movementBadgeClass(type: string): string {
    switch (type) {
        case 'receive':
        case 'return':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'issue':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'adjustment':
        case 'cycle_count':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300';
        case 'dispose':
            return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
        default:
            return 'border-border bg-muted/50 text-foreground';
    }
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function formatReasonCode(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return value
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

// ============================================================================
// Audit Detail Modal
// ============================================================================
const inspectedMovement = ref<MovementRow | null>(null);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => filterState.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            applyFilters();
        }, 300);
    },
);

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
});
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6"
        data-testid="movements-index-page"
    >
        <Head title="Stock Movements" />

        <!-- Header Banner -->
        <div
            class="relative overflow-hidden rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-52 rounded-full bg-primary/5 blur-2xl"
            />
            <div
                class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-2xs"
                    >
                        <Activity class="size-5" />
                    </div>
                    <Heading
                        title="Stock movements"
                        description="Audit-ready inventory ledger for stock receipts, issuances, transfers, adjustments, and cycle counts."
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button as-child variant="outline" size="sm">
                        <a :href="exportUrls.csv">
                            <FileSpreadsheet class="mr-1.5 size-4" />
                            Export CSV
                        </a>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <a :href="exportUrls.pdf">
                            <Download class="mr-1.5 size-4" />
                            Export PDF
                        </a>
                    </Button>
                </div>
            </div>

            <!-- Summary KPI Strip -->
            <div
                class="mt-5 grid grid-cols-2 gap-3 border-t border-border/60 pt-4 sm:grid-cols-4"
            >
                <div
                    class="rounded-xl border border-border/60 bg-muted/20 px-3.5 py-2.5"
                >
                    <div class="text-[11px] text-muted-foreground">
                        Matching ledger records
                    </div>
                    <div class="mt-0.5 font-mono text-lg font-bold">
                        {{ totalMovementCount }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-emerald-500/25 bg-emerald-500/[0.04] px-3.5 py-2.5"
                >
                    <div
                        class="flex items-center gap-1 text-[11px] text-emerald-700 dark:text-emerald-300"
                    >
                        <ArrowUpRight class="size-3.5" />
                        <span>Receipts &amp; returns (page)</span>
                    </div>
                    <div
                        class="mt-0.5 font-mono text-lg font-bold text-emerald-700 dark:text-emerald-300"
                    >
                        {{ pageStats.inflows }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-amber-500/25 bg-amber-500/[0.04] px-3.5 py-2.5"
                >
                    <div
                        class="flex items-center gap-1 text-[11px] text-amber-700 dark:text-amber-300"
                    >
                        <ArrowDownRight class="size-3.5" />
                        <span>Issuances &amp; disposals (page)</span>
                    </div>
                    <div
                        class="mt-0.5 font-mono text-lg font-bold text-amber-700 dark:text-amber-300"
                    >
                        {{ pageStats.outflows }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-sky-500/25 bg-sky-500/[0.04] px-3.5 py-2.5"
                >
                    <div
                        class="flex items-center gap-1 text-[11px] text-sky-700 dark:text-sky-300"
                    >
                        <ClipboardCheck class="size-3.5" />
                        <span>Adjustments &amp; counts (page)</span>
                    </div>
                    <div
                        class="mt-0.5 font-mono text-lg font-bold text-sky-700 dark:text-sky-300"
                    >
                        {{ pageStats.reconciliations }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Actionable Stock Adjustment & Cycle Count Cards -->
        <div class="grid gap-6 xl:grid-cols-2">
            <!-- Card 1: Manual Stock Adjustment -->
            <Form
                v-bind="StockMovementController.adjust.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col justify-between gap-4 rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
            >
                <div class="space-y-4">
                    <div
                        class="flex flex-col gap-3 border-b border-border/60 pb-3.5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <SlidersHorizontal class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">
                                    Manual stock adjustment
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Record a signed quantity delta with an
                                    auditable reason code.
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="openCatalogFor('adjust')"
                            >
                                <BookOpen class="mr-1.5 size-3.5" />
                                Browse
                            </Button>
                            <QrScannerDialog
                                button-label="Scan SKU"
                                button-size="sm"
                                title="Scan consumable SKU for adjustment"
                                description="Scan a product QR code to select it for manual stock adjustment."
                                @scanned="handleScanFor('adjust', $event)"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div
                            class="grid min-w-0 content-start gap-1.5 md:col-span-2"
                        >
                            <Label
                                for="adjustment-product-id"
                                class="font-medium"
                            >
                                Consumable product
                            </Label>
                            <select
                                id="adjustment-product-id"
                                v-model="adjustProductId"
                                name="product_id"
                                required
                                class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Select product</option>
                                <option
                                    v-for="product in products"
                                    :key="product.id"
                                    :value="String(product.id)"
                                >
                                    {{ product.sku }} -
                                    {{ product.name }} (Stock:
                                    {{ product.on_hand_qty ?? 0 }})
                                </option>
                            </select>
                            <InputError :message="errors.product_id" />
                        </div>

                        <div class="grid min-w-0 content-start gap-1.5">
                            <Label
                                for="adjustment-qty-delta"
                                class="font-medium"
                            >
                                Quantity delta
                            </Label>
                            <Input
                                id="adjustment-qty-delta"
                                v-model="adjustQtyDelta"
                                name="qty_delta"
                                type="number"
                                step="1"
                                placeholder="-3 or 12"
                                required
                                class="font-mono"
                            />
                            <InputError :message="errors.qty_delta" />
                        </div>
                    </div>

                    <!-- Live Stock Impact Preview -->
                    <div
                        v-if="selectedAdjustProduct"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3.5 py-2 text-xs"
                        :class="
                            projectedAdjustStock !== null &&
                            projectedAdjustStock < 0
                                ? 'border-destructive/40 bg-destructive/10 text-destructive'
                                : 'border-border/60 bg-muted/25 text-muted-foreground'
                        "
                    >
                        <span>
                            Current on hand:
                            <strong class="font-mono text-foreground">{{
                                selectedAdjustProduct.on_hand_qty ?? 0
                            }}</strong>
                        </span>
                        <span
                            v-if="projectedAdjustStock !== null"
                            class="font-medium"
                        >
                            Projected stock:
                            <strong
                                class="font-mono"
                                :class="
                                    projectedAdjustStock < 0
                                        ? 'text-destructive'
                                        : 'text-primary'
                                "
                            >
                                {{ projectedAdjustStock }}
                            </strong>
                            <span v-if="projectedAdjustStock < 0">
                                (Cannot reduce below 0)
                            </span>
                        </span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid min-w-0 content-start gap-1.5">
                            <Label
                                for="adjustment-reason-code"
                                class="font-medium"
                            >
                                Reason code
                            </Label>
                            <select
                                id="adjustment-reason-code"
                                v-model="adjustReasonCode"
                                name="reason_code"
                                required
                                class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="reason in adjustmentReasonCodes"
                                    :key="reason.value"
                                    :value="reason.value"
                                >
                                    {{ reason.label }}
                                </option>
                            </select>
                            <InputError :message="errors.reason_code" />
                        </div>

                        <div class="grid min-w-0 content-start gap-1.5">
                            <Label for="adjustment-notes" class="font-medium">
                                Audit notes
                            </Label>
                            <Input
                                id="adjustment-notes"
                                v-model="adjustNotes"
                                name="notes"
                                required
                                placeholder="Explain why the stock changed…"
                            />
                            <InputError :message="errors.notes" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="w-full sm:w-auto"
                    >
                        {{ processing ? 'Recording…' : 'Record Adjustment' }}
                    </Button>
                </div>
            </Form>

            <!-- Card 2: Cycle Count Reconciliation -->
            <Form
                v-bind="StockMovementController.cycleCount.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col justify-between gap-4 rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
            >
                <div class="space-y-4">
                    <div
                        class="flex flex-col gap-3 border-b border-border/60 pb-3.5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300"
                            >
                                <ClipboardCheck class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">
                                    Cycle count reconciliation
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Enter physical shelf quantity so PRISM
                                    calculates and logs the variance.
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="openCatalogFor('cycle')"
                            >
                                <BookOpen class="mr-1.5 size-3.5" />
                                Browse
                            </Button>
                            <QrScannerDialog
                                button-label="Scan SKU"
                                button-size="sm"
                                title="Scan consumable SKU for cycle count"
                                description="Scan a product QR code to select it for cycle count reconciliation."
                                @scanned="handleScanFor('cycle', $event)"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div
                            class="grid min-w-0 content-start gap-1.5 md:col-span-2"
                        >
                            <Label
                                for="cycle-count-product-id"
                                class="font-medium"
                            >
                                Consumable product
                            </Label>
                            <select
                                id="cycle-count-product-id"
                                v-model="cycleProductId"
                                name="product_id"
                                required
                                class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Select product</option>
                                <option
                                    v-for="product in products"
                                    :key="product.id"
                                    :value="String(product.id)"
                                >
                                    {{ product.sku }} -
                                    {{ product.name }} (Stock:
                                    {{ product.on_hand_qty ?? 0 }})
                                </option>
                            </select>
                            <InputError :message="errors.product_id" />
                        </div>

                        <div class="grid min-w-0 content-start gap-1.5">
                            <Label for="cycle-counted-qty" class="font-medium">
                                Counted quantity
                            </Label>
                            <Input
                                id="cycle-counted-qty"
                                v-model="cycleCountedQty"
                                name="counted_qty"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="0"
                                required
                                class="font-mono"
                            />
                            <InputError :message="errors.counted_qty" />
                        </div>
                    </div>

                    <!-- Live Cycle Count Variance Preview -->
                    <div
                        v-if="selectedCycleProduct"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border/60 bg-muted/25 px-3.5 py-2 text-xs text-muted-foreground"
                    >
                        <span>
                            System on-hand:
                            <strong class="font-mono text-foreground">{{
                                selectedCycleProduct.on_hand_qty ?? 0
                            }}</strong>
                        </span>
                        <span
                            v-if="calculatedCycleVariance !== null"
                            class="font-medium"
                        >
                            Variance delta:
                            <strong
                                class="font-mono"
                                :class="
                                    calculatedCycleVariance > 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : calculatedCycleVariance < 0
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-foreground'
                                "
                            >
                                {{
                                    calculatedCycleVariance > 0
                                        ? `+${calculatedCycleVariance}`
                                        : calculatedCycleVariance
                                }}
                            </strong>
                        </span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid min-w-0 content-start gap-1.5">
                            <Label for="cycle-reason-code" class="font-medium">
                                Reason code
                            </Label>
                            <select
                                id="cycle-reason-code"
                                v-model="cycleReasonCode"
                                name="reason_code"
                                required
                                class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="reason in cycleCountReasonCodes"
                                    :key="reason.value"
                                    :value="reason.value"
                                >
                                    {{ reason.label }}
                                </option>
                            </select>
                            <InputError :message="errors.reason_code" />
                        </div>

                        <div class="grid min-w-0 content-start gap-1.5">
                            <Label for="cycle-notes" class="font-medium">
                                Audit notes
                            </Label>
                            <Input
                                id="cycle-notes"
                                v-model="cycleNotes"
                                name="notes"
                                required
                                placeholder="Record bin location and count context…"
                            />
                            <InputError :message="errors.notes" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="w-full sm:w-auto"
                    >
                        {{ processing ? 'Recording…' : 'Record Cycle Count' }}
                    </Button>
                </div>
            </Form>
        </div>

        <!-- Search, Filtering & Date Range Controls -->
        <section
            class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
        >
            <div
                class="mb-3.5 flex flex-wrap items-center justify-between gap-2"
            >
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <Filter class="size-3.5 text-primary" />
                    <span>Search &amp; filter ledger</span>
                    <Badge
                        v-if="activeFilterCount > 0"
                        variant="secondary"
                        class="h-5 px-1.5 text-[10px]"
                    >
                        {{ activeFilterCount }} active
                    </Badge>
                </div>

                <!-- Quick Date Range Presets -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] text-muted-foreground"
                        >Date range:</span
                    >
                    <button
                        type="button"
                        class="rounded-md border border-border/70 bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="applyDatePreset('today')"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-border/70 bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="applyDatePreset('7d')"
                    >
                        Last 7 days
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-border/70 bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="applyDatePreset('month')"
                    >
                        This month
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-border/70 bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="applyDatePreset('30d')"
                    >
                        Last 30 days
                    </button>
                    <Button
                        v-if="activeFilterCount > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-6 px-2 text-[11px] text-primary"
                        @click="resetFilters"
                    >
                        <RotateCcw class="mr-1 size-3" />
                        Reset filters
                    </Button>
                </div>
            </div>

            <div
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.6fr)_repeat(4,minmax(0,1fr))]"
            >
                <div class="grid min-w-0 gap-1.5">
                    <Label for="movement-search" class="text-xs">Search</Label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="movement-search"
                            v-model="filterState.search"
                            placeholder="Search SKU, product name, asset tag, lot reference, user, or notes…"
                            class="pl-9"
                        />
                    </div>
                </div>

                <div class="grid min-w-0 gap-1.5">
                    <Label for="movement-type" class="text-xs"
                        >Movement type</Label
                    >
                    <select
                        id="movement-type"
                        v-model="filterState.type"
                        class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All types</option>
                        <option value="receive">Receive</option>
                        <option value="issue">Issue</option>
                        <option value="transfer">Transfer</option>
                        <option value="return">Return</option>
                        <option value="dispose">Dispose</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="adjustment">Adjustment</option>
                        <option value="cycle_count">Cycle Count</option>
                    </select>
                </div>

                <div class="grid min-w-0 gap-1.5">
                    <Label for="movement-user" class="text-xs"
                        >Performed by</Label
                    >
                    <select
                        id="movement-user"
                        v-model="filterState.performed_by"
                        class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All users</option>
                        <option
                            v-for="user in users"
                            :key="user.id"
                            :value="String(user.id)"
                        >
                            {{ user.name }}
                        </option>
                    </select>
                </div>

                <div class="grid min-w-0 gap-1.5">
                    <Label for="movement-date-from" class="text-xs"
                        >Date from</Label
                    >
                    <Input
                        id="movement-date-from"
                        v-model="filterState.date_from"
                        type="date"
                        @change="applyFilters"
                    />
                </div>

                <div class="grid min-w-0 gap-1.5">
                    <Label for="movement-date-to" class="text-xs"
                        >Date to</Label
                    >
                    <Input
                        id="movement-date-to"
                        v-model="filterState.date_to"
                        type="date"
                        @change="applyFilters"
                    />
                </div>
            </div>
        </section>

        <!-- Mobile Movement Cards -->
        <div class="space-y-3 md:hidden">
            <article
                v-for="m in movements.data"
                :key="`mobile-${m.id}`"
                class="rounded-2xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold">
                            {{
                                m.product
                                    ? `${m.product.sku} - ${m.product.name}`
                                    : 'Unassigned product'
                            }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{ formatDate(m.performed_at) }}
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                        :class="movementBadgeClass(m.movement_type)"
                    >
                        {{ movementLabel(m.movement_type) }}
                    </span>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <dt class="text-muted-foreground">Qty delta</dt>
                        <dd class="font-mono font-semibold">
                            {{
                                m.qty_delta !== null &&
                                m.qty_delta !== undefined
                                    ? m.qty_delta > 0
                                        ? `+${m.qty_delta}`
                                        : m.qty_delta
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Reason</dt>
                        <dd>{{ formatReasonCode(m.reason_code) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Asset / Lot</dt>
                        <dd class="font-mono">
                            {{
                                m.asset?.tag_code ??
                                m.stock_lot?.reference_no ??
                                '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">User</dt>
                        <dd>{{ m.performed_by?.name ?? '—' }}</dd>
                    </div>
                </dl>

                <div
                    v-if="m.notes"
                    class="mt-2.5 rounded-lg bg-muted/30 p-2 text-xs text-muted-foreground"
                >
                    {{ m.notes }}
                </div>

                <div class="mt-3 flex justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs"
                        @click="inspectedMovement = m"
                    >
                        <Eye class="mr-1 size-3.5" />
                        Inspect details
                    </Button>
                </div>
            </article>

            <div
                v-if="movements.data.length === 0"
                class="rounded-2xl border border-border/70 bg-card px-4 py-8 text-center text-sm text-muted-foreground"
            >
                No stock movements match the current filters.
            </div>
        </div>

        <!-- Desktop Ledger Table (Cleanly Proportioned Columns) -->
        <div
            class="hidden overflow-x-auto rounded-2xl border border-border/70 bg-card shadow-xs md:block"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-border/60 bg-muted/30 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    <tr>
                        <th
                            class="px-4 py-3 whitespace-nowrap"
                            :aria-sort="ariaSortFor('performed_at')"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 font-semibold hover:text-foreground"
                                @click="toggleSort('performed_at')"
                            >
                                <span>Date &amp; time</span>
                                <span v-if="sortIndicator('performed_at')">
                                    {{ sortIndicator('performed_at') }}
                                </span>
                                <ArrowUpDown v-else class="size-3 opacity-50" />
                            </button>
                        </th>
                        <th
                            class="px-3 py-3 whitespace-nowrap"
                            :aria-sort="ariaSortFor('movement_type')"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 font-semibold hover:text-foreground"
                                @click="toggleSort('movement_type')"
                            >
                                <span>Classification</span>
                                <span v-if="sortIndicator('movement_type')">
                                    {{ sortIndicator('movement_type') }}
                                </span>
                                <ArrowUpDown v-else class="size-3 opacity-50" />
                            </button>
                        </th>
                        <th class="min-w-[230px] px-4 py-3">
                            Product &amp; reference
                        </th>
                        <th
                            class="px-3 py-3 whitespace-nowrap"
                            :aria-sort="ariaSortFor('qty_delta')"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 font-semibold hover:text-foreground"
                                @click="toggleSort('qty_delta')"
                            >
                                <span>Stock impact</span>
                                <span v-if="sortIndicator('qty_delta')">
                                    {{ sortIndicator('qty_delta') }}
                                </span>
                                <ArrowUpDown v-else class="size-3 opacity-50" />
                            </button>
                        </th>
                        <th class="min-w-[170px] px-3 py-3">
                            Reason &amp; notes
                        </th>
                        <th class="min-w-[170px] px-3 py-3">
                            Performed by &amp; accountability
                        </th>
                        <th class="px-4 py-3 text-right">Audit</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border/50">
                    <tr
                        v-for="m in movements.data"
                        :key="m.id"
                        class="transition hover:bg-muted/20"
                    >
                        <!-- 1. Date & Time -->
                        <td
                            class="px-4 py-3.5 text-xs whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatDate(m.performed_at) }}
                        </td>

                        <!-- 2. Classification Badge -->
                        <td class="px-3 py-3.5 whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold"
                                :class="movementBadgeClass(m.movement_type)"
                            >
                                {{ movementLabel(m.movement_type) }}
                            </span>
                        </td>

                        <!-- 3. Product & Reference (Generous width, no vertical word squishing) -->
                        <td class="min-w-[230px] px-4 py-3.5">
                            <template v-if="m.product">
                                <div class="font-semibold text-foreground">
                                    {{ m.product.name }}
                                </div>
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-1.5 text-xs"
                                >
                                    <Badge
                                        variant="outline"
                                        class="h-5 px-1.5 font-mono text-[10px]"
                                    >
                                        {{ m.product.sku }}
                                    </Badge>
                                    <span
                                        v-if="m.product.variant"
                                        class="text-muted-foreground"
                                    >
                                        {{ m.product.variant }}
                                    </span>
                                    <span
                                        v-if="m.product.unit"
                                        class="text-muted-foreground"
                                    >
                                        · {{ m.product.unit }}
                                    </span>
                                    <Badge
                                        v-if="m.stock_lot?.reference_no"
                                        variant="secondary"
                                        class="h-5 px-1.5 font-mono text-[10px]"
                                    >
                                        Ref:
                                        {{ m.stock_lot.reference_no }}
                                    </Badge>
                                    <Badge
                                        v-if="m.asset"
                                        variant="secondary"
                                        class="h-5 px-1.5 font-mono text-[10px]"
                                    >
                                        Asset: {{ m.asset.tag_code }}
                                    </Badge>
                                </div>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>

                        <!-- 4. Stock Impact (Delta + Before/After) -->
                        <td class="px-3 py-3.5 whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <span
                                    v-if="
                                        m.qty_delta !== null &&
                                        m.qty_delta !== undefined
                                    "
                                    class="inline-flex items-center rounded-md px-2 py-0.5 font-mono text-xs font-bold"
                                    :class="
                                        m.qty_delta > 0
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                            : m.qty_delta < 0
                                              ? 'bg-rose-500/10 text-rose-700 dark:text-rose-300'
                                              : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{
                                        m.qty_delta > 0
                                            ? `+${m.qty_delta}`
                                            : m.qty_delta
                                    }}
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </div>
                            <div
                                v-if="
                                    m.qty_before !== null &&
                                    m.qty_before !== undefined
                                "
                                class="mt-1 font-mono text-[11px] text-muted-foreground"
                            >
                                {{ m.qty_before }} →
                                <strong class="text-foreground">{{
                                    m.qty_after ?? '—'
                                }}</strong>
                            </div>
                            <div
                                v-if="
                                    m.counted_qty !== null &&
                                    m.counted_qty !== undefined
                                "
                                class="mt-0.5 text-[11px] text-muted-foreground"
                            >
                                Counted: {{ m.counted_qty }} (Var:
                                {{ m.variance_qty ?? 0 }})
                            </div>
                        </td>

                        <!-- 5. Reason & Notes -->
                        <td class="max-w-[240px] px-3 py-3.5">
                            <div
                                v-if="m.reason_code"
                                class="text-xs font-medium text-foreground"
                            >
                                {{ formatReasonCode(m.reason_code) }}
                            </div>
                            <p
                                v-if="m.notes"
                                class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                                :title="m.notes"
                            >
                                {{ m.notes }}
                            </p>
                            <span
                                v-if="!m.reason_code && !m.notes"
                                class="text-xs text-muted-foreground"
                            >
                                —
                            </span>
                        </td>

                        <!-- 6. Performed By & Accountability -->
                        <td class="px-3 py-3.5">
                            <div class="text-xs font-medium text-foreground">
                                {{ m.performed_by?.name ?? 'System' }}
                            </div>
                            <div
                                v-if="m.accountable_position"
                                class="mt-0.5 text-[11px] text-muted-foreground"
                            >
                                {{ m.accountable_position.code }} —
                                {{ m.accountable_position.title }}
                            </div>
                            <div
                                v-if="m.ip_address"
                                class="mt-0.5 font-mono text-[10px] text-muted-foreground/80"
                            >
                                IP: {{ m.ip_address }}
                            </div>
                        </td>

                        <!-- 7. Audit Details Trigger -->
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 px-2.5 text-xs"
                                @click="inspectedMovement = m"
                            >
                                <Eye class="mr-1 size-3.5" />
                                Details
                            </Button>
                        </td>
                    </tr>

                    <tr v-if="movements.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-sm text-muted-foreground"
                        >
                            No stock movements match the current filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div
            v-if="lastMovementPage > 1"
            class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-border/70 bg-card px-4 py-3 shadow-xs"
        >
            <div class="text-xs text-muted-foreground">
                Page
                <strong class="text-foreground">{{
                    currentMovementPage
                }}</strong>
                of
                <strong class="text-foreground">{{ lastMovementPage }}</strong>
                ({{ totalMovementCount }} total movements)
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <template v-for="(link, idx) in movements.links" :key="idx">
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        class="h-8 px-2.5 text-xs"
                    >
                        <Link :href="link.url" preserve-scroll>
                            <span v-html="link.label" />
                        </Link>
                    </Button>
                </template>
            </div>
        </div>

        <!-- Catalog Browser Modal for Adjustment / Cycle Count -->
        <ProductSelectDialog
            v-model:open="isCatalogOpen"
            :title="
                catalogTarget === 'adjust'
                    ? 'Select consumable product for stock adjustment'
                    : 'Select consumable product for cycle count'
            "
            description="Search by product name or SKU to select the consumable item."
            type-filter="consumable"
            :only-in-stock-default="false"
            :close-on-select="true"
            @select="handleCatalogSelect"
        />

        <!-- Movement Audit Details Dialog -->
        <Dialog
            :open="Boolean(inspectedMovement)"
            @update:open="
                if (!$event) {
                    inspectedMovement = null;
                }
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Stock movement #{{ inspectedMovement?.id }}
                    </DialogTitle>
                    <DialogDescription>
                        Complete audit trail and stock reconciliation metadata.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="inspectedMovement" class="grid gap-3 text-xs">
                    <div
                        class="flex items-center justify-between rounded-xl border border-border/60 bg-muted/25 p-3"
                    >
                        <div>
                            <div class="font-semibold text-foreground">
                                {{
                                    inspectedMovement.product?.name ??
                                    'Unassigned Product'
                                }}
                            </div>
                            <div class="mt-0.5 font-mono text-muted-foreground">
                                SKU:
                                {{ inspectedMovement.product?.sku ?? '—' }}
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold"
                            :class="
                                movementBadgeClass(
                                    inspectedMovement.movement_type,
                                )
                            "
                        >
                            {{ movementLabel(inspectedMovement.movement_type) }}
                        </span>
                    </div>

                    <dl
                        class="grid grid-cols-2 gap-3 rounded-xl border border-border/60 p-3.5"
                    >
                        <div>
                            <dt class="text-muted-foreground">Performed at</dt>
                            <dd class="mt-0.5 font-medium text-foreground">
                                {{ formatDate(inspectedMovement.performed_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Quantity delta
                            </dt>
                            <dd class="mt-0.5 font-mono font-bold">
                                {{
                                    inspectedMovement.qty_delta !== null &&
                                    inspectedMovement.qty_delta !== undefined
                                        ? inspectedMovement.qty_delta > 0
                                            ? `+${inspectedMovement.qty_delta}`
                                            : inspectedMovement.qty_delta
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Before → After stock
                            </dt>
                            <dd class="mt-0.5 font-mono">
                                {{ inspectedMovement.qty_before ?? '—' }} →
                                {{ inspectedMovement.qty_after ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Reason code</dt>
                            <dd class="mt-0.5 font-medium">
                                {{
                                    formatReasonCode(
                                        inspectedMovement.reason_code,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Stock lot reference
                            </dt>
                            <dd class="mt-0.5 font-mono">
                                {{
                                    inspectedMovement.stock_lot?.reference_no ??
                                    '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Asset tag</dt>
                            <dd class="mt-0.5 font-mono">
                                {{ inspectedMovement.asset?.tag_code ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Performed by</dt>
                            <dd class="mt-0.5 font-medium">
                                {{
                                    inspectedMovement.performed_by?.name ?? '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">IP address</dt>
                            <dd class="mt-0.5 font-mono">
                                {{ inspectedMovement.ip_address ?? '—' }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-muted-foreground">
                                Accountable position &amp; department
                            </dt>
                            <dd class="mt-0.5 font-medium">
                                <template
                                    v-if="
                                        inspectedMovement.accountable_position
                                    "
                                >
                                    {{
                                        inspectedMovement.accountable_position
                                            .code
                                    }}
                                    —
                                    {{
                                        inspectedMovement.accountable_position
                                            .title
                                    }}
                                    <span
                                        v-if="
                                            inspectedMovement
                                                .accountable_position.department
                                        "
                                        class="text-muted-foreground"
                                    >
                                        ({{
                                            inspectedMovement
                                                .accountable_position
                                                .department
                                        }})
                                    </span>
                                </template>
                                <span v-else>—</span>
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="inspectedMovement.notes"
                        class="rounded-xl border border-border/60 bg-muted/20 p-3"
                    >
                        <div class="font-semibold text-foreground">Notes</div>
                        <p
                            class="mt-1 whitespace-pre-line text-muted-foreground"
                        >
                            {{ inspectedMovement.notes }}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="inspectedMovement = null"
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
