<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import {
    AlertOctagon,
    AlertTriangle,
    Calendar,
    Download,
    FileText,
    PackageCheck,
    ShoppingCart,
    Truck,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import RecentlyDeletedPanel from '@/components/dashboard/RecentlyDeletedPanel.vue';
import RecommendedActionsPanel from '@/components/dashboard/RecommendedActionsPanel.vue';
import SystemAlertsPanel from '@/components/dashboard/SystemAlertsPanel.vue';
import Heading from '@/components/Heading.vue';
import ForecastWidget from '@/components/inventory/ForecastWidget.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

type Alert = { id: number; type: string; message: string; detected_at: string };
type TrendData = { labels: string[]; data: number[] };
type SummaryData = Record<string, number>;
type ForecastSummary = {
    forecast_date: string | null;
    last_generated_at: string | null;
    urgent_count: number;
    at_risk_count: number;
    average_confidence: number | null;
    items: {
        product_id: number;
        product_name: string;
        sku: string;
        current_on_hand_qty: number;
        reorder_point_qty: number;
        predicted_daily_consumption: number;
        predicted_days_until_stockout: number | null;
        predicted_stockout_date: string | null;
        recommended_reorder_qty: number;
        confidence_score: number | null;
    }[];
};
type RecentlyDeleted = {
    id: number;
    type: string;
    name: string;
    deleted_at: string;
    deleted_by: string;
    restore_url: string;
};
type SupplierPerformanceRow = {
    id: number;
    name: string;
    total_pos: number;
    open_pos: number;
    avg_lead_time_days: number | null;
};

type KpiSummary = {
    issued_today_count?: number;
    near_expiry_batch_count?: number;
    low_stock_count?: number;
    pending_requisitions_count?: number;
    pending_bookings_count?: number;
    active_alerts_count?: number;
    open_purchase_orders_count?: number;
    my_open_requisitions?: number;
    my_upcoming_bookings?: number;
    pending_handovers?: number;
    assigned_assets?: number;
};

type NearExpiryLot = {
    id: number;
    product_name: string;
    sku: string;
    qty_remaining: number;
    expires_at: string;
};

type CustodianSummary = {
    my_open_requisitions: number;
    my_upcoming_bookings: number;
    assigned_assets: number;
    pending_handovers: number;
};

const props = defineProps<{
    dashboardVariant: 'admin' | 'supply_head' | 'custodian';
    canViewForecasting: boolean;
    dateRange: { from: string | null; to: string | null };
    alerts: Alert[];
    forecastSummary: ForecastSummary;
    lowStock: {
        id: number;
        sku: string;
        name: string;
        category: string | null;
        on_hand_qty: number | null;
        reorder_threshold: number;
    }[];
    unserviceableAssets: {
        id: number;
        tag_code: string;
        status: string;
        name: string | null;
    }[];
    assetStatusCounts: { labels: string[]; data: number[] };
    receivingTrends: TrendData;
    issuingTrends: TrendData;
    requisitionSummary: SummaryData;
    bookingSummary: SummaryData;
    purchaseOrderSummary: SummaryData;
    supplierPerformance: SupplierPerformanceRow[];
    assetConditionSummary: SummaryData;
    recentlyDeleted: RecentlyDeleted[];
    kpiSummary: KpiSummary;
    nearExpiryLots: NearExpiryLot[];
    custodianSummary: CustodianSummary | null;
    exportUrls: {
        assetConditionsCsv: string;
        assetConditionsPdf: string;
    } | null;
}>();

defineOptions({
    name: 'DashboardPage',
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const isAdmin = computed(() => props.dashboardVariant === 'admin');
const isSupplyHead = computed(() => props.dashboardVariant === 'supply_head');
const isCustodian = computed(() => props.dashboardVariant === 'custodian');
const canViewProcurement = computed(() => isAdmin.value || isSupplyHead.value);
const canUseDateRange = computed(() => isAdmin.value || isSupplyHead.value);
const dashboardDescription = computed(() => {
    if (isAdmin.value) {
        return 'Review inventory activity, pending work, and items that may need attention.';
    }

    if (isSupplyHead.value) {
        return 'Track stock activity, requisitions, bookings, and open purchase orders.';
    }

    return 'Review your requisitions, bookings, assigned assets, and pending handovers.';
});

const pendingRequisitionsCount = computed(
    () =>
        props.kpiSummary.pending_requisitions_count ??
        (() => {
            const summary = props.requisitionSummary ?? {};
            const key = Object.keys(summary).find(
                (k) => k.toLowerCase() === 'submitted',
            );

            return key ? Number(summary[key]) : 0;
        })(),
);

const pendingBookingsCount = computed(
    () =>
        props.kpiSummary.pending_bookings_count ??
        (() => {
            const summary = props.bookingSummary ?? {};
            const key = Object.keys(summary).find(
                (k) => k.toLowerCase() === 'requested',
            );

            return key ? Number(summary[key]) : 0;
        })(),
);

const openPurchaseOrdersCount = computed(() => {
    const summary = props.purchaseOrderSummary ?? {};

    return ['draft', 'sent', 'partial'].reduce(
        (total, key) => total + Number(summary[key] ?? 0),
        0,
    );
});

const fromDate = ref(props.dateRange.from ?? '');
const toDate = ref(props.dateRange.to ?? '');
const dateRangeError = computed(() => {
    if (!fromDate.value || !toDate.value || fromDate.value <= toDate.value) {
        return null;
    }

    return 'The end date must be on or after the start date.';
});

function applyDateRange(): void {
    if (dateRangeError.value) {
        return;
    }

    router.get(
        dashboard(),
        {
            from: fromDate.value || null,
            to: toDate.value || null,
        },
        { preserveScroll: true },
    );
}

function setPreset(preset: 'today' | 'week' | 'month'): void {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');

    if (preset === 'today') {
        fromDate.value = `${yyyy}-${mm}-${dd}`;
        toDate.value = `${yyyy}-${mm}-${dd}`;
    } else if (preset === 'week') {
        const start = new Date(today);
        start.setDate(today.getDate() - today.getDay());
        fromDate.value = formatDate(start);
        toDate.value = `${yyyy}-${mm}-${dd}`;
    } else if (preset === 'month') {
        fromDate.value = `${yyyy}-${mm}-01`;
        toDate.value = `${yyyy}-${mm}-${dd}`;
    }

    applyDateRange();
}

function formatDate(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatDayCount(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${value} ${value === 1 ? 'day' : 'days'}`;
}

function formatStatusLabel(value: string): string {
    return value
        .replace(/_/g, ' ')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function chartAriaLabel(trend: TrendData, metric: string): string {
    if (trend.labels.length === 0 || trend.data.every((value) => value === 0)) {
        return `${metric}: no activity in the selected date range.`;
    }

    return `${metric}: ${trend.labels
        .map((label, index) => `${label}, ${trend.data[index] ?? 0}`)
        .join('; ')}.`;
}

function assetConditionChartLabel(): string {
    if (props.assetStatusCounts.labels.length === 0) {
        return 'Asset conditions: no asset condition data is available.';
    }

    return `Asset conditions: ${props.assetStatusCounts.labels
        .map(
            (label, index) =>
                `${formatStatusLabel(label)}, ${props.assetStatusCounts.data[index] ?? 0}`,
        )
        .join('; ')}.`;
}

function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

const assetStatusCanvas = ref<HTMLCanvasElement | null>(null);
const receivingCanvas = ref<HTMLCanvasElement | null>(null);
const issuingCanvas = ref<HTMLCanvasElement | null>(null);

let assetStatusChart: Chart | null = null;
let receivingChart: Chart | null = null;
let issuingChart: Chart | null = null;

const chartColors = [
    'hsl(222 65% 52%)',
    'hsl(38 95% 55%)',
    'hsl(152 65% 45%)',
    'hsl(205 90% 55%)',
    'hsl(260 70% 60%)',
    'hsl(345 80% 58%)',
    'hsl(43 90% 55%)',
    'hsl(170 65% 45%)',
];

const chartHoverColors = [
    'hsl(222 65% 62%)',
    'hsl(38 95% 65%)',
    'hsl(152 65% 55%)',
    'hsl(205 90% 65%)',
    'hsl(260 70% 70%)',
    'hsl(345 80% 68%)',
    'hsl(43 90% 65%)',
    'hsl(170 65% 55%)',
];

function getCssVar(name: string): string {
    return (
        getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim() || '#000'
    );
}

function renderBarChart(
    canvas: HTMLCanvasElement,
    labels: string[],
    data: number[],
    label: string,
): Chart {
    const textColor = getCssVar('--foreground');
    const gridColor = getCssVar('--border');
    const mutedColor = getCssVar('--muted-foreground');
    const barColors = labels.map((_, i) => chartColors[i % chartColors.length]);
    const barHoverColors = labels.map(
        (_, i) => chartHoverColors[i % chartHoverColors.length],
    );

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label,
                    data,
                    backgroundColor: barColors,
                    hoverBackgroundColor: barHoverColors,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: getCssVar('--card'),
                    titleColor: textColor,
                    bodyColor: textColor,
                    borderColor: gridColor,
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: mutedColor,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11,
                            weight: 500,
                        },
                    },
                    grid: { display: false },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: mutedColor,
                        font: { family: "'Inter', sans-serif", size: 11 },
                        padding: 8,
                    },
                    grid: { color: gridColor, lineWidth: 1 },
                    border: { display: false },
                },
            },
            interaction: { mode: 'index', intersect: false },
            onHover: (event, activeElements) => {
                if (event.native?.target instanceof HTMLElement) {
                    event.native.target.style.cursor =
                        activeElements.length > 0 ? 'pointer' : 'default';
                }
            },
        },
    });
}

function renderLineChart(
    canvas: HTMLCanvasElement,
    labels: string[],
    data: number[],
    label: string,
    color: string,
): Chart {
    const textColor = getCssVar('--foreground');
    const gridColor = getCssVar('--border');
    const mutedColor = getCssVar('--muted-foreground');

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label,
                    data,
                    borderColor: color,
                    backgroundColor: color.replace(')', ' / 0.1)'),
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: getCssVar('--card'),
                    titleColor: textColor,
                    bodyColor: textColor,
                    borderColor: gridColor,
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: mutedColor,
                        font: { family: "'Inter', sans-serif", size: 10 },
                        maxRotation: 45,
                    },
                    grid: { display: false },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: mutedColor,
                        font: { family: "'Inter', sans-serif", size: 11 },
                        padding: 8,
                    },
                    grid: { color: gridColor, lineWidth: 1 },
                    border: { display: false },
                },
            },
            interaction: { mode: 'index', intersect: false },
            onHover: (event, activeElements) => {
                if (event.native?.target instanceof HTMLElement) {
                    event.native.target.style.cursor =
                        activeElements.length > 0 ? 'pointer' : 'default';
                }
            },
        },
    });
}

onMounted(() => {
    if (!isAdmin.value) {
        return;
    }

    nextTick(() => {
        if (assetStatusCanvas.value) {
            assetStatusChart = renderBarChart(
                assetStatusCanvas.value,
                props.assetStatusCounts.labels,
                props.assetStatusCounts.data,
                'Assets',
            );
        }

        if (receivingCanvas.value) {
            receivingChart = renderLineChart(
                receivingCanvas.value,
                props.receivingTrends.labels,
                props.receivingTrends.data,
                'Received',
                'hsl(152 65% 45%)',
            );
        }

        if (issuingCanvas.value) {
            issuingChart = renderLineChart(
                issuingCanvas.value,
                props.issuingTrends.labels,
                props.issuingTrends.data,
                'Issued',
                'hsl(205 90% 55%)',
            );
        }
    });
});

onBeforeUnmount(() => {
    assetStatusChart?.destroy();
    receivingChart?.destroy();
    issuingChart?.destroy();
    assetStatusChart = null;
    receivingChart = null;
    issuingChart = null;
});

watch(
    () => [props.receivingTrends, props.issuingTrends, props.assetStatusCounts],
    () => {
        if (!isAdmin.value) {
            return;
        }

        nextTick(() => {
            assetStatusChart?.destroy();
            receivingChart?.destroy();
            issuingChart?.destroy();

            if (assetStatusCanvas.value) {
                assetStatusChart = renderBarChart(
                    assetStatusCanvas.value,
                    props.assetStatusCounts.labels,
                    props.assetStatusCounts.data,
                    'Assets',
                );
            }

            if (receivingCanvas.value) {
                receivingChart = renderLineChart(
                    receivingCanvas.value,
                    props.receivingTrends.labels,
                    props.receivingTrends.data,
                    'Received',
                    'hsl(152 65% 45%)',
                );
            }

            if (issuingCanvas.value) {
                issuingChart = renderLineChart(
                    issuingCanvas.value,
                    props.issuingTrends.labels,
                    props.issuingTrends.data,
                    'Issued',
                    'hsl(205 90% 55%)',
                );
            }
        });
    },
    { deep: true },
);

function summaryEntries(
    summary: SummaryData,
): { key: string; value: number }[] {
    return Object.entries(summary).map(([key, value]) => ({ key, value }));
}

function todayRange(): { from: string; to: string } {
    const today = new Date();
    const value = formatDate(today);

    return { from: value, to: value };
}

function weekRange(): { from: string; to: string } {
    const today = new Date();
    const start = new Date(today);
    start.setDate(today.getDate() - today.getDay());

    return { from: formatDate(start), to: formatDate(today) };
}

function monthRange(): { from: string; to: string } {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');

    return { from: `${yyyy}-${mm}-01`, to: formatDate(today) };
}

function isPresetActive(preset: 'today' | 'week' | 'month'): boolean {
    const range =
        preset === 'today'
            ? todayRange()
            : preset === 'week'
              ? weekRange()
              : monthRange();

    return fromDate.value === range.from && toDate.value === range.to;
}

function presetButtonClass(preset: 'today' | 'week' | 'month'): string {
    return isPresetActive(preset)
        ? 'border-primary/50 bg-primary/15 text-primary shadow-sm shadow-primary/10'
        : 'border-border/70 bg-background/70 text-muted-foreground hover:border-primary/40 hover:bg-primary/10 hover:text-foreground';
}

function kpiCardClass(
    tone: 'blue' | 'amber' | 'rose' | 'purple' | 'cyan',
): string {
    const tones = {
        blue: 'border-border/70 border-t-blue-500/65 hover:border-blue-500/35',
        amber: 'border-border/70 border-t-amber-500/65 hover:border-amber-500/35',
        rose: 'border-border/70 border-t-rose-500/65 hover:border-rose-500/35',
        purple: 'border-border/70 border-t-primary/70 hover:border-primary/35',
        cyan: 'border-border/70 border-t-cyan-500/65 hover:border-cyan-500/35',
    };

    return `group relative overflow-hidden rounded-2xl border border-t-2 bg-card p-4 shadow-xs transition-[border-color,box-shadow] duration-200 hover:shadow-sm ${tones[tone]}`;
}

function iconBubbleClass(
    tone: 'blue' | 'amber' | 'rose' | 'purple' | 'cyan',
): string {
    const tones = {
        blue: 'bg-blue-500/15 text-blue-600 ring-blue-500/20 dark:text-blue-300',
        amber: 'bg-amber-500/15 text-amber-600 ring-amber-500/20 dark:text-amber-300',
        rose: 'bg-rose-500/15 text-rose-600 ring-rose-500/20 dark:text-rose-300',
        purple: 'bg-primary/15 text-primary ring-primary/20',
        cyan: 'bg-cyan-500/15 text-cyan-600 ring-cyan-500/20 dark:text-cyan-300',
    };

    return `flex h-10 w-10 items-center justify-center rounded-xl ring-1 ${tones[tone]}`;
}

function summaryToneClass(key: string): string {
    const value = key.toLowerCase();

    if (
        value.includes('approved') ||
        value.includes('completed') ||
        value.includes('received')
    ) {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (
        value.includes('pending') ||
        value.includes('submitted') ||
        value.includes('requested') ||
        value.includes('draft')
    ) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (
        value.includes('rejected') ||
        value.includes('cancel') ||
        value.includes('condemned') ||
        value.includes('unserviceable')
    ) {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (
        value.includes('sent') ||
        value.includes('partial') ||
        value.includes('issued')
    ) {
        return 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300';
    }

    return 'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300';
}
</script>

<template>
    <Head title="Dashboard" />

    <div
        data-testid="dashboard-page"
        class="mx-auto flex h-full w-full max-w-400 flex-1 flex-col gap-5 overflow-x-hidden bg-muted/15 p-4 sm:gap-6 sm:p-6 dark:bg-background"
    >
        <div
            class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div
                    class="mb-2 text-xs font-medium tracking-wide text-muted-foreground"
                >
                    Operational overview
                </div>
                <Heading
                    variant="small"
                    title="Dashboard"
                    :description="dashboardDescription"
                />
            </div>

            <div v-if="props.exportUrls" class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-xl border-primary/25 bg-primary/8 text-primary shadow-xs transition-colors hover:border-primary/45 hover:bg-primary/12"
                >
                    <a
                        :href="props.exportUrls.assetConditionsCsv"
                        class="gap-2"
                    >
                        <Download aria-hidden="true" class="h-3.5 w-3.5" />
                        Download Asset Report (CSV)
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-xl border-brand-gold/30 bg-brand-gold/8 text-brand-gold shadow-xs transition-colors hover:border-brand-gold/50 hover:bg-brand-gold/12"
                >
                    <a
                        :href="props.exportUrls.assetConditionsPdf"
                        class="gap-2"
                    >
                        <Download aria-hidden="true" class="h-3.5 w-3.5" />
                        Download Asset Report (PDF)
                    </a>
                </Button>
            </div>
        </div>

        <!-- Date Range Filter -->
        <fieldset
            v-if="canUseDateRange"
            class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-4 shadow-xs xl:flex-row xl:items-center xl:justify-between"
        >
            <legend class="sr-only">Dashboard date range</legend>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="rounded-xl px-3 text-xs transition-colors"
                    :class="presetButtonClass('today')"
                    :aria-pressed="isPresetActive('today')"
                    @click="setPreset('today')"
                    >Today</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="rounded-xl px-3 text-xs transition-colors"
                    :class="presetButtonClass('week')"
                    :aria-pressed="isPresetActive('week')"
                    @click="setPreset('week')"
                    >This week</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="rounded-xl px-3 text-xs transition-colors"
                    :class="presetButtonClass('month')"
                    :aria-pressed="isPresetActive('month')"
                    @click="setPreset('month')"
                    >This month</Button
                >
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    From
                    <input
                        v-model="fromDate"
                        name="dashboard_from"
                        type="date"
                        :aria-invalid="Boolean(dateRangeError)"
                        :aria-describedby="
                            dateRangeError
                                ? 'dashboard-date-range-error'
                                : undefined
                        "
                        class="h-11 rounded-xl border border-border/70 bg-background/80 px-3 text-sm text-foreground shadow-inner shadow-black/5 transition-colors hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-ring/30 focus:outline-none sm:h-10 sm:text-xs"
                    />
                </label>
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    To
                    <input
                        v-model="toDate"
                        name="dashboard_to"
                        type="date"
                        :aria-invalid="Boolean(dateRangeError)"
                        :aria-describedby="
                            dateRangeError
                                ? 'dashboard-date-range-error'
                                : undefined
                        "
                        class="h-11 rounded-xl border border-border/70 bg-background/80 px-3 text-sm text-foreground shadow-inner shadow-black/5 transition-colors hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-ring/30 focus:outline-none sm:h-10 sm:text-xs"
                    />
                </label>
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    :disabled="Boolean(dateRangeError)"
                    class="h-10 rounded-xl px-5 text-xs font-semibold shadow-lg shadow-primary/20"
                    @click="applyDateRange"
                    >Apply Date Range</Button
                >
            </div>
            <p
                v-if="dateRangeError"
                id="dashboard-date-range-error"
                role="alert"
                class="text-sm font-medium text-destructive xl:basis-full"
            >
                {{ dateRangeError }}
            </p>
        </fieldset>

        <!-- KPI Stats Row -->
        <div
            v-if="isAdmin"
            class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-6"
        >
            <!-- 1. Issued Today -->
            <div :class="kpiCardClass('blue')">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-blue-700 uppercase dark:text-blue-300"
                        >Issued Today</span
                    >
                    <div :class="iconBubbleClass('blue')">
                        <PackageCheck class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div
                    class="mt-4 font-display text-3xl font-bold text-foreground"
                >
                    {{ kpiSummary.issued_today_count ?? 0 }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Issue movements recorded today
                </div>
            </div>

            <!-- 2. Low Stock -->
            <div :class="kpiCardClass('amber')">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-amber-700 uppercase dark:text-amber-300"
                        >Low Stock</span
                    >
                    <div :class="iconBubbleClass('amber')">
                        <AlertTriangle class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div
                    class="mt-4 font-display text-3xl font-bold text-foreground"
                >
                    {{ lowStock.length }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Consumables low
                </div>
            </div>

            <!-- 3. Near Expiry -->
            <div :class="kpiCardClass('rose')">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-rose-700 uppercase dark:text-rose-300"
                        >Near Expiry</span
                    >
                    <div :class="iconBubbleClass('rose')">
                        <Calendar class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div
                    class="mt-4 font-display text-3xl font-bold text-foreground"
                >
                    {{ kpiSummary.near_expiry_batch_count ?? 0 }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Batches expiring within 30 days
                </div>
            </div>

            <!-- 4. Pending Requisitions -->
            <div :class="kpiCardClass('purple')">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-primary uppercase"
                        >Pending Requisitions</span
                    >
                    <div :class="iconBubbleClass('purple')">
                        <FileText class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div
                    class="mt-4 font-display text-3xl font-bold text-foreground"
                >
                    {{ pendingRequisitionsCount }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Awaiting approval
                </div>
            </div>

            <!-- 5. Pending Bookings -->
            <div :class="kpiCardClass('cyan')">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-cyan-700 uppercase dark:text-cyan-300"
                        >Pending Bookings</span
                    >
                    <div :class="iconBubbleClass('cyan')">
                        <Calendar class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div
                    class="mt-4 font-display text-3xl font-bold text-foreground"
                >
                    {{ pendingBookingsCount }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Awaiting review
                </div>
            </div>

            <!-- 6. Critical Alerts -->
            <div :class="kpiCardClass('rose')">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-rose-700 uppercase dark:text-rose-300"
                        >Active Alerts</span
                    >
                    <div :class="iconBubbleClass('rose')">
                        <AlertOctagon class="h-4.5 w-4.5" />
                    </div>
                </div>
                <div
                    class="mt-4 font-display text-3xl font-bold text-foreground"
                >
                    {{ alerts.length }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Operational warnings to review
                </div>
            </div>
        </div>

        <!-- PRISM Intelligence Panel -->
        <RecommendedActionsPanel
            v-if="isAdmin"
            :low-stock-count="lowStock.length"
            :first-low-stock-name="lowStock[0]?.name ?? null"
            :near-expiry-batch-count="kpiSummary.near_expiry_batch_count ?? 0"
            :pending-requisitions-count="pendingRequisitionsCount"
            :pending-bookings-count="pendingBookingsCount"
        />

        <div
            v-if="isSupplyHead"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Open POs
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{
                        kpiSummary.open_purchase_orders_count ??
                        openPurchaseOrdersCount
                    }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Low Stock
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{ lowStock.length }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Pending Requisitions
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{ pendingRequisitionsCount }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Forecast Urgent
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{ forecastSummary.urgent_count }}
                </div>
            </div>
        </div>

        <div
            v-if="isCustodian && custodianSummary"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    My Open Requisitions
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{ custodianSummary.my_open_requisitions }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Upcoming Bookings
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{ custodianSummary.my_upcoming_bookings }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Assigned Assets
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{ custodianSummary.assigned_assets }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-xs"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Pending Handovers
                </div>
                <div class="mt-3 font-display text-2xl font-bold">
                    {{ custodianSummary.pending_handovers }}
                </div>
            </div>
        </div>

        <div v-if="isCustodian" class="grid gap-6 lg:grid-cols-2">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 text-sm font-semibold tracking-tight">
                    My requisition activity
                </div>
                <ul
                    v-if="summaryEntries(requisitionSummary).length"
                    class="space-y-2 text-sm"
                >
                    <li
                        v-for="entry in summaryEntries(requisitionSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-lg border border-border/40 p-2"
                    >
                        <span class="text-muted-foreground capitalize">{{
                            formatStatusLabel(entry.key)
                        }}</span>
                        <span class="font-mono text-xs font-semibold">{{
                            entry.value
                        }}</span>
                    </li>
                </ul>
                <div v-else class="text-sm text-muted-foreground">
                    No requisition activity in the selected range.
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 text-sm font-semibold tracking-tight">
                    My booking activity
                </div>
                <ul
                    v-if="summaryEntries(bookingSummary).length"
                    class="space-y-2 text-sm"
                >
                    <li
                        v-for="entry in summaryEntries(bookingSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-lg border border-border/40 p-2"
                    >
                        <span class="text-muted-foreground capitalize">{{
                            formatStatusLabel(entry.key)
                        }}</span>
                        <span class="font-mono text-xs font-semibold">{{
                            entry.value
                        }}</span>
                    </li>
                </ul>
                <div v-else class="text-sm text-muted-foreground">
                    No booking activity in the selected range.
                </div>
            </div>
        </div>

        <!-- Trends -->
        <div v-if="isAdmin" class="grid gap-6 lg:grid-cols-2">
            <div
                class="overflow-hidden rounded-2xl border border-border/70 bg-card p-5 shadow-xs transition-[border-color,box-shadow] hover:border-emerald-500/30 hover:shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/20 dark:text-emerald-300"
                        >
                            <Truck class="h-4.5 w-4.5" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Receiving trends
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Inbound stock movement velocity
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"
                    >
                        Received
                    </div>
                </div>
                <div
                    v-if="
                        receivingTrends.data.length === 0 ||
                        receivingTrends.data.every((v) => v === 0)
                    "
                    class="rounded-2xl border border-dashed border-emerald-500/20 bg-emerald-500/5 px-5 py-8 text-center text-sm text-muted-foreground"
                >
                    No receiving activity was recorded in this date range.
                </div>
                <div v-else class="h-48">
                    <canvas
                        ref="receivingCanvas"
                        role="img"
                        :aria-label="
                            chartAriaLabel(receivingTrends, 'Units received')
                        "
                    />
                </div>
            </div>

            <div
                class="overflow-hidden rounded-2xl border border-border/70 bg-card p-5 shadow-xs transition-[border-color,box-shadow] hover:border-blue-500/30 hover:shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 ring-1 ring-blue-500/20 dark:text-blue-300"
                        >
                            <PackageCheck class="h-4.5 w-4.5" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Issuing trends
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Outbound issuance over the selected range
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-full border border-blue-500/20 bg-blue-500/10 px-2.5 py-1 text-[11px] font-semibold text-blue-700 dark:text-blue-300"
                    >
                        Issued
                    </div>
                </div>
                <div
                    v-if="
                        issuingTrends.data.length === 0 ||
                        issuingTrends.data.every((v) => v === 0)
                    "
                    class="rounded-2xl border border-dashed border-blue-500/20 bg-blue-500/5 px-5 py-8 text-center text-sm text-muted-foreground"
                >
                    No issuance activity was recorded in this date range.
                </div>
                <div v-else class="h-48">
                    <canvas
                        ref="issuingCanvas"
                        role="img"
                        :aria-label="
                            chartAriaLabel(issuingTrends, 'Units issued')
                        "
                    />
                </div>
            </div>
        </div>

        <!-- Summaries -->
        <div v-if="isAdmin" class="grid gap-6 lg:grid-cols-3">
            <div
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary/15 text-primary ring-1 ring-primary/20"
                        >
                            <FileText class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Requisition status
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Approval pipeline
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="summaryEntries(requisitionSummary).length === 0"
                    class="rounded-2xl border border-dashed border-border/60 bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    No requisitions were recorded in this date range.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="entry in summaryEntries(requisitionSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-primary/25 hover:bg-primary/5"
                    >
                        <span class="font-medium text-foreground capitalize">{{
                            formatStatusLabel(entry.key)
                        }}</span>
                        <span
                            class="rounded-full border px-2.5 py-1 font-mono text-xs font-semibold"
                            :class="summaryToneClass(entry.key)"
                            >{{ entry.value }}</span
                        >
                    </li>
                </ul>
            </div>

            <div
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-300"
                        >
                            <Calendar class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Booking status
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Reservation lifecycle
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="summaryEntries(bookingSummary).length === 0"
                    class="rounded-2xl border border-dashed border-border/60 bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    No bookings were recorded in this date range.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="entry in summaryEntries(bookingSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-amber-500/25 hover:bg-amber-500/5"
                    >
                        <span class="font-medium text-foreground capitalize">{{
                            formatStatusLabel(entry.key)
                        }}</span>
                        <span
                            class="rounded-full border px-2.5 py-1 font-mono text-xs font-semibold"
                            :class="summaryToneClass(entry.key)"
                            >{{ entry.value }}</span
                        >
                    </li>
                </ul>
            </div>

            <div
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/20 dark:text-rose-300"
                        >
                            <AlertOctagon class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Asset conditions
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Serviceability mix
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="summaryEntries(assetConditionSummary).length === 0"
                    class="rounded-2xl border border-dashed border-border/60 bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    No asset condition records are available.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="entry in summaryEntries(assetConditionSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-rose-500/25 hover:bg-rose-500/5"
                    >
                        <span class="font-medium text-foreground capitalize">{{
                            formatStatusLabel(entry.key)
                        }}</span>
                        <span
                            class="rounded-full border px-2.5 py-1 font-mono text-xs font-semibold"
                            :class="summaryToneClass(entry.key)"
                            >{{ entry.value }}</span
                        >
                    </li>
                </ul>
            </div>
        </div>

        <div
            v-if="canViewProcurement"
            class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]"
        >
            <div
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 ring-1 ring-blue-500/20 dark:text-blue-300"
                        >
                            <ShoppingCart class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Purchase order status
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Procurement queue health
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="mb-4 rounded-2xl border border-blue-500/20 bg-blue-500/10 p-4"
                >
                    <div
                        class="text-xs font-semibold tracking-wider text-blue-700 uppercase dark:text-blue-300"
                    >
                        Open purchase orders
                    </div>
                    <div
                        class="mt-2 font-display text-3xl font-bold text-foreground"
                    >
                        {{ openPurchaseOrdersCount }}
                    </div>
                </div>
                <ul
                    v-if="summaryEntries(purchaseOrderSummary).length > 0"
                    class="space-y-2 text-sm"
                >
                    <li
                        v-for="entry in summaryEntries(purchaseOrderSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-blue-500/25 hover:bg-blue-500/5"
                    >
                        <span class="font-medium text-foreground capitalize">
                            {{ entry.key }}
                        </span>
                        <span
                            class="rounded-full border px-2.5 py-1 font-mono text-xs font-semibold"
                            :class="summaryToneClass(entry.key)"
                        >
                            {{ entry.value }}
                        </span>
                    </li>
                </ul>
                <div v-else class="text-sm text-muted-foreground">
                    No purchase order activity for the selected range.
                </div>
            </div>

            <div
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/20 dark:text-emerald-300"
                        >
                            <Truck class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Supplier performance
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Lead time and open PO load
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="supplierPerformance.length === 0"
                    class="rounded-2xl border border-dashed border-emerald-500/20 bg-emerald-500/5 px-5 py-8 text-center text-sm text-muted-foreground"
                >
                    No supplier procurement history yet.
                </div>
                <div v-else class="grid gap-3">
                    <div
                        v-for="supplier in supplierPerformance"
                        :key="supplier.id"
                        class="rounded-2xl border border-border/40 bg-background/50 p-4 transition-colors hover:border-emerald-500/25 hover:bg-emerald-500/5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium">{{ supplier.name }}</div>
                            <div
                                class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                {{
                                    pluralize(
                                        supplier.total_pos,
                                        'purchase order',
                                        'purchase orders',
                                    )
                                }}
                            </div>
                        </div>
                        <div
                            class="mt-3 grid gap-2 text-sm text-muted-foreground md:grid-cols-2"
                        >
                            <div class="rounded-xl bg-muted/30 p-3">
                                Open:
                                <span class="font-semibold text-foreground">{{
                                    supplier.open_pos
                                }}</span>
                            </div>
                            <div class="rounded-xl bg-muted/30 p-3">
                                Avg lead time:
                                <span class="font-semibold text-foreground">{{
                                    formatDayCount(supplier.avg_lead_time_days)
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing widgets -->
        <div v-if="isAdmin" class="grid gap-6 lg:grid-cols-2">
            <div
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/20 dark:text-rose-300"
                        >
                            <AlertOctagon class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Unserviceable / Condemned assets
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Condition distribution
                            </div>
                        </div>
                    </div>
                </div>
                <div class="h-64">
                    <canvas
                        ref="assetStatusCanvas"
                        role="img"
                        :aria-label="assetConditionChartLabel()"
                    />
                </div>
            </div>

            <div
                class="rounded-2xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-300"
                        >
                            <AlertTriangle class="h-4 w-4" />
                        </div>
                        <div>
                            <div
                                class="text-sm leading-tight font-semibold tracking-tight"
                            >
                                Low-stock consumables
                            </div>
                            <div class="mt-0.5 text-xs text-muted-foreground">
                                Items below reorder threshold
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="lowStock.length === 0"
                    class="rounded-2xl border border-dashed border-emerald-500/20 bg-emerald-500/5 px-5 py-8 text-center text-sm text-muted-foreground"
                >
                    No low-stock items found.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="p in lowStock"
                        :key="p.id"
                        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-amber-500/15 bg-background/50 px-3 py-2.5 transition-colors hover:border-amber-500/30 hover:bg-amber-500/5"
                    >
                        <div class="min-w-0">
                            <div class="truncate leading-tight font-medium">
                                {{ p.name }}
                            </div>
                            <div
                                class="mt-1 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                {{ p.category ?? '—' }} &middot;
                                <span class="truncate font-mono text-[11px]">{{
                                    p.sku
                                }}</span>
                            </div>
                        </div>
                        <div class="flex min-w-29 flex-col items-end gap-1">
                            <div
                                class="inline-flex min-w-18 items-center justify-center rounded-full border border-amber-500/25 bg-amber-500/10 px-2.5 py-0.5 font-mono text-xs leading-5 font-semibold text-amber-700 dark:text-amber-300"
                            >
                                {{ p.on_hand_qty ?? 0 }}
                                <span
                                    class="mx-1 font-sans font-normal text-amber-700/55 dark:text-amber-300/55"
                                    >/</span
                                >
                                {{ p.reorder_threshold ?? 0 }}
                            </div>
                            <div
                                class="text-[9px] font-semibold tracking-[0.16em] whitespace-nowrap text-muted-foreground/70 uppercase"
                            >
                                on hand / threshold
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div
            v-if="isAdmin"
            class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/20 dark:text-rose-300"
                    >
                        <AlertOctagon class="h-4 w-4" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Unserviceable / Condemned assets
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Assets needing repair, disposal, or review
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="unserviceableAssets.length === 0"
                class="rounded-2xl border border-dashed border-emerald-500/20 bg-emerald-500/5 px-5 py-8 text-center text-sm text-muted-foreground"
            >
                No assets in these statuses.
            </div>
            <ul v-else class="space-y-2 text-sm">
                <li
                    v-for="a in unserviceableAssets"
                    :key="a.id"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-rose-500/15 bg-background/50 p-3 transition-colors hover:border-rose-500/30 hover:bg-rose-500/5"
                >
                    <span class="font-medium">{{ a.name ?? 'Asset' }}</span>
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                            :class="summaryToneClass(a.status)"
                            >{{ a.status }}</span
                        >
                        <span
                            class="font-mono text-[11px] text-muted-foreground"
                            >{{ a.tag_code }}</span
                        >
                    </div>
                </li>
            </ul>
        </div>

        <ForecastWidget v-if="canViewForecasting" :summary="forecastSummary" />

        <!-- Recently Deleted Widget -->
        <RecentlyDeletedPanel
            v-if="isAdmin && recentlyDeleted.length > 0"
            :items="recentlyDeleted"
        />

        <SystemAlertsPanel v-if="isAdmin" :alerts="alerts" />
    </div>
</template>
