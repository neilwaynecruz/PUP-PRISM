<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Inventory/StockMovementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
import { Label } from '@/components/ui/label';

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
        if (!isComponentActive || !shouldApplyDebouncedVisit('/inventory/movements')) {
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

    return direction.value === 'asc' ? ' \u2191' : ' \u2193';
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
    <Head title="Audit trail" />

    <div
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="movements-index-page"
    >
        <Heading
            variant="small"
            title="Audit trail"
            description="Every inbound and outbound transaction records the actor, accountable position, timestamp, and IP address."
        />

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4">
                    <div class="font-medium">Manual stock adjustment</div>
                    <div class="text-sm text-muted-foreground">
                        Record audited inventory increases or decreases with a structured reason code.
                    </div>
                </div>

                <Form
                    v-bind="StockMovementController.adjust.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-3"
                >
                    <div class="grid gap-1.5">
                        <Label for="adjust-product">Product</Label>
                        <select
                            id="adjust-product"
                            name="product_id"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled selected>Select a product</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }} ({{ product.sku }})
                            </option>
                        </select>
                        <InputError :message="errors.product_id" />
                    </div>

                    <div class="grid gap-1.5 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="adjust-qty">Quantity delta</Label>
                            <Input id="adjust-qty" name="qty_delta" type="number" placeholder="-3 or 5" required />
                            <InputError :message="errors.qty_delta" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="adjust-reason">Reason code</Label>
                            <select
                                id="adjust-reason"
                                name="reason_code"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                                required
                            >
                                <option value="" disabled selected>Select a reason</option>
                                <option v-for="reason in adjustmentReasonCodes" :key="reason.value" :value="reason.value">
                                    {{ reason.label }}
                                </option>
                            </select>
                            <InputError :message="errors.reason_code" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="adjust-notes">Notes</Label>
                        <textarea
                            id="adjust-notes"
                            name="notes"
                            rows="3"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Optional audit note"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <Button type="submit" :disabled="processing" class="w-full sm:w-auto">
                        Save adjustment
                    </Button>
                </Form>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4">
                    <div class="font-medium">Cycle count</div>
                    <div class="text-sm text-muted-foreground">
                        Capture a physical count and let the system write any resulting variance to stock history.
                    </div>
                </div>

                <Form
                    v-bind="StockMovementController.cycleCount.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-3"
                >
                    <div class="grid gap-1.5">
                        <Label for="count-product">Product</Label>
                        <select
                            id="count-product"
                            name="product_id"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled selected>Select a product</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }} ({{ product.sku }})
                            </option>
                        </select>
                        <InputError :message="errors.product_id" />
                    </div>

                    <div class="grid gap-1.5 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="counted-qty">Counted quantity</Label>
                            <Input id="counted-qty" name="counted_qty" type="number" min="0" required />
                            <InputError :message="errors.counted_qty" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="count-reason">Reason code</Label>
                            <select
                                id="count-reason"
                                name="reason_code"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                                required
                            >
                                <option value="" disabled selected>Select a reason</option>
                                <option v-for="reason in cycleCountReasonCodes" :key="reason.value" :value="reason.value">
                                    {{ reason.label }}
                                </option>
                            </select>
                            <InputError :message="errors.reason_code" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="count-notes">Notes</Label>
                        <textarea
                            id="count-notes"
                            name="notes"
                            rows="3"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Optional counting note"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <Button type="submit" :disabled="processing" class="w-full sm:w-auto">
                        Save cycle count
                    </Button>
                </Form>
            </div>
        </div>

        <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
            <div
                class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight"
            >
                <span
                    class="inline-block h-1.5 w-1.5 rounded-full bg-primary/60"
                />
                Filters
            </div>

            <div
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <div class="grid gap-1.5">
                    <label
                        for="filter-search"
                        class="text-[11px] font-medium tracking-wider text-muted-foreground/70 uppercase"
                        >Search</label
                    >
                    <Input
                        id="filter-search"
                        v-model="search"
                        data-testid="movement-search-input"
                        placeholder="SKU, name, tag, ref, user, notes\u2026"
                        class="rounded-lg"
                    />
                </div>

                <div class="grid gap-1.5">
                    <label
                        for="filter-type"
                        class="text-[11px] font-medium tracking-wider text-muted-foreground/70 uppercase"
                        >Movement type</label
                    >
                    <select
                        id="filter-type"
                        v-model="type"
                        data-testid="movement-type-select"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
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

                <div class="grid gap-1.5">
                    <label
                        for="filter-user"
                        class="text-[11px] font-medium tracking-wider text-muted-foreground/70 uppercase"
                        >Performed by</label
                    >
                    <select
                        id="filter-user"
                        v-model="performedBy"
                        data-testid="movement-user-select"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
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

                <div class="grid gap-1.5">
                    <label
                        for="filter-date-from"
                        class="text-[11px] font-medium tracking-wider text-muted-foreground/70 uppercase"
                        >Date from</label
                    >
                    <Input
                        id="filter-date-from"
                        v-model="dateFrom"
                        data-testid="movement-date-from-input"
                        type="date"
                        class="rounded-lg"
                    />
                </div>

                <div class="grid gap-1.5">
                    <label
                        for="filter-date-to"
                        class="text-[11px] font-medium tracking-wider text-muted-foreground/70 uppercase"
                        >Date to</label
                    >
                    <Input
                        id="filter-date-to"
                        v-model="dateTo"
                        data-testid="movement-date-to-input"
                        type="date"
                        class="rounded-lg"
                    />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        as-child
                        class="rounded-lg border-dashed"
                    >
                        <a :href="props.exportUrls.csv">Export CSV</a>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        as-child
                        class="rounded-lg border-dashed"
                    >
                        <a :href="props.exportUrls.pdf">Export PDF</a>
                    </Button>
                </div>

                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    data-testid="movement-reset-filters"
                    class="rounded-lg text-muted-foreground"
                    @click="resetFilters"
                >
                    Reset filters
                </Button>
            </div>
        </div>

        <div class="grid gap-3 md:hidden">
            <div
                v-for="m in movements.data"
                :key="m.id"
                class="rounded-xl border border-border/60 p-4 text-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase',
                            movementTypeColor(m.movement_type),
                        ]"
                    >
                        <span
                            :class="[
                                'h-1 w-1 rounded-full',
                                movementTypeDot(m.movement_type),
                            ]"
                        />
                        {{ movementTypeLabel(m.movement_type) }}
                    </span>
                    <div class="font-mono text-xs text-muted-foreground">
                        IP {{ m.ip_address ?? '\u2014' }}
                    </div>
                </div>

                <div class="mt-3 space-y-2 text-muted-foreground">
                    <div v-if="m.product">
                        {{ m.product.name }} \u00b7 {{ m.product.sku }}
                    </div>
                    <div v-if="m.asset">
                        Asset tag: {{ m.asset.tag_code }} ({{ m.asset.status }})
                    </div>
                    <div v-if="m.stock_lot?.reference_no">
                        Ref: {{ m.stock_lot.reference_no }}
                    </div>
                    <div>Actor: {{ m.performed_by.name }}</div>
                    <div v-if="m.accountable_position">
                        Position: {{ m.accountable_position.title
                        }}{{
                            m.accountable_position.department
                                ? `, ${m.accountable_position.department}`
                                : ''
                        }}
                    </div>
                    <div v-if="m.qty_delta !== null">
                        Quantity: {{ m.qty_delta }}
                    </div>
                    <div>Reason: {{ humanizeReasonCode(m.reason_code) }}</div>
                    <div v-if="m.counted_qty !== null">
                        Counted: {{ m.counted_qty }} (variance {{ m.variance_qty ?? 0 }})
                    </div>
                    <div v-if="m.notes">Notes: {{ m.notes }}</div>
                    <div class="text-xs text-muted-foreground/70">
                        {{ formatDateTime(m.performed_at) }}
                    </div>
                </div>
            </div>

            <div
                v-if="movements.data.length === 0"
                class="rounded-xl border border-dashed border-border/60 p-8 text-center text-sm text-muted-foreground"
            >
                No audit entries match the current filters.
            </div>
        </div>

        <div
            class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm md:block"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3">
                        <th
                            class="cursor-pointer whitespace-nowrap select-none"
                            @click="toggleSort('performed_at')"
                        >
                            When{{ sortIndicator('performed_at') }}
                        </th>
                        <th
                            class="cursor-pointer select-none"
                            @click="toggleSort('movement_type')"
                        >
                            Type{{ sortIndicator('movement_type') }}
                        </th>
                        <th>Product</th>
                        <th>Asset</th>
                        <th class="text-right">Qty</th>
                        <th>Reason</th>
                        <th
                            class="cursor-pointer select-none"
                            @click="toggleSort('performed_by')"
                        >
                            Actor{{ sortIndicator('performed_by') }}
                        </th>
                        <th>Position</th>
                        <th>IP</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="movements.data.length === 0">
                        <td
                            class="px-4 py-10 text-center text-muted-foreground"
                            colspan="10"
                        >
                            No audit entries match the current filters.
                        </td>
                    </tr>

                    <tr
                        v-for="m in movements.data"
                        :key="m.id"
                        class="[&>td]:px-4 [&>td]:py-3"
                    >
                        <td
                            class="text-xs whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatDateTime(m.performed_at) }}
                        </td>
                        <td>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase',
                                    movementTypeColor(m.movement_type),
                                ]"
                            >
                                <span
                                    :class="[
                                        'h-1 w-1 rounded-full',
                                        movementTypeDot(m.movement_type),
                                    ]"
                                />
                                {{ movementTypeLabel(m.movement_type) }}
                            </span>
                        </td>
                        <td>
                            <div v-if="m.product">
                                <div class="font-medium">
                                    {{ m.product.name }}
                                </div>
                                <div
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ m.product.sku }}
                                </div>
                            </div>
                            <div v-else class="text-muted-foreground">—</div>
                        </td>
                        <td>
                            <div v-if="m.asset">
                                <div class="font-medium">
                                    {{ m.asset.tag_code }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ m.asset.status }}
                                </div>
                            </div>
                            <div v-else class="text-muted-foreground">—</div>
                        </td>
                        <td class="text-right font-mono">
                            <span v-if="m.qty_delta !== null">{{
                                m.qty_delta
                            }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td>
                            <div class="font-medium">
                                {{ humanizeReasonCode(m.reason_code) }}
                            </div>
                            <div v-if="m.counted_qty !== null" class="text-xs text-muted-foreground">
                                Counted {{ m.counted_qty }}, variance {{ m.variance_qty ?? 0 }}
                            </div>
                        </td>
                        <td>
                            <div class="font-medium">
                                {{ m.performed_by.name }}
                            </div>
                        </td>
                        <td>
                            <div
                                v-if="m.accountable_position"
                                class="font-medium"
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
                            <div v-else class="text-muted-foreground">—</div>
                        </td>
                        <td class="font-mono text-xs text-muted-foreground">
                            {{ m.ip_address ?? '—' }}
                        </td>
                        <td class="max-w-sm truncate text-muted-foreground">
                            {{ m.notes ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="movements.links.length"
            class="flex flex-wrap items-center justify-center gap-2"
        >
            <Button
                v-for="(link, i) in movements.links"
                :key="i"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
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
