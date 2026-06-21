<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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

const props = defineProps<{
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
    assetConditionSummary: SummaryData;
    recentlyDeleted: RecentlyDeleted[];
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

const page = usePage();
const roles = computed<string[]>(() => page.props.auth.roles ?? []);
const isAdmin = computed(() => roles.value.includes('Admin'));

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
                            family: "'Outfit', sans-serif",
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
                        font: { family: "'Outfit', sans-serif", size: 11 },
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
                        font: { family: "'Outfit', sans-serif", size: 10 },
                        maxRotation: 45,
                    },
                    grid: { display: false },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: mutedColor,
                        font: { family: "'Outfit', sans-serif", size: 11 },
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

function restoreItem(url: string): void {
    router.put(url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Dashboard" />

    <div
        data-testid="dashboard-page"
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Dashboard"
                description="Overview and decision support."
            />

            <div v-if="props.exportUrls" class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <a :href="props.exportUrls.assetConditionsCsv">
                        <span
                            class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary"
                        />Asset report CSV
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <a :href="props.exportUrls.assetConditionsPdf">
                        <span
                            class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary"
                        />Asset report PDF
                    </a>
                </Button>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div
            v-if="isAdmin"
            class="flex flex-col gap-3 rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:flex-row sm:items-center"
        >
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="setPreset('today')"
                    >Today</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="setPreset('week')"
                    >This week</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="setPreset('month')"
                    >This month</Button
                >
            </div>
            <div class="flex items-center gap-2">
                <input
                    v-model="fromDate"
                    type="date"
                    class="h-8 rounded-lg border border-input bg-background px-2 text-xs transition-colors focus:border-ring focus:outline-none"
                />
                <span class="text-xs text-muted-foreground">to</span>
                <input
                    v-model="toDate"
                    type="date"
                    class="h-8 rounded-lg border border-input bg-background px-2 text-xs transition-colors focus:border-ring focus:outline-none"
                />
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="applyDateRange"
                    >Apply</Button
                >
            </div>
        </div>

        <!-- Recently Deleted Widget -->
        <div
            v-if="isAdmin && recentlyDeleted.length > 0"
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="text-sm font-semibold tracking-tight">
                        Recently Deleted
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-rose-500/60" />
                </div>
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/inventory/trash">View Trash</Link>
                </Button>
            </div>
            <ul class="space-y-2 text-sm">
                <li
                    v-for="item in recentlyDeleted"
                    :key="`${item.type}-${item.id}`"
                    class="flex items-center justify-between gap-3 rounded-lg border border-border/40 p-3 transition-colors hover:bg-muted/40"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase"
                            :class="{
                                'bg-sky-500/10 text-sky-700 dark:text-sky-400':
                                    item.type === 'product',
                                'bg-amber-500/10 text-amber-700 dark:text-amber-400':
                                    item.type === 'booking',
                                'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400':
                                    item.type === 'requisition',
                            }"
                        >
                            {{ item.type }}
                        </span>
                        <div>
                            <div class="font-medium">{{ item.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                Deleted by {{ item.deleted_by }} ·
                                {{ item.deleted_at }}
                            </div>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs"
                        @click="restoreItem(item.restore_url)"
                    >
                        Restore
                    </Button>
                </li>
            </ul>
        </div>

        <ForecastWidget
            v-if="canViewForecasting"
            :summary="forecastSummary"
        />

        <!-- Quick Stats Row -->
        <div v-if="isAdmin" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:border-primary/20 hover:shadow-md"
            >
                <div
                    class="absolute top-3 right-3 h-2 w-2 rounded-full bg-primary/40 transition-colors group-hover:bg-primary"
                />
                <div
                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                >
                    Assets
                </div>
                <div
                    class="mt-2 font-display text-2xl font-bold text-foreground"
                >
                    {{
                        props.assetStatusCounts.data.reduce((a, b) => a + b, 0)
                    }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Total tracked
                </div>
            </div>
            <div
                class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:border-primary/20 hover:shadow-md"
            >
                <div
                    class="absolute top-3 right-3 h-2 w-2 rounded-full bg-amber-500/40 transition-colors group-hover:bg-amber-500"
                />
                <div
                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                >
                    Low Stock
                </div>
                <div
                    class="mt-2 font-display text-2xl font-bold text-foreground"
                >
                    {{ lowStock.length }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Consumables below threshold
                </div>
            </div>
            <div
                class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:border-primary/20 hover:shadow-md"
            >
                <div
                    class="absolute top-3 right-3 h-2 w-2 rounded-full bg-rose-500/40 transition-colors group-hover:bg-rose-500"
                />
                <div
                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                >
                    Unserviceable
                </div>
                <div
                    class="mt-2 font-display text-2xl font-bold text-foreground"
                >
                    {{ unserviceableAssets.length }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Assets needing attention
                </div>
            </div>
            <div
                class="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:border-primary/20 hover:shadow-md"
            >
                <div
                    class="absolute top-3 right-3 h-2 w-2 rounded-full bg-emerald-500/40 transition-colors group-hover:bg-emerald-500"
                />
                <div
                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                >
                    Alerts
                </div>
                <div
                    class="mt-2 font-display text-2xl font-bold text-foreground"
                >
                    {{ alerts.length }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Active system alerts
                </div>
            </div>
        </div>

        <!-- Trends -->
        <div v-if="isAdmin" class="grid gap-6 lg:grid-cols-2">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">
                        Receiving trends
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-emerald-500/60" />
                </div>
                <div
                    v-if="
                        receivingTrends.data.length === 0 ||
                        receivingTrends.data.every((v) => v === 0)
                    "
                    class="text-sm text-muted-foreground"
                >
                    No receiving activity in selected range.
                </div>
                <div v-else class="h-48">
                    <canvas ref="receivingCanvas" />
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">
                        Issuing trends
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-sky-500/60" />
                </div>
                <div
                    v-if="
                        issuingTrends.data.length === 0 ||
                        issuingTrends.data.every((v) => v === 0)
                    "
                    class="text-sm text-muted-foreground"
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
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">
                        Requisition status
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-primary/60" />
                </div>
                <div
                    v-if="summaryEntries(requisitionSummary).length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No data.
                </div>
                <ul v-else class="space-y-2 text-sm">
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
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">
                        Booking status
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-amber-500/60" />
                </div>
                <div
                    v-if="summaryEntries(bookingSummary).length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No data.
                </div>
                <ul v-else class="space-y-2 text-sm">
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
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">
                        Asset conditions
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-rose-500/60" />
                </div>
                <div
                    v-if="summaryEntries(assetConditionSummary).length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No data.
                </div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="entry in summaryEntries(assetConditionSummary)"
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
            </div>
        </div>

        <!-- Existing widgets -->
        <div v-if="isAdmin" class="grid gap-6 lg:grid-cols-2">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">
                        Unserviceable / Condemned assets
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-primary/60" />
                </div>
                <div class="h-64">
                    <canvas ref="assetStatusCanvas" />
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">
                        Low-stock consumables
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full bg-amber-500/60" />
                </div>
                <div
                    v-if="lowStock.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No low-stock items found.
                </div>
                <ul v-else class="space-y-3 text-sm">
                    <li
                        v-for="p in lowStock"
                        :key="p.id"
                        class="flex items-center justify-between gap-3 rounded-lg border border-border/40 p-3 transition-colors hover:bg-muted/40"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ p.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ p.category ?? '—' }} &middot;
                                <span class="font-mono text-[11px]">{{
                                    p.sku
                                }}</span>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-mono text-xs font-semibold">
                                {{ p.on_hand_qty ?? 0 }}
                                <span
                                    class="font-sans font-normal text-muted-foreground"
                                    >/ {{ p.reorder_threshold ?? 0 }}</span
                                >
                            </div>
                            <div
                                class="text-[10px] tracking-wider text-muted-foreground/70 uppercase"
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
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="text-sm font-semibold tracking-tight">
                    Unserviceable / Condemned assets
                </div>
                <div class="h-1.5 w-1.5 rounded-full bg-rose-500/60" />
            </div>
            <div
                v-if="unserviceableAssets.length === 0"
                class="text-sm text-muted-foreground"
            >
                No assets in these statuses.
            </div>
            <ul v-else class="space-y-2 text-sm">
                <li
                    v-for="a in unserviceableAssets"
                    :key="a.id"
                    class="flex items-center justify-between gap-3 rounded-lg border border-border/40 p-3 transition-colors hover:bg-muted/40"
                >
                    <span class="font-medium">{{ a.name ?? 'Asset' }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ a.status }} &middot;
                        <span class="font-mono text-[11px]">{{
                            a.tag_code
                        }}</span>
                    </span>
                </li>
            </ul>
        </div>

        <div
            v-if="isAdmin"
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="text-sm font-semibold tracking-tight">
                    System Alerts
                </div>
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-500/60" />
            </div>
            <div
                v-if="alerts.length === 0"
                class="text-sm text-muted-foreground"
            >
                No active alerts.
            </div>
            <ul v-else class="space-y-3 text-sm">
                <li
                    v-for="a in alerts"
                    :key="a.id"
                    class="rounded-lg border border-border/40 bg-muted/20 p-4 transition-colors hover:bg-muted/40"
                >
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            class="inline-block h-1.5 w-1.5 rounded-full bg-primary/60"
                        />
                        {{ a.type }} &middot; {{ a.detected_at }}
                    </div>
                    <div class="mt-1.5 leading-relaxed">{{ a.message }}</div>
                </li>
            </ul>
        </div>
    </div>
</template>
