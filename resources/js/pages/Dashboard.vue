<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import {
    Activity,
    AlertOctagon,
    AlertTriangle,
    Calendar,
    ClipboardList,
    Clock3,
    Download,
    FileText,
    PackageCheck,
    RotateCcw,
    ShoppingCart,
    Sparkles,
    TrendingUp,
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
import Heading from '@/components/Heading.vue';
import ForecastWidget from '@/components/inventory/ForecastWidget.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
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

function applyDateRange(): void {
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
        ? 'border-blue-400/50 bg-blue-500/15 text-blue-700 shadow-sm shadow-blue-500/10 dark:text-blue-200'
        : 'border-border/70 bg-background/70 text-muted-foreground hover:border-blue-400/40 hover:bg-blue-500/10 hover:text-foreground';
}

function kpiCardClass(
    tone: 'blue' | 'amber' | 'rose' | 'purple' | 'cyan',
): string {
    const tones = {
        blue: 'border-blue-500/25 from-blue-500/15 via-blue-500/5 hover:border-blue-400/45 hover:shadow-blue-950/30',
        amber: 'border-amber-500/25 from-amber-500/15 via-amber-500/5 hover:border-amber-400/45 hover:shadow-amber-950/30',
        rose: 'border-rose-500/25 from-rose-500/15 via-rose-500/5 hover:border-rose-400/45 hover:shadow-rose-950/30',
        purple: 'border-violet-500/25 from-violet-500/15 via-violet-500/5 hover:border-violet-400/45 hover:shadow-violet-950/30',
        cyan: 'border-cyan-500/25 from-cyan-500/15 via-cyan-500/5 hover:border-cyan-400/45 hover:shadow-cyan-950/30',
    };

    return `group relative overflow-hidden rounded-2xl border bg-linear-to-br ${tones[tone]} to-card p-5 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl`;
}

function iconBubbleClass(
    tone: 'blue' | 'amber' | 'rose' | 'purple' | 'cyan',
): string {
    const tones = {
        blue: 'bg-blue-500/15 text-blue-600 ring-blue-500/20 dark:text-blue-300',
        amber: 'bg-amber-500/15 text-amber-600 ring-amber-500/20 dark:text-amber-300',
        rose: 'bg-rose-500/15 text-rose-600 ring-rose-500/20 dark:text-rose-300',
        purple: 'bg-violet-500/15 text-violet-600 ring-violet-500/20 dark:text-violet-300',
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

function deletedBadgeClass(type: string): string {
    const value = type.toLowerCase();

    if (value === 'product') {
        return 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }

    if (value === 'booking') {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (value === 'requisition') {
        return 'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300';
    }

    return 'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300';
}

function alertBadgeClass(type: string): string {
    const value = type.toLowerCase();

    if (value.includes('expiry') || value.includes('expired')) {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (value.includes('stock') || value.includes('low')) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (value.includes('forecast')) {
        return 'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300';
    }

    return 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300';
}

function restoreItem(url: string): void {
    router.put(url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Dashboard" />

    <div
        data-testid="dashboard-page"
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.10),transparent_34%),radial-gradient(circle_at_18%_0%,rgba(20,184,166,0.08),transparent_28%)] p-4 sm:p-6"
    >
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border/60 bg-card/80 p-5 shadow-sm backdrop-blur sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div
                    class="mb-3 inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-blue-700 uppercase dark:text-blue-300"
                >
                    <Activity class="h-3.5 w-3.5" />
                    Live command center
                </div>
                <Heading
                    variant="small"
                    title="Dashboard"
                    description="Overview, risk signals, and decision support for inventory operations."
                />
            </div>

            <div v-if="props.exportUrls" class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-xl border-blue-500/25 bg-blue-500/10 text-blue-700 shadow-sm transition-all hover:border-blue-400/50 hover:bg-blue-500/15 dark:text-blue-300"
                >
                    <a
                        :href="props.exportUrls.assetConditionsCsv"
                        class="gap-2"
                    >
                        <Download class="h-3.5 w-3.5" />
                        Asset report CSV
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-xl border-rose-500/25 bg-rose-500/10 text-rose-700 shadow-sm transition-all hover:border-rose-400/50 hover:bg-rose-500/15 dark:text-rose-300"
                >
                    <a
                        :href="props.exportUrls.assetConditionsPdf"
                        class="gap-2"
                    >
                        <Download class="h-3.5 w-3.5" />
                        Asset report PDF
                    </a>
                </Button>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div
            v-if="canUseDateRange"
            class="flex flex-col gap-4 rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm backdrop-blur xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 rounded-xl px-3 text-xs transition-all"
                    :class="presetButtonClass('today')"
                    @click="setPreset('today')"
                    >Today</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 rounded-xl px-3 text-xs transition-all"
                    :class="presetButtonClass('week')"
                    @click="setPreset('week')"
                    >This week</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 rounded-xl px-3 text-xs transition-all"
                    :class="presetButtonClass('month')"
                    @click="setPreset('month')"
                    >This month</Button
                >
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-model="fromDate"
                    type="date"
                    class="h-10 rounded-xl border border-border/70 bg-background/80 px-3 text-xs text-foreground shadow-inner shadow-black/5 transition-colors hover:border-blue-400/40 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
                />
                <span class="text-xs font-medium text-muted-foreground"
                    >to</span
                >
                <input
                    v-model="toDate"
                    type="date"
                    class="h-10 rounded-xl border border-border/70 bg-background/80 px-3 text-xs text-foreground shadow-inner shadow-black/5 transition-colors hover:border-blue-400/40 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
                />
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    class="h-10 rounded-xl bg-blue-600 px-5 text-xs font-semibold shadow-lg shadow-blue-950/20 transition-all hover:bg-blue-500"
                    @click="applyDateRange"
                    >Apply</Button
                >
            </div>
        </div>

        <!-- KPI Stats Row -->
        <div
            v-if="isAdmin"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
        >
            <!-- 1. Issued Today -->
            <div :class="kpiCardClass('blue')">
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-blue-500/15 blur-2xl transition-opacity group-hover:opacity-80"
                />
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
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-amber-500/15 blur-2xl"
                />
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
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-rose-500/15 blur-2xl"
                />
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
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-violet-500/15 blur-2xl"
                />
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-violet-700 uppercase dark:text-violet-300"
                        >Pending Reqs</span
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
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-cyan-500/15 blur-2xl"
                />
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
                    Awaiting allocation
                </div>
            </div>

            <!-- 6. Critical Alerts -->
            <div :class="kpiCardClass('rose')">
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-pink-500/15 blur-2xl"
                />
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-rose-700 uppercase dark:text-rose-300"
                        >System Alerts</span
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
                    Attention required
                </div>
            </div>
        </div>

        <!-- PRISM Intelligence Panel -->
        <div
            v-if="isAdmin"
            class="relative overflow-hidden rounded-3xl border border-blue-500/25 bg-linear-to-br from-blue-950/20 via-card to-violet-950/20 p-5 shadow-xl shadow-blue-950/10"
        >
            <div
                class="pointer-events-none absolute -top-20 -right-16 h-56 w-56 rounded-full bg-blue-500/20 blur-3xl"
            />
            <div
                class="pointer-events-none absolute bottom-0 left-1/3 h-32 w-32 rounded-full bg-violet-500/15 blur-3xl"
            />
            <div
                class="relative mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/20"
                    >
                        <Sparkles class="h-5 w-5" />
                    </div>
                    <div>
                        <h3
                            class="flex items-center gap-2 text-base font-semibold text-foreground"
                        >
                            PRISM Intelligence
                            <span
                                class="inline-flex items-center rounded-full border border-violet-500/25 bg-violet-500/10 px-2.5 py-0.5 text-[10px] font-semibold tracking-[0.18em] text-violet-700 uppercase dark:text-violet-300"
                                >Decision support</span
                            >
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Smart suggestions compiled from thresholds, workflow
                            queues, and inventory risk signals.
                        </p>
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-blue-500/20 bg-blue-500/10 px-4 py-2 text-xs text-blue-700 dark:text-blue-200"
                >
                    Updated from live operational data
                </div>
            </div>

            <div class="relative grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Observation 1: Low Stock -->
                <div
                    class="group rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber-400/40 hover:bg-amber-500/15"
                >
                    <div class="flex gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-300"
                        >
                            <AlertTriangle class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-foreground">
                                Reorder Consumables
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                <span class="font-medium text-foreground">{{
                                    lowStock.length > 0
                                        ? lowStock[0].name
                                        : 'No low-stock items'
                                }}</span>
                                {{
                                    lowStock.length > 0
                                        ? ' is running below reorder threshold.'
                                        : ' detected right now.'
                                }}
                            </p>
                            <Link
                                href="/inventory/products"
                                class="mt-3 inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-500/25 dark:text-amber-200"
                            >
                                Adjust Stock
                                <TrendingUp class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Observation 2: Near Expiry -->
                <div
                    class="group rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-rose-400/40 hover:bg-rose-500/15"
                >
                    <div class="flex gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/20 dark:text-rose-300"
                        >
                            <Calendar class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-foreground">
                                Near-Expiry Warning
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                <span class="font-medium text-foreground"
                                    >{{
                                        kpiSummary.near_expiry_batch_count ?? 0
                                    }}
                                    batch(es)</span
                                >
                                {{
                                    (kpiSummary.near_expiry_batch_count ?? 0) >
                                    0
                                        ? ' expire within 30 days.'
                                        : ' are expiring within 30 days.'
                                }}
                            </p>
                            <Link
                                href="/inventory/handover"
                                class="mt-3 inline-flex items-center gap-1 rounded-full bg-rose-500/15 px-3 py-1 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-500/25 dark:text-rose-200"
                            >
                                Initiate Handover
                                <Clock3 class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Observation 3: Pending Approvals -->
                <div
                    class="group rounded-2xl border border-blue-500/20 bg-blue-500/10 p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-blue-400/40 hover:bg-blue-500/15"
                >
                    <div class="flex gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 ring-1 ring-blue-500/20 dark:text-blue-300"
                        >
                            <Activity class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-foreground">
                                Pending Action Items
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                You have
                                <span class="font-medium text-foreground"
                                    >{{
                                        pendingRequisitionsCount
                                    }}
                                    requisitions</span
                                >
                                and
                                <span class="font-medium text-foreground"
                                    >{{ pendingBookingsCount }} bookings</span
                                >
                                awaiting review.
                            </p>
                            <Link
                                href="/inventory/requisitions"
                                class="mt-3 inline-flex items-center gap-1 rounded-full bg-blue-500/15 px-3 py-1 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-500/25 dark:text-blue-200"
                            >
                                Approve Requisitions
                                <ClipboardList class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                            entry.key
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
                            entry.key
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
                class="overflow-hidden rounded-2xl border border-emerald-500/20 bg-linear-to-br from-emerald-500/10 via-card to-card p-5 shadow-sm transition-all hover:border-emerald-400/35 hover:shadow-lg hover:shadow-emerald-950/10"
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
                    No receiving activity in selected range.
                </div>
                <div v-else class="h-48">
                    <canvas ref="receivingCanvas" />
                </div>
            </div>

            <div
                class="overflow-hidden rounded-2xl border border-blue-500/20 bg-linear-to-br from-blue-500/10 via-card to-card p-5 shadow-sm transition-all hover:border-blue-400/35 hover:shadow-lg hover:shadow-blue-950/10"
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
                    No issuing activity in selected range.
                </div>
                <div v-else class="h-48">
                    <canvas ref="issuingCanvas" />
                </div>
            </div>
        </div>

        <!-- Summaries -->
        <div v-if="isAdmin" class="grid gap-6 lg:grid-cols-3">
            <div
                class="rounded-2xl border border-violet-500/20 bg-linear-to-br from-violet-500/10 via-card to-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-500/15 text-violet-600 ring-1 ring-violet-500/20 dark:text-violet-300"
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
                    No data.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="entry in summaryEntries(requisitionSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-violet-500/25 hover:bg-violet-500/5"
                    >
                        <span class="font-medium text-foreground capitalize">{{
                            entry.key
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
                class="rounded-2xl border border-amber-500/20 bg-linear-to-br from-amber-500/10 via-card to-card p-5 shadow-sm"
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
                    No data.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="entry in summaryEntries(bookingSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-amber-500/25 hover:bg-amber-500/5"
                    >
                        <span class="font-medium text-foreground capitalize">{{
                            entry.key
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
                class="rounded-2xl border border-rose-500/20 bg-linear-to-br from-rose-500/10 via-card to-card p-5 shadow-sm"
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
                    No data.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="entry in summaryEntries(assetConditionSummary)"
                        :key="entry.key"
                        class="flex items-center justify-between rounded-xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-rose-500/25 hover:bg-rose-500/5"
                    >
                        <span class="font-medium text-foreground capitalize">{{
                            entry.key
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
                class="rounded-2xl border border-blue-500/20 bg-linear-to-br from-blue-500/10 via-card to-card p-5 shadow-sm"
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
                class="rounded-2xl border border-emerald-500/20 bg-linear-to-br from-emerald-500/10 via-card to-card p-5 shadow-sm"
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
                                {{ supplier.total_pos }} PO(s)
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
                                    supplier.avg_lead_time_days !== null
                                        ? `${supplier.avg_lead_time_days} day(s)`
                                        : '—'
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
                class="rounded-2xl border border-rose-500/20 bg-linear-to-br from-rose-500/10 via-card to-card p-5 shadow-sm"
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
                    <canvas ref="assetStatusCanvas" />
                </div>
            </div>

            <div
                class="rounded-2xl border border-amber-500/20 bg-linear-to-br from-amber-500/10 via-card to-card p-4 shadow-sm"
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
                        <div
                            class="flex min-w-[7.25rem] flex-col items-end gap-1"
                        >
                            <div
                                class="inline-flex min-w-[4.5rem] items-center justify-center rounded-full border border-amber-500/25 bg-amber-500/10 px-2.5 py-0.5 font-mono text-xs leading-5 font-semibold text-amber-700 dark:text-amber-300"
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
            class="rounded-2xl border border-rose-500/20 bg-linear-to-br from-rose-500/10 via-card to-card p-5 shadow-sm"
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
        <div
            v-if="isAdmin && recentlyDeleted.length > 0"
            class="rounded-2xl border border-slate-500/20 bg-linear-to-br from-slate-500/10 via-card to-card p-5 shadow-sm"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-500/15 text-slate-600 ring-1 ring-slate-500/20 dark:text-slate-300"
                    >
                        <RotateCcw class="h-4 w-4" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Recently Deleted
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Recoverable inventory records
                        </div>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="rounded-xl border border-slate-500/20 bg-slate-500/10 text-slate-700 hover:bg-slate-500/15 dark:text-slate-200"
                >
                    <Link href="/inventory/trash">View Trash</Link>
                </Button>
            </div>
            <ul class="space-y-2 text-sm">
                <li
                    v-for="item in recentlyDeleted"
                    :key="`${item.type}-${item.id}`"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-border/40 bg-background/50 p-3 transition-colors hover:border-slate-500/25 hover:bg-slate-500/5"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase"
                            :class="deletedBadgeClass(item.type)"
                        >
                            {{ item.type }}
                        </span>
                        <div>
                            <div class="font-medium">{{ item.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                <template
                                    v-if="
                                        item.deleted_by &&
                                        item.deleted_by !== 'Unknown'
                                    "
                                >
                                    Deleted by {{ item.deleted_by }} ·
                                </template>
                                {{ item.deleted_at }}
                            </div>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-xs font-semibold text-emerald-700 hover:bg-emerald-500/15 dark:text-emerald-300"
                        @click="restoreItem(item.restore_url)"
                    >
                        Restore
                    </Button>
                </li>
            </ul>
        </div>

        <div
            v-if="isAdmin"
            class="rounded-2xl border border-rose-500/20 bg-linear-to-br from-rose-500/10 via-card to-card p-5 shadow-sm"
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
                            System Alerts
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Active operational warnings
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="alerts.length === 0"
                class="rounded-2xl border border-dashed border-emerald-500/20 bg-emerald-500/5 px-5 py-8 text-center text-sm text-muted-foreground"
            >
                No active alerts.
            </div>
            <ul v-else class="space-y-3 text-sm">
                <li
                    v-for="a in alerts"
                    :key="a.id"
                    class="rounded-2xl border border-rose-500/15 bg-background/50 p-4 transition-colors hover:border-rose-500/30 hover:bg-rose-500/5"
                >
                    <div
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase"
                            :class="alertBadgeClass(a.type)"
                        >
                            {{ a.type }}
                        </span>
                        <span>{{ a.detected_at }}</span>
                    </div>
                    <div class="mt-1.5 leading-relaxed">{{ a.message }}</div>
                </li>
            </ul>
        </div>
    </div>
</template>
