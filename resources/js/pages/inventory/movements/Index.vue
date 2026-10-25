<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Activity,
    Calendar,
    ClipboardCheck,
    FileSpreadsheet,
    FileText,
    RotateCcw,
    Search,
    SlidersHorizontal,
    User,
    Wrench,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Inventory/StockMovementController';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';

type PaginationLink = { url: string | null; label: string; active: boolean };

type Movement = {
    id: number;
    movement_type: string;
    reason_code: string | null;
    qty_delta: number | null;
    qty_before: number | null;
    qty_after: number | null;
    counted_qty: number | null;
    variance_qty: number | null;
    performed_at: string;
    ip_address: string | null;
    notes: string | null;
    product: { id: number; sku: string; name: string } | null;
    stock_lot: { id: number; reference_no: string | null } | null;
    asset: { id: number; tag_code: string; status: string } | null;
    performed_by: { id: number; name: string; email: string };
    accountable_position: {
        title: string;
        code: string;
        department: string | null;
    } | null;
};

type UserOption = { id: number; name: string; email: string };
type ProductOption = { id: number; sku: string; name: string };
type ReasonCodeOption = { value: string; label: string };

type Paginated<T> = { data: T[]; links: PaginationLink[] };

const props = defineProps<{
    filters: {
        type: string;
        search: string;
        date_from: string | null;
        date_to: string | null;
        performed_by: string | null;
        sort: string;
        direction: string;
    };
    movements: Paginated<Movement>;
    users: UserOption[];
    products: ProductOption[];
    adjustmentReasonCodes: ReasonCodeOption[];
    cycleCountReasonCodes: ReasonCodeOption[];
    exportUrls: { csv: string; pdf: string };
}>();

defineOptions({
    name: 'StockMovementIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Movements', href: StockMovementController.index() },
        ],
    },
});

const type = ref(props.filters.type ?? '');
const search = ref(props.filters.search ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const performedBy = ref(props.filters.performed_by ?? '');
const sort = ref(props.filters.sort ?? 'performed_at');
const direction = ref(props.filters.direction ?? 'desc');

const hasActiveFilters = computed(
    () =>
        type.value !== '' ||
        search.value !== '' ||
        dateFrom.value !== '' ||
        dateTo.value !== '' ||
        performedBy.value !== '',
);

const query = computed(() => ({
    type: type.value || undefined,
    search: search.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    performed_by: performedBy.value || undefined,
    sort: sort.value,
    direction: direction.value,
}));

let timer: number | undefined;
let isComponentActive = true;

watch([type, search, dateFrom, dateTo, performedBy], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        if (
            !isComponentActive ||
            !shouldApplyDebouncedVisit('/inventory/movements')
        ) {
            return;
        }

        router.get(StockMovementController.index().url, query.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
});

onBeforeUnmount(() => {
    isComponentActive = false;
    window.clearTimeout(timer);
});

function toggleSort(column: string): void {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'desc';
    }

    router.get(StockMovementController.index().url, query.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters(): void {
    type.value = '';
    search.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    performedBy.value = '';
}

function sortIndicator(col: string): string {
    if (sort.value !== col) {
        return '';
    }

    return direction.value === 'asc' ? ' ↑' : ' ↓';
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function movementTypeLabel(mt: string): string {
    const labels: Record<string, string> = {
        receive: 'Received',
        issue: 'Issued',
        transfer: 'Transferred',
        condemn: 'Condemned',
        return: 'Returned',
        adjustment: 'Adjustment',
        cycle_count: 'Cycle count',
    };

    return labels[mt] ?? mt;
}

function movementTypeColor(mt: string): string {
    switch (mt) {
        case 'receive':
            return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 'issue':
            return 'border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400';
        case 'transfer':
            return 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400';
        case 'condemn':
            return 'border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-400';
        case 'return':
            return 'border-violet-500/20 bg-violet-500/10 text-violet-600 dark:text-violet-400';
        case 'adjustment':
            return 'border-orange-500/20 bg-orange-500/10 text-orange-600 dark:text-orange-400';
        case 'cycle_count':
            return 'border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400';
        default:
            return 'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400';
    }
}

function movementTypeDot(mt: string): string {
    switch (mt) {
        case 'receive':
            return 'bg-emerald-500';
        case 'issue':
            return 'bg-sky-500';
        case 'transfer':
            return 'bg-amber-500';
        case 'condemn':
            return 'bg-rose-500';
        case 'return':
            return 'bg-violet-500';
        case 'adjustment':
            return 'bg-orange-500';
        case 'cycle_count':
            return 'bg-indigo-500';
        default:
            return 'bg-slate-400';
    }
}

function humanizeReasonCode(value: string | null): string {
    if (!value) {
        return '—';
    }

    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
</script>

<template>
    <Head title="Stock Movements" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="movements-index-page"
    >
        <!-- Header & Export Actions -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <Heading
                    variant="small"
                    title="Stock movements"
                    description="Review historical inventory events: receipts, issuances, transfers, manual adjustments, and cycle count reconciliations."
                />
            </div>

            <!-- Export Buttons -->
            <div
                class="flex items-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 p-0.5"
            >
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                >
                    <a
                        :href="props.exportUrls.csv"
                        title="Download Stock Movements CSV"
                    >
                        <FileSpreadsheet
                            class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                        />
                        <span class="hidden sm:inline">CSV</span>
                    </a>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                >
                    <a
                        :href="props.exportUrls.pdf"
                        title="Download Stock Movements PDF"
                    >
                        <FileText
                            class="h-3.5 w-3.5 text-rose-600 dark:text-rose-400"
                        />
                        <span class="hidden sm:inline">PDF</span>
                    </a>
                </Button>
            </div>
        </div>

        <!-- 2 Column Operations: Manual Adjustment & Cycle Count Reconciliation -->
        <div class="grid gap-6 xl:grid-cols-2">
            <!-- Manual stock adjustment -->
            <div
                class="rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4">
                    <div
                        class="flex items-center gap-2 text-xs font-bold tracking-tight text-foreground uppercase"
                    >
                        <Wrench class="h-4 w-4 text-primary" />
                        Manual stock adjustment
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Record audited inventory increases or decreases with a
                        structured reason code.
                    </div>
                </div>

                <Form
                    v-bind="StockMovementController.adjust.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-3"
                >
                    <div class="grid gap-1.5">
                        <Label
                            for="adjust-product"
                            class="text-xs font-medium text-muted-foreground"
                            >Product</Label
                        >
                        <select
                            id="adjust-product"
                            name="product_id"
                            class="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            required
                        >
                            <option value="" disabled selected>
                                Select a product
                            </option>
                            <option
                                v-for="product in products"
                                :key="product.id"
                                :value="product.id"
                            >
                                {{ product.name }} ({{ product.sku }})
                            </option>
                        </select>
                        <InputError :message="errors.product_id" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="adjust-qty"
                                class="text-xs font-medium text-muted-foreground"
                                >Quantity change</Label
                            >
                            <Input
                                id="adjust-qty"
                                name="qty_delta"
                                type="number"
                                inputmode="numeric"
                                placeholder="Example: -3 or 5"
                                required
                                class="h-10 rounded-lg text-sm"
                            />
                            <p class="text-[0.7rem] text-muted-foreground">
                                Use negative to decrease or positive to
                                increase.
                            </p>
                            <InputError :message="errors.qty_delta" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="adjust-reason"
                                class="text-xs font-medium text-muted-foreground"
                                >Reason code</Label
                            >
                            <select
                                id="adjust-reason"
                                name="reason_code"
                                class="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                                required
                            >
                                <option value="" disabled selected>
                                    Select a reason
                                </option>
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
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="adjust-notes"
                            class="text-xs font-medium text-muted-foreground"
                            >Remarks / Notes</Label
                        >
                        <textarea
                            id="adjust-notes"
                            name="notes"
                            rows="2"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            placeholder="Explain why this adjustment is needed…"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing"
                        size="sm"
                        class="h-9 w-full rounded-lg text-xs sm:w-auto"
                    >
                        {{
                            processing
                                ? 'Saving Adjustment…'
                                : 'Save Adjustment'
                        }}
                    </Button>
                </Form>
            </div>

            <!-- Cycle count -->
            <div
                class="rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4">
                    <div
                        class="flex items-center gap-2 text-xs font-bold tracking-tight text-foreground uppercase"
                    >
                        <ClipboardCheck class="h-4 w-4 text-primary" />
                        Cycle count reconciliation
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Capture physical counts and write variances
                        automatically to stock history.
                    </div>
                </div>

                <Form
                    v-bind="StockMovementController.cycleCount.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-3"
                >
                    <div class="grid gap-1.5">
                        <Label
                            for="count-product"
                            class="text-xs font-medium text-muted-foreground"
                            >Product</Label
                        >
                        <select
                            id="count-product"
                            name="product_id"
                            class="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            required
                        >
                            <option value="" disabled selected>
                                Select a product
                            </option>
                            <option
                                v-for="product in products"
                                :key="product.id"
                                :value="product.id"
                            >
                                {{ product.name }} ({{ product.sku }})
                            </option>
                        </select>
                        <InputError :message="errors.product_id" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="counted-qty"
                                class="text-xs font-medium text-muted-foreground"
                                >Counted quantity</Label
                            >
                            <Input
                                id="counted-qty"
                                name="counted_qty"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                placeholder="Example: 24"
                                required
                                class="h-10 rounded-lg text-sm"
                            />
                            <p class="text-[0.7rem] text-muted-foreground">
                                Actual units counted in stock.
                            </p>
                            <InputError :message="errors.counted_qty" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="count-reason"
                                class="text-xs font-medium text-muted-foreground"
                                >Reason code</Label
                            >
                            <select
                                id="count-reason"
                                name="reason_code"
                                class="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                                required
                            >
                                <option value="" disabled selected>
                                    Select a reason
                                </option>
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
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="count-notes"
                            class="text-xs font-medium text-muted-foreground"
                            >Remarks / Notes</Label
                        >
                        <textarea
                            id="count-notes"
                            name="notes"
                            rows="2"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            placeholder="Optional notes regarding the count…"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing"
                        size="sm"
                        class="h-9 w-full rounded-lg text-xs sm:w-auto"
                    >
                        {{
                            processing
                                ? 'Saving Cycle Count…'
                                : 'Save Cycle Count'
                        }}
                    </Button>
                </Form>
            </div>
        </div>

        <!-- Filter Card -->
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
                    data-testid="movement-reset-filters"
                    class="h-7 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click="resetFilters"
                >
                    <RotateCcw class="h-3 w-3" />
                    Clear filters
                </Button>
            </div>

            <div
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <div class="grid gap-1">
                    <label
                        for="filter-search"
                        class="text-xs font-medium text-muted-foreground"
                        >Search</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <Input
                            id="filter-search"
                            v-model="search"
                            data-testid="movement-search-input"
                            placeholder="SKU, product, tag, user…"
                            class="h-10 rounded-lg pl-9 text-sm"
                        />
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="filter-type"
                        class="text-xs font-medium text-muted-foreground"
                        >Movement type</label
                    >
                    <div class="relative">
                        <Activity
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <select
                            id="filter-type"
                            v-model="type"
                            data-testid="movement-type-select"
                            class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        >
                            <option value="">All types</option>
                            <option value="receive">Received</option>
                            <option value="issue">Issued</option>
                            <option value="transfer">Transferred</option>
                            <option value="condemn">Condemned</option>
                            <option value="return">Returned</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="cycle_count">Cycle count</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="filter-user"
                        class="text-xs font-medium text-muted-foreground"
                        >Performed by</label
                    >
                    <div class="relative">
                        <User
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <select
                            id="filter-user"
                            v-model="performedBy"
                            data-testid="movement-user-select"
                            class="h-10 w-full rounded-lg border border-input bg-background pr-8 pl-9 text-sm text-foreground transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                        >
                            <option value="">All users</option>
                            <option
                                v-for="u in users"
                                :key="u.id"
                                :value="String(u.id)"
                            >
                                {{ u.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="filter-date-from"
                        class="text-xs font-medium text-muted-foreground"
                        >Date from</label
                    >
                    <div class="relative">
                        <Calendar
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <Input
                            id="filter-date-from"
                            v-model="dateFrom"
                            data-testid="movement-date-from-input"
                            type="date"
                            class="h-10 rounded-lg pl-9 text-sm"
                        />
                    </div>
                </div>

                <div class="grid gap-1">
                    <label
                        for="filter-date-to"
                        class="text-xs font-medium text-muted-foreground"
                        >Date to</label
                    >
                    <div class="relative">
                        <Calendar
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                        <Input
                            id="filter-date-to"
                            v-model="dateTo"
                            data-testid="movement-date-to-input"
                            type="date"
                            class="h-10 rounded-lg pl-9 text-sm"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Movements List -->
        <div class="grid gap-3 md:hidden">
            <div
                v-if="movements.data.length === 0"
                class="rounded-xl border border-dashed border-border/70 p-8 text-center"
            >
                <p class="text-sm font-semibold text-foreground">
                    No stock movements found
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Try changing your search keywords or filter criteria.
                </p>
            </div>
            <div
                v-for="m in movements.data"
                :key="m.id"
                class="rounded-xl border border-border/70 bg-card p-4 text-sm shadow-xs"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wider uppercase',
                            movementTypeColor(m.movement_type),
                        ]"
                    >
                        <span
                            :class="[
                                'h-1.5 w-1.5 rounded-full',
                                movementTypeDot(m.movement_type),
                            ]"
                        />
                        {{ movementTypeLabel(m.movement_type) }}
                    </span>
                    <div class="font-mono text-xs text-muted-foreground">
                        {{ formatDateTime(m.performed_at) }}
                    </div>
                </div>

                <div class="mt-3">
                    <div v-if="m.product">
                        <div class="font-semibold text-foreground">
                            {{ m.product.name }}
                        </div>
                        <div class="mt-0.5">
                            <AdminCodeBadge :value="m.product.sku" />
                        </div>
                    </div>
                    <div v-if="m.asset" class="mt-2 flex items-center gap-2">
                        <AdminCodeBadge :value="m.asset.tag_code" />
                        <span class="text-xs text-muted-foreground">{{
                            m.asset.status
                        }}</span>
                    </div>
                </div>

                <dl
                    class="mt-3 grid grid-cols-2 gap-2 border-t border-border/40 pt-3 text-xs"
                >
                    <div>
                        <dt class="text-muted-foreground">Qty change</dt>
                        <dd
                            class="mt-0.5 font-mono font-bold"
                            :class="
                                (m.qty_delta ?? 0) > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : (m.qty_delta ?? 0) < 0
                                      ? 'text-rose-600 dark:text-rose-400'
                                      : 'text-foreground'
                            "
                        >
                            {{
                                m.qty_delta !== null
                                    ? m.qty_delta > 0
                                        ? `+${m.qty_delta}`
                                        : m.qty_delta
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Reason</dt>
                        <dd class="mt-0.5 font-medium text-foreground">
                            {{ humanizeReasonCode(m.reason_code) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">User</dt>
                        <dd class="mt-0.5 text-foreground">
                            {{ m.performed_by.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Position</dt>
                        <dd class="mt-0.5 truncate text-foreground">
                            {{ m.accountable_position?.title ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Desktop Movements Table -->
        <div
            class="hidden overflow-hidden rounded-xl border border-border/70 bg-card shadow-xs md:block"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">
                        Stock movements audit trail
                    </caption>
                    <thead
                        class="border-b border-border/60 bg-muted/40 text-left"
                    >
                        <tr
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase [&>th]:px-4 [&>th]:py-3"
                        >
                            <th
                                class="cursor-pointer select-none hover:text-foreground"
                                scope="col"
                                @click="toggleSort('performed_at')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Date {{ sortIndicator('performed_at') }}
                                </span>
                            </th>
                            <th scope="col">Type</th>
                            <th scope="col">Product</th>
                            <th scope="col">Asset</th>
                            <th
                                class="cursor-pointer text-right select-none hover:text-foreground"
                                scope="col"
                                @click="toggleSort('qty_delta')"
                            >
                                <span
                                    class="inline-flex items-center justify-end gap-1"
                                >
                                    Qty delta {{ sortIndicator('qty_delta') }}
                                </span>
                            </th>
                            <th scope="col">Reason</th>
                            <th scope="col">User</th>
                            <th scope="col">Accountability</th>
                            <th scope="col">IP</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-if="movements.data.length === 0">
                            <td
                                class="px-4 py-12 text-center text-sm text-muted-foreground"
                                colspan="10"
                            >
                                No stock movements match the current filters.
                            </td>
                        </tr>

                        <tr
                            v-for="m in movements.data"
                            :key="m.id"
                            class="transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3.5"
                        >
                            <td
                                class="text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(m.performed_at) }}
                            </td>
                            <td>
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wider uppercase',
                                        movementTypeColor(m.movement_type),
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            movementTypeDot(m.movement_type),
                                        ]"
                                    />
                                    {{ movementTypeLabel(m.movement_type) }}
                                </span>
                            </td>
                            <td>
                                <div v-if="m.product">
                                    <div class="font-medium text-foreground">
                                        {{ m.product.name }}
                                    </div>
                                    <div class="mt-0.5">
                                        <AdminCodeBadge
                                            :value="m.product.sku"
                                        />
                                    </div>
                                </div>
                                <span v-else class="text-muted-foreground/60"
                                    >—</span
                                >
                            </td>
                            <td>
                                <div v-if="m.asset" class="space-y-0.5">
                                    <AdminCodeBadge :value="m.asset.tag_code" />
                                    <div
                                        class="text-[0.7rem] text-muted-foreground"
                                    >
                                        {{ m.asset.status }}
                                    </div>
                                </div>
                                <span v-else class="text-muted-foreground/60"
                                    >—</span
                                >
                            </td>
                            <td class="text-right font-mono font-bold">
                                <span
                                    v-if="m.qty_delta !== null"
                                    :class="
                                        m.qty_delta > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : m.qty_delta < 0
                                              ? 'text-rose-600 dark:text-rose-400'
                                              : 'text-foreground'
                                    "
                                >
                                    {{
                                        m.qty_delta > 0
                                            ? `+${m.qty_delta}`
                                            : m.qty_delta
                                    }}
                                </span>
                                <span v-else class="text-muted-foreground/60"
                                    >—</span
                                >
                            </td>
                            <td>
                                <div class="font-medium text-foreground">
                                    {{ humanizeReasonCode(m.reason_code) }}
                                </div>
                                <div
                                    v-if="m.counted_qty !== null"
                                    class="text-xs text-muted-foreground"
                                >
                                    Counted: {{ m.counted_qty }}, var:
                                    {{ m.variance_qty ?? 0 }}
                                </div>
                            </td>
                            <td>
                                <div class="font-medium text-foreground">
                                    {{ m.performed_by.name }}
                                </div>
                            </td>
                            <td>
                                <div
                                    v-if="m.accountable_position"
                                    class="font-medium text-foreground"
                                >
                                    {{ m.accountable_position.title }}
                                </div>
                                <div
                                    v-if="m.accountable_position"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        m.accountable_position.department ??
                                        m.accountable_position.code
                                    }}
                                </div>
                                <span v-else class="text-muted-foreground/60"
                                    >—</span
                                >
                            </td>
                            <td class="font-mono text-xs text-muted-foreground">
                                {{ m.ip_address ?? '—' }}
                            </td>
                            <td
                                class="max-w-xs truncate text-xs text-muted-foreground"
                            >
                                {{ m.notes ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination :links="movements.links" />
    </div>
</template>
