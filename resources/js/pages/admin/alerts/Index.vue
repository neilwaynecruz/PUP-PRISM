<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BellRing,
    CheckCircle2,
    ChevronDown,
    Clock3,
    ExternalLink,
    Eye,
    UserCheck,
    UserPlus,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    acknowledge,
    assign,
    index as alertsIndex,
    resolve,
} from '@/routes/admin/alerts';
import { show as forecastShow } from '@/routes/inventory/forecasting';
import { show as productShow } from '@/routes/inventory/products';

type Severity = 'critical' | 'high' | 'medium';

type AlertRow = {
    id: number;
    type: string;
    message: string;
    detected_at: string | null;
    acknowledged_at: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
    is_active: boolean;
    severity: Severity;
    product: {
        id: number;
        sku: string;
        name: string;
        on_hand_qty: number | null;
        reorder_threshold: number | null;
    } | null;
    stock_lot: {
        id: number;
        reference_no: string | null;
        expires_at: string | null;
        qty_remaining: number;
    } | null;
    acknowledged_by: { id: number; name: string } | null;
    assigned_to: { id: number; name: string } | null;
    resolved_by: { id: number; name: string } | null;
};

type Operator = { id: number; name: string; email: string };
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: {
        search: string;
        type: string;
        status: string;
        assigned_to: number;
    };
    alerts: {
        data: AlertRow[];
        links: PaginationLink[];
        total: number;
    };
    types: string[];
    operators: Operator[];
    summary: {
        active: number;
        unacknowledged: number;
        acknowledged: number;
        assigned_to_me: number;
        resolved_this_week: number;
        active_by_type: Record<string, number>;
    };
    currentUserId: number | null;
}>();

defineOptions({
    name: 'AdminAlertsIndexPage',
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: alertsIndex() },
            { title: 'Alerts', href: alertsIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status || 'active');
const assignedTo = ref(
    props.filters.assigned_to !== 0 ? String(props.filters.assigned_to) : '',
);

const resolveDialogOpen = ref(false);
const selectedAlert = ref<AlertRow | null>(null);
const resolveForm = useForm({ resolution_notes: '' });
const expandedAlertIds = ref<Set<number>>(new Set());
const processingAlertId = ref<number | null>(null);

let refreshTimer: number | undefined;

watch([search, type, status, assignedTo], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            alertsIndex().url,
            {
                search: search.value || undefined,
                type: type.value || undefined,
                status: status.value || undefined,
                assigned_to: assignedTo.value || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 250);
});

onBeforeUnmount(() => {
    window.clearTimeout(refreshTimer);
});

const statusTabs = computed(() => [
    {
        value: 'unacknowledged',
        label: 'Needs review',
        count: props.summary.unacknowledged,
    },
    {
        value: 'acknowledged',
        label: 'In progress',
        count: props.summary.acknowledged,
    },
    { value: 'active', label: 'All open', count: props.summary.active },
    { value: 'resolved', label: 'Resolved', count: null },
    { value: 'all', label: 'Everything', count: null },
]);

const hasFilters = computed(() =>
    Boolean(search.value || type.value || assignedTo.value),
);

function clearFilters(): void {
    search.value = '';
    type.value = '';
    assignedTo.value = '';
}

const typeLabels: Record<string, string> = {
    low_stock: 'Low stock',
    expiring: 'Expiring lot',
    forecast_stockout: 'Forecast stock-out',
};

function typeLabel(value: string): string {
    return (
        typeLabels[value] ??
        value.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
    );
}

const severityMeta: Record<
    Severity,
    { label: string; classes: string; stripe: string }
> = {
    critical: {
        label: 'Critical',
        classes:
            'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        stripe: 'before:bg-rose-500',
    },
    high: {
        label: 'High',
        classes:
            'border-amber-500/35 bg-amber-500/10 text-amber-800 dark:text-amber-300',
        stripe: 'before:bg-amber-500',
    },
    medium: {
        label: 'Medium',
        classes:
            'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        stripe: 'before:bg-sky-500',
    },
};

function stateOf(alert: AlertRow): 'open' | 'progress' | 'resolved' {
    if (!alert.is_active) {
        return 'resolved';
    }

    return alert.acknowledged_at ? 'progress' : 'open';
}

const stateMeta = {
    open: {
        label: 'Needs review',
        classes:
            'border-amber-500/35 bg-amber-500/10 text-amber-800 dark:text-amber-300',
    },
    progress: {
        label: 'In progress',
        classes:
            'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    },
    resolved: {
        label: 'Resolved',
        classes:
            'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatAge(value: string | null): string {
    if (!value) {
        return '';
    }

    const minutes = Math.max(
        0,
        Math.round((Date.now() - new Date(value).getTime()) / 60000),
    );

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 48) {
        return `${hours} hr ago`;
    }

    return `${Math.round(hours / 24)} days ago`;
}

function toggleDetails(alertId: number): void {
    const next = new Set(expandedAlertIds.value);

    if (next.has(alertId)) {
        next.delete(alertId);
    } else {
        next.add(alertId);
    }

    expandedAlertIds.value = next;
}

function acknowledgeAlert(alert: AlertRow): void {
    router.patch(
        acknowledge(alert.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (processingAlertId.value = alert.id),
            onFinish: () => (processingAlertId.value = null),
        },
    );
}

function assignAlert(alert: AlertRow, operatorId: number): void {
    router.patch(
        assign(alert.id).url,
        { assigned_to: operatorId },
        {
            preserveScroll: true,
            onStart: () => (processingAlertId.value = alert.id),
            onFinish: () => (processingAlertId.value = null),
        },
    );
}

function openResolveDialog(alert: AlertRow): void {
    selectedAlert.value = alert;
    resolveForm.reset();
    resolveForm.clearErrors();
    resolveDialogOpen.value = true;
}

function submitResolve(): void {
    if (selectedAlert.value === null) {
        return;
    }

    resolveForm.patch(resolve(selectedAlert.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            resolveDialogOpen.value = false;
            selectedAlert.value = null;
        },
    });
}
</script>

<template>
    <Head title="Alerts" />

    <div
        class="mx-auto flex w-full max-w-400 flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="alerts-page"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <Heading
                variant="small"
                title="Inventory alerts"
                description="Review low-stock, expiring-lot, and forecast alerts. Acknowledge an alert, assign an owner, then resolve it once the issue is handled."
            />
            <div
                class="inline-flex w-fit items-center gap-2 rounded-full border border-border/60 bg-card px-3 py-1.5 text-xs text-muted-foreground shadow-xs"
            >
                <BellRing class="h-3.5 w-3.5 text-primary" />
                {{ summary.resolved_this_week }} resolved in the last 7 days
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
                type="button"
                class="rounded-xl border border-amber-500/30 bg-card p-4 text-left shadow-sm transition hover:border-amber-500/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="{
                    'ring-2 ring-amber-500/40': status === 'unacknowledged',
                }"
                @click="status = 'unacknowledged'"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-300"
                        >Needs review</span
                    >
                    <AlertTriangle class="h-4 w-4 text-amber-500" />
                </div>
                <div class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ summary.unacknowledged }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Not yet acknowledged by anyone.
                </p>
            </button>
            <button
                type="button"
                class="rounded-xl border border-sky-500/25 bg-card p-4 text-left shadow-sm transition hover:border-sky-500/45 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="{ 'ring-2 ring-sky-500/40': status === 'acknowledged' }"
                @click="status = 'acknowledged'"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wide text-sky-700 uppercase dark:text-sky-300"
                        >In progress</span
                    >
                    <Clock3 class="h-4 w-4 text-sky-500" />
                </div>
                <div class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ summary.acknowledged }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Acknowledged and waiting to be resolved.
                </p>
            </button>
            <button
                type="button"
                class="rounded-xl border border-primary/25 bg-card p-4 text-left shadow-sm transition hover:border-primary/45 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="{
                    'ring-2 ring-primary/40':
                        currentUserId !== null &&
                        assignedTo === String(currentUserId),
                }"
                @click="
                    status = 'active';
                    assignedTo = currentUserId ? String(currentUserId) : '';
                "
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wide text-primary uppercase"
                        >Assigned to me</span
                    >
                    <UserCheck class="h-4 w-4 text-primary" />
                </div>
                <div class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ summary.assigned_to_me }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Open alerts you are responsible for.
                </p>
            </button>
            <div
                class="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
            >
                <div
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Open by type
                </div>
                <ul
                    v-if="Object.keys(summary.active_by_type).length"
                    class="mt-2 space-y-1.5 text-sm"
                >
                    <li
                        v-for="(count, alertType) in summary.active_by_type"
                        :key="alertType"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-md px-1.5 py-0.5 text-left hover:bg-muted/60"
                            @click="
                                type = String(alertType);
                                status = 'active';
                            "
                        >
                            <span>{{ typeLabel(String(alertType)) }}</span>
                            <span class="font-semibold tabular-nums">{{
                                count
                            }}</span>
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-2 text-sm text-muted-foreground">
                    No open alerts.
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-border/60 bg-card shadow-sm">
            <div
                class="flex gap-1 overflow-x-auto border-b border-border/60 px-3 pt-3"
                role="tablist"
                aria-label="Alert status"
            >
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="status === tab.value"
                    class="-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        status === tab.value
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="status = tab.value"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count !== null"
                        class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] tabular-nums"
                        >{{ tab.count }}</span
                    >
                </button>
            </div>

            <div
                class="grid gap-3 p-3 sm:grid-cols-2 sm:p-4 lg:grid-cols-[2fr_1fr_1fr_auto]"
            >
                <Input
                    v-model="search"
                    type="search"
                    aria-label="Search alerts"
                    placeholder="Search by message, product name, or SKU"
                    class="h-10 rounded-lg sm:col-span-2 lg:col-span-1"
                />
                <select
                    v-model="type"
                    aria-label="Alert type"
                    class="h-10 rounded-lg border border-input bg-background px-3 text-sm shadow-xs focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none"
                >
                    <option value="">All types</option>
                    <option
                        v-for="alertType in types"
                        :key="alertType"
                        :value="alertType"
                    >
                        {{ typeLabel(alertType) }}
                    </option>
                </select>
                <select
                    v-model="assignedTo"
                    aria-label="Assigned to"
                    class="h-10 rounded-lg border border-input bg-background px-3 text-sm shadow-xs focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none"
                >
                    <option value="">Anyone</option>
                    <option value="-1">Unassigned</option>
                    <option
                        v-for="operator in operators"
                        :key="operator.id"
                        :value="String(operator.id)"
                    >
                        {{ operator.name
                        }}{{ operator.id === currentUserId ? ' (you)' : '' }}
                    </option>
                </select>
                <Button
                    v-if="hasFilters"
                    variant="ghost"
                    class="h-10"
                    @click="clearFilters"
                >
                    Clear
                </Button>
            </div>

            <div
                v-if="alerts.data.length === 0"
                class="flex flex-col items-center gap-2 border-t border-border/60 px-4 py-14 text-center"
            >
                <CheckCircle2 class="h-8 w-8 text-emerald-500/70" />
                <p class="text-sm font-medium">
                    {{
                        status === 'unacknowledged' && !hasFilters
                            ? 'Nothing needs review right now.'
                            : 'No alerts match these filters.'
                    }}
                </p>
                <p class="text-xs text-muted-foreground">
                    New alerts are generated daily from stock levels, lot expiry
                    dates, and demand forecasts.
                </p>
            </div>

            <ul
                v-else
                class="divide-y divide-border/60 border-t border-border/60"
            >
                <li
                    v-for="alert in alerts.data"
                    :key="alert.id"
                    class="relative px-4 py-4 pl-5 before:absolute before:inset-y-0 before:left-0 before:w-1"
                    :class="[
                        alert.is_active
                            ? severityMeta[alert.severity].stripe
                            : 'opacity-80 before:bg-emerald-500/60',
                    ]"
                    data-testid="alert-row"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    v-if="alert.is_active"
                                    class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="
                                        severityMeta[alert.severity].classes
                                    "
                                >
                                    {{ severityMeta[alert.severity].label }}
                                </span>
                                <span
                                    class="inline-flex rounded-full border border-border/70 bg-muted/50 px-2 py-0.5 text-[11px] font-semibold text-foreground/80"
                                >
                                    {{ typeLabel(alert.type) }}
                                </span>
                                <span
                                    class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="stateMeta[stateOf(alert)].classes"
                                >
                                    {{ stateMeta[stateOf(alert)].label }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    · {{ formatAge(alert.detected_at) }}
                                </span>
                            </div>

                            <p class="text-sm text-foreground">
                                {{ alert.message }}
                            </p>

                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span v-if="alert.product">
                                    <Link
                                        :href="productShow(alert.product.id)"
                                        class="font-medium text-foreground/90 underline-offset-2 hover:text-primary hover:underline"
                                    >
                                        {{ alert.product.name }}
                                    </Link>
                                    <span class="ml-1 font-mono"
                                        >({{ alert.product.sku }})</span
                                    >
                                </span>
                                <span
                                    v-if="
                                        alert.product?.on_hand_qty !== null &&
                                        alert.product?.on_hand_qty !== undefined
                                    "
                                >
                                    On hand:
                                    <span
                                        class="font-semibold tabular-nums"
                                        :class="
                                            alert.product.on_hand_qty <= 0
                                                ? 'text-rose-600 dark:text-rose-400'
                                                : 'text-foreground/90'
                                        "
                                        >{{ alert.product.on_hand_qty }}</span
                                    >
                                    <template
                                        v-if="
                                            alert.product.reorder_threshold !==
                                            null
                                        "
                                    >
                                        / reorder at
                                        {{
                                            alert.product.reorder_threshold
                                        }}</template
                                    >
                                </span>
                                <span v-if="alert.stock_lot?.expires_at">
                                    Lot
                                    {{
                                        alert.stock_lot.reference_no ??
                                        `#${alert.stock_lot.id}`
                                    }}
                                    expires
                                    <span
                                        class="font-semibold text-foreground/90"
                                        >{{ alert.stock_lot.expires_at }}</span
                                    >
                                    ({{ alert.stock_lot.qty_remaining }} left)
                                </span>
                                <span>
                                    Owner:
                                    <span
                                        class="font-medium text-foreground/90"
                                        >{{
                                            alert.assigned_to?.name ??
                                            'Unassigned'
                                        }}</span
                                    >
                                </span>
                            </div>
                        </div>

                        <div
                            class="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end"
                        >
                            <Button
                                v-if="alert.is_active && !alert.acknowledged_at"
                                size="sm"
                                class="rounded-lg"
                                :disabled="processingAlertId === alert.id"
                                data-testid="alert-acknowledge-button"
                                @click="acknowledgeAlert(alert)"
                            >
                                <Eye class="mr-1.5 h-3.5 w-3.5" />
                                Acknowledge
                            </Button>
                            <Button
                                v-else-if="alert.is_active"
                                size="sm"
                                class="rounded-lg bg-emerald-600 text-white hover:bg-emerald-600/90"
                                :disabled="processingAlertId === alert.id"
                                data-testid="alert-resolve-button"
                                @click="openResolveDialog(alert)"
                            >
                                <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" />
                                Resolve
                            </Button>

                            <DropdownMenu v-if="alert.is_active">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="rounded-lg"
                                        :disabled="
                                            processingAlertId === alert.id
                                        "
                                        data-testid="alert-assign-button"
                                    >
                                        <UserPlus class="mr-1.5 h-3.5 w-3.5" />
                                        {{
                                            alert.assigned_to
                                                ? 'Reassign'
                                                : 'Assign'
                                        }}
                                        <ChevronDown class="ml-1 h-3.5 w-3.5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-60">
                                    <DropdownMenuLabel class="text-xs">
                                        Assign to an Admin or Supply Head
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        v-for="operator in operators"
                                        :key="operator.id"
                                        :disabled="
                                            alert.assigned_to?.id ===
                                            operator.id
                                        "
                                        @select="
                                            assignAlert(alert, operator.id)
                                        "
                                    >
                                        <div class="min-w-0">
                                            <div class="truncate text-sm">
                                                {{ operator.name
                                                }}{{
                                                    operator.id ===
                                                    currentUserId
                                                        ? ' (you)'
                                                        : ''
                                                }}
                                            </div>
                                            <div
                                                class="truncate text-[11px] text-muted-foreground"
                                            >
                                                {{ operator.email }}
                                            </div>
                                        </div>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Button
                                size="sm"
                                variant="ghost"
                                class="rounded-lg"
                                :aria-expanded="expandedAlertIds.has(alert.id)"
                                @click="toggleDetails(alert.id)"
                            >
                                History
                                <ChevronDown
                                    class="ml-1 h-3.5 w-3.5 transition-transform"
                                    :class="{
                                        'rotate-180': expandedAlertIds.has(
                                            alert.id,
                                        ),
                                    }"
                                />
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="expandedAlertIds.has(alert.id)"
                        class="mt-3 grid gap-4 rounded-lg border border-border/60 bg-muted/30 p-3 text-xs sm:grid-cols-[1fr_auto]"
                    >
                        <ol class="space-y-2">
                            <li class="flex gap-2">
                                <span
                                    class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500"
                                />
                                <span>
                                    <span class="font-semibold">Detected</span>
                                    · {{ formatDate(alert.detected_at) }}
                                </span>
                            </li>
                            <li class="flex gap-2">
                                <span
                                    class="mt-1 h-2 w-2 shrink-0 rounded-full"
                                    :class="
                                        alert.acknowledged_at
                                            ? 'bg-sky-500'
                                            : 'bg-border'
                                    "
                                />
                                <span v-if="alert.acknowledged_at">
                                    <span class="font-semibold"
                                        >Acknowledged</span
                                    >
                                    by
                                    {{
                                        alert.acknowledged_by?.name ?? 'Unknown'
                                    }}
                                    · {{ formatDate(alert.acknowledged_at) }}
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >Not acknowledged yet</span
                                >
                            </li>
                            <li class="flex gap-2">
                                <span
                                    class="mt-1 h-2 w-2 shrink-0 rounded-full"
                                    :class="
                                        alert.resolved_at
                                            ? 'bg-emerald-500'
                                            : 'bg-border'
                                    "
                                />
                                <span v-if="alert.resolved_at">
                                    <span class="font-semibold">Resolved</span>
                                    <template v-if="alert.resolved_by">
                                        by
                                        {{ alert.resolved_by.name }}</template
                                    >
                                    <template v-else>
                                        automatically when stock
                                        recovered</template
                                    >
                                    · {{ formatDate(alert.resolved_at) }}
                                    <span
                                        v-if="alert.resolution_notes"
                                        class="mt-1 block rounded-md bg-background/80 px-2 py-1 text-foreground/90"
                                        >“{{ alert.resolution_notes }}”</span
                                    >
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >Not resolved yet</span
                                >
                            </li>
                        </ol>
                        <div v-if="alert.product" class="flex flex-col gap-1.5">
                            <Link
                                :href="productShow(alert.product.id)"
                                class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                            >
                                <ExternalLink class="h-3 w-3" />
                                Open product record
                            </Link>
                            <Link
                                v-if="alert.type !== 'expiring'"
                                :href="forecastShow(alert.product.id)"
                                class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                            >
                                <ExternalLink class="h-3 w-3" />
                                View demand forecast
                            </Link>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <AdminPagination :links="alerts.links" />

        <Dialog v-model:open="resolveDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Resolve alert</DialogTitle>
                    <DialogDescription>
                        {{ selectedAlert?.message }}
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="resolution-notes"
                        >What was done? (optional)</Label
                    >
                    <textarea
                        id="resolution-notes"
                        v-model="resolveForm.resolution_notes"
                        rows="4"
                        maxlength="2000"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none"
                        placeholder="e.g. Purchase order PO-2026-0042 raised for 200 units."
                    />
                    <InputError
                        :message="resolveForm.errors.resolution_notes"
                    />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="ghost" type="button">Cancel</Button>
                    </DialogClose>
                    <Button
                        :disabled="resolveForm.processing"
                        data-testid="alert-resolve-confirm"
                        @click="submitResolve"
                    >
                        {{
                            resolveForm.processing
                                ? 'Resolving…'
                                : 'Resolve alert'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
