<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowUpRight,
    BrainCircuit,
    Calendar,
    Clock,
    Eye,
    Gauge,
    PackageSearch,
    Radar,
    ShieldCheck,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { show as productsShow } from '@/routes/inventory/products';
import { create as purchaseOrdersCreate } from '@/routes/inventory/purchase-orders';

type ForecastItem = {
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
};

const props = defineProps<{
    summary: {
        forecast_date: string | null;
        last_generated_at: string | null;
        urgent_count: number;
        at_risk_count: number;
        average_confidence: number | null;
        items: ForecastItem[];
    };
}>();

const activeFilter = ref<'all' | 'urgent' | 'at_risk'>('all');

const urgentItems = computed(() =>
    props.summary.items.filter(
        (item) =>
            item.predicted_days_until_stockout !== null &&
            item.predicted_days_until_stockout <= 7,
    ),
);

const atRiskItems = computed(() =>
    props.summary.items.filter(
        (item) =>
            item.predicted_days_until_stockout !== null &&
            item.predicted_days_until_stockout > 7 &&
            item.predicted_days_until_stockout <= 14,
    ),
);

const filteredItems = computed(() => {
    if (activeFilter.value === 'urgent') {
        return urgentItems.value;
    }

    if (activeFilter.value === 'at_risk') {
        return atRiskItems.value;
    }

    return props.summary.items;
});

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    return new Date(value).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Pending first run';
    }

    return new Date(value).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function bufferPercentage(onHand: number, reorderPoint: number): number {
    if (reorderPoint <= 0) {
        return onHand > 0 ? 100 : 0;
    }

    return Math.min(
        100,
        Math.max(0, Math.round((onHand / reorderPoint) * 100)),
    );
}

function bufferBarColor(days: number | null): string {
    if (days === null) {
        return 'bg-slate-400 dark:bg-slate-500';
    }

    if (days <= 7) {
        return 'bg-rose-500';
    }

    if (days <= 14) {
        return 'bg-amber-500';
    }

    return 'bg-emerald-500';
}

function confidenceTier(score: number | null): {
    label: string;
    colorClass: string;
    badgeClass: string;
} {
    if (score === null || score === undefined) {
        return {
            label: 'Baseline',
            colorClass: 'text-muted-foreground',
            badgeClass: 'border-border/60 bg-muted/40 text-muted-foreground',
        };
    }

    if (score >= 80) {
        return {
            label: 'High Confidence',
            colorClass: 'text-emerald-700 dark:text-emerald-300',
            badgeClass:
                'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        };
    }

    if (score >= 60) {
        return {
            label: 'Moderate',
            colorClass: 'text-sky-700 dark:text-sky-300',
            badgeClass:
                'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        };
    }

    return {
        label: 'Learning',
        colorClass: 'text-amber-700 dark:text-amber-300',
        badgeClass:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    };
}
</script>

<template>
    <section
        class="relative overflow-hidden rounded-3xl border border-border/80 bg-card shadow-sm transition-shadow duration-300 hover:shadow-md"
        aria-labelledby="demand-intelligence-title"
    >
        <!-- Subtle Atmospheric Ambient Glow -->
        <div
            class="pointer-events-none absolute -top-24 -left-20 h-64 w-64 rounded-full bg-primary/8 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -right-20 -bottom-24 h-64 w-64 rounded-full bg-amber-500/8 blur-3xl"
            aria-hidden="true"
        />

        <!-- Section Header Bar -->
        <header
            class="relative border-b border-border/60 bg-muted/20 px-5 py-5 sm:px-6"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="space-y-1">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3 py-1 text-xs font-semibold tracking-wider text-primary uppercase"
                    >
                        <Radar class="animate-spin-slow size-3.5" />
                        <span>Predictive Intelligence</span>
                        <span
                            class="inline-block size-1.5 rounded-full bg-emerald-500"
                            aria-hidden="true"
                        />
                    </div>
                    <h2
                        id="demand-intelligence-title"
                        class="font-display text-2xl font-bold tracking-tight text-foreground"
                    >
                        Demand and Stockout Intelligence
                    </h2>
                    <p class="max-w-2xl text-sm text-muted-foreground">
                        AI forecasting monitors consumption velocity and
                        prioritizes inventory items projected to deplete before
                        scheduled procurement cycles.
                    </p>
                </div>

                <!-- Snapshot Meta Chips -->
                <div
                    class="flex flex-wrap items-center gap-2 text-xs sm:self-start lg:self-center"
                >
                    <div
                        class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background/80 px-3 py-2 text-muted-foreground shadow-2xs backdrop-blur-xs"
                    >
                        <Calendar class="size-3.5 text-primary" />
                        <div>
                            <span class="font-medium text-muted-foreground">
                                Forecast Date:
                            </span>
                            <span class="font-semibold text-foreground">
                                {{ formatDate(summary.forecast_date) }}
                            </span>
                        </div>
                    </div>
                    <div
                        class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background/80 px-3 py-2 text-muted-foreground shadow-2xs backdrop-blur-xs"
                    >
                        <Clock class="size-3.5 text-primary" />
                        <div>
                            <span class="font-medium text-muted-foreground">
                                Generated:
                            </span>
                            <span class="font-semibold text-foreground">
                                {{ formatDateTime(summary.last_generated_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- KPI Summary Cards Grid -->
        <div
            class="relative grid gap-3 border-b border-border/60 p-5 sm:px-6 sm:py-5 md:grid-cols-3"
        >
            <!-- Urgent Metric (< 7 Days) -->
            <div
                class="group relative overflow-hidden rounded-2xl border p-4.5 transition-all duration-200"
                :class="
                    summary.urgent_count > 0
                        ? 'border-rose-500/35 bg-rose-500/8 hover:border-rose-500/50 hover:bg-rose-500/12'
                        : 'border-border/70 bg-muted/15 hover:border-border'
                "
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-bold tracking-widest uppercase"
                        :class="
                            summary.urgent_count > 0
                                ? 'text-rose-700 dark:text-rose-300'
                                : 'text-muted-foreground'
                        "
                    >
                        Urgent Depletion
                    </span>
                    <div
                        class="flex size-9 items-center justify-center rounded-xl"
                        :class="
                            summary.urgent_count > 0
                                ? 'bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/30 dark:text-rose-300'
                                : 'bg-muted text-muted-foreground ring-1 ring-border'
                        "
                    >
                        <AlertTriangle class="size-4" />
                    </div>
                </div>
                <div
                    class="mt-2 font-display text-3xl font-extrabold tracking-tight"
                    :class="
                        summary.urgent_count > 0
                            ? 'text-rose-700 dark:text-rose-300'
                            : 'text-foreground'
                    "
                >
                    {{ summary.urgent_count }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Projected to stock out within
                    <strong class="font-semibold text-foreground">7 days</strong
                    >. Immediate PO creation recommended.
                </p>
            </div>

            <!-- At Risk Metric (8–14 Days) -->
            <div
                class="group relative overflow-hidden rounded-2xl border p-4.5 transition-all duration-200"
                :class="
                    summary.at_risk_count > 0
                        ? 'border-amber-500/35 bg-amber-500/8 hover:border-amber-500/50 hover:bg-amber-500/12'
                        : 'border-border/70 bg-muted/15 hover:border-border'
                "
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-bold tracking-widest uppercase"
                        :class="
                            summary.at_risk_count > 0
                                ? 'text-amber-800 dark:text-amber-200'
                                : 'text-muted-foreground'
                        "
                    >
                        At Risk Horizon
                    </span>
                    <div
                        class="flex size-9 items-center justify-center rounded-xl"
                        :class="
                            summary.at_risk_count > 0
                                ? 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/30 dark:text-amber-300'
                                : 'bg-muted text-muted-foreground ring-1 ring-border'
                        "
                    >
                        <TrendingUp class="size-4" />
                    </div>
                </div>
                <div
                    class="mt-2 font-display text-3xl font-extrabold tracking-tight"
                    :class="
                        summary.at_risk_count > 0
                            ? 'text-amber-800 dark:text-amber-200'
                            : 'text-foreground'
                    "
                >
                    {{ summary.at_risk_count }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Projected stockout within
                    <strong class="font-semibold text-foreground"
                        >8 to 14 days</strong
                    >. Schedule replenishment.
                </p>
            </div>

            <!-- Model Reliability / Confidence -->
            <div
                class="group relative overflow-hidden rounded-2xl border border-sky-500/30 bg-sky-500/8 p-4.5 transition-all duration-200 hover:border-sky-500/45 hover:bg-sky-500/12"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-bold tracking-widest text-sky-800 uppercase dark:text-sky-200"
                    >
                        Engine Confidence
                    </span>
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-sky-500/15 text-sky-600 ring-1 ring-sky-500/30 dark:text-sky-300"
                    >
                        <Gauge class="size-4" />
                    </div>
                </div>
                <div
                    class="mt-2 flex items-baseline gap-2 font-display text-3xl font-extrabold tracking-tight text-sky-800 dark:text-sky-200"
                >
                    <span
                        >{{
                            summary.average_confidence?.toFixed(0) ?? '0'
                        }}%</span
                    >
                    <span class="text-xs font-semibold text-muted-foreground">
                        {{ confidenceTier(summary.average_confidence).label }}
                    </span>
                </div>
                <div
                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-sky-500/20"
                >
                    <div
                        class="h-full rounded-full bg-sky-500 transition-all duration-500"
                        :style="{
                            width: `${Math.min(100, Math.max(0, summary.average_confidence ?? 0))}%`,
                        }"
                    />
                </div>
                <p class="mt-1.5 text-xs text-muted-foreground">
                    Aggregated predictive accuracy across active consumables.
                </p>
            </div>
        </div>

        <!-- High-Alert Notification Banner (when urgent items exist) -->
        <div
            v-if="summary.urgent_count > 0"
            role="alert"
            class="flex flex-col gap-2 border-b border-rose-500/25 bg-rose-500/10 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <div
                class="flex items-center gap-2.5 text-sm text-rose-800 dark:text-rose-200"
            >
                <AlertCircle
                    class="size-4.5 shrink-0 text-rose-600 dark:text-rose-400"
                />
                <span>
                    <strong class="font-semibold"
                        >{{ summary.urgent_count }} item{{
                            summary.urgent_count === 1 ? '' : 's'
                        }}</strong
                    >
                    require immediate attention. Critical stockout estimated
                    within 7 days.
                </span>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 underline underline-offset-4 hover:text-rose-900 dark:text-rose-300 dark:hover:text-rose-100"
                @click="activeFilter = 'urgent'"
            >
                <span>Filter urgent queue</span>
                <ArrowUpRight class="size-3.5" />
            </button>
        </div>

        <!-- Priority Reorder Queue Section -->
        <div class="p-5 sm:p-6">
            <!-- Queue Controls & Filtering Bar -->
            <div
                class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-8 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                    >
                        <BrainCircuit class="size-4" />
                    </div>
                    <div>
                        <h3
                            class="text-sm font-bold tracking-tight text-foreground"
                        >
                            Priority Reorder Queue
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Sorted by impending stockout vulnerability
                        </p>
                    </div>
                </div>

                <!-- Filter Segmented Tabs -->
                <div
                    v-if="summary.items.length > 0"
                    class="inline-flex items-center rounded-xl border border-border/70 bg-muted/30 p-1 text-xs"
                    role="tablist"
                    aria-label="Filter reorder queue"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeFilter === 'all'"
                        class="rounded-lg px-2.5 py-1 font-medium transition-all"
                        :class="
                            activeFilter === 'all'
                                ? 'bg-background font-semibold text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activeFilter = 'all'"
                    >
                        All ({{ summary.items.length }})
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeFilter === 'urgent'"
                        class="rounded-lg px-2.5 py-1 font-medium transition-all"
                        :class="
                            activeFilter === 'urgent'
                                ? 'bg-rose-500/15 font-semibold text-rose-700 shadow-2xs dark:text-rose-300'
                                : 'text-muted-foreground hover:text-rose-600 dark:hover:text-rose-300'
                        "
                        @click="activeFilter = 'urgent'"
                    >
                        Urgent ({{ urgentItems.length }})
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeFilter === 'at_risk'"
                        class="rounded-lg px-2.5 py-1 font-medium transition-all"
                        :class="
                            activeFilter === 'at_risk'
                                ? 'bg-amber-500/15 font-semibold text-amber-800 shadow-2xs dark:text-amber-200'
                                : 'text-muted-foreground hover:text-amber-700 dark:hover:text-amber-300'
                        "
                        @click="activeFilter = 'at_risk'"
                    >
                        At Risk ({{ atRiskItems.length }})
                    </button>
                </div>
            </div>

            <!-- Zero State: Optimal Inventory Health -->
            <div
                v-if="summary.items.length === 0"
                class="rounded-2xl border border-dashed border-emerald-500/30 bg-emerald-500/5 px-6 py-12 text-center"
            >
                <div
                    class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/25 dark:text-emerald-300"
                >
                    <ShieldCheck class="size-6" />
                </div>
                <h4 class="font-display text-base font-bold text-foreground">
                    All Consumable Stock Levels are Healthy
                </h4>
                <p
                    class="mx-auto mt-1.5 max-w-md text-sm text-muted-foreground"
                >
                    The predictive engine calculates safe buffer margins across
                    all tracked items. When velocity surges or replenishment is
                    needed, items appear here automatically.
                </p>
            </div>

            <!-- Filtered Empty State -->
            <div
                v-else-if="filteredItems.length === 0"
                class="rounded-2xl border border-dashed border-border px-6 py-8 text-center"
            >
                <PackageSearch
                    class="mx-auto mb-2 size-8 text-muted-foreground"
                />
                <p class="text-sm font-semibold text-foreground">
                    No items match the selected filter
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    There are currently no items under the
                    <strong class="font-medium text-foreground">{{
                        activeFilter
                    }}</strong>
                    classification.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-3 text-xs"
                    @click="activeFilter = 'all'"
                >
                    View All Items
                </Button>
            </div>

            <!-- Item Cards Grid -->
            <div v-else class="grid gap-3.5">
                <article
                    v-for="item in filteredItems"
                    :key="item.product_id"
                    class="group relative rounded-2xl border p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                    :class="
                        item.predicted_days_until_stockout !== null &&
                        item.predicted_days_until_stockout <= 7
                            ? 'border-rose-500/30 bg-card hover:border-rose-500/50'
                            : item.predicted_days_until_stockout !== null &&
                                item.predicted_days_until_stockout <= 14
                              ? 'border-amber-500/30 bg-card hover:border-amber-500/50'
                              : 'border-border/80 bg-card hover:border-border'
                    "
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <!-- Left Details Column -->
                        <div class="space-y-3 lg:max-w-2xl">
                            <!-- Title & Status Badges -->
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-bold text-foreground">
                                    {{ item.product_name }}
                                </h4>
                                <span
                                    class="inline-flex rounded-md border border-border/70 bg-muted/50 px-2 py-0.5 font-mono text-[11px] font-semibold text-foreground"
                                >
                                    {{ item.sku }}
                                </span>
                                <!-- Stockout Horizon Badge -->
                                <span
                                    v-if="
                                        item.predicted_days_until_stockout !==
                                        null
                                    "
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-bold tracking-wide uppercase"
                                    :class="
                                        item.predicted_days_until_stockout <= 7
                                            ? 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                                            : 'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-200'
                                    "
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            item.predicted_days_until_stockout <=
                                            7
                                                ? 'animate-pulse bg-rose-500'
                                                : 'bg-amber-500'
                                        "
                                        aria-hidden="true"
                                    />
                                    <span>
                                        {{
                                            item.predicted_days_until_stockout <=
                                            0
                                                ? 'Stockout Imminent'
                                                : `Depletes in ~${item.predicted_days_until_stockout} day${item.predicted_days_until_stockout === 1 ? '' : 's'}`
                                        }}
                                    </span>
                                </span>
                            </div>

                            <!-- Inventory Metrics Row -->
                            <div
                                class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4"
                            >
                                <div
                                    class="rounded-xl border border-border/50 bg-muted/20 p-2.5"
                                >
                                    <span
                                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        On Hand
                                    </span>
                                    <div
                                        class="mt-0.5 font-display text-base font-bold text-foreground"
                                    >
                                        {{ item.current_on_hand_qty }}
                                    </div>
                                    <div
                                        class="mt-1 h-1 w-full overflow-hidden rounded-full bg-border/60"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all duration-300"
                                            :class="
                                                bufferBarColor(
                                                    item.predicted_days_until_stockout,
                                                )
                                            "
                                            :style="{
                                                width: `${bufferPercentage(item.current_on_hand_qty, item.reorder_point_qty)}%`,
                                            }"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="rounded-xl border border-border/50 bg-muted/20 p-2.5"
                                >
                                    <span
                                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Reorder Point
                                    </span>
                                    <div
                                        class="mt-0.5 font-display text-base font-bold text-foreground"
                                    >
                                        {{ item.reorder_point_qty }}
                                    </div>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Safety threshold
                                    </span>
                                </div>

                                <div
                                    class="rounded-xl border border-border/50 bg-muted/20 p-2.5"
                                >
                                    <span
                                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Daily Demand
                                    </span>
                                    <div
                                        class="mt-0.5 font-display text-base font-bold text-foreground"
                                    >
                                        {{
                                            item.predicted_daily_consumption.toFixed(
                                                2,
                                            )
                                        }}
                                    </div>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        units / day
                                    </span>
                                </div>

                                <div
                                    class="rounded-xl border border-border/50 bg-muted/20 p-2.5"
                                >
                                    <span
                                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Model Score
                                    </span>
                                    <div class="mt-0.5">
                                        <span
                                            class="inline-flex rounded-full border px-2 py-0.5 font-mono text-[11px] font-semibold"
                                            :class="
                                                confidenceTier(
                                                    item.confidence_score,
                                                ).badgeClass
                                            "
                                        >
                                            {{
                                                item.confidence_score?.toFixed(
                                                    0,
                                                ) ?? '0'
                                            }}%
                                        </span>
                                    </div>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{
                                            confidenceTier(
                                                item.confidence_score,
                                            ).label
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Recommendation & Action Column -->
                        <div
                            class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-muted/20 p-4 lg:w-64 lg:shrink-0"
                        >
                            <div>
                                <div
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Recommended Reorder
                                </div>
                                <div class="mt-0.5 flex items-baseline gap-1">
                                    <span
                                        class="font-display text-2xl font-extrabold text-foreground"
                                    >
                                        {{ item.recommended_reorder_qty }}
                                    </span>
                                    <span
                                        class="text-xs font-semibold text-muted-foreground"
                                        >units</span
                                    >
                                </div>
                                <p
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    {{
                                        item.predicted_stockout_date
                                            ? `Est. stockout ${formatDate(item.predicted_stockout_date)}`
                                            : 'No definite stockout date'
                                    }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <Button
                                    v-if="item.recommended_reorder_qty > 0"
                                    size="sm"
                                    as-child
                                    class="h-9 flex-1 gap-1.5 rounded-xl font-semibold shadow-xs"
                                >
                                    <Link
                                        :href="
                                            purchaseOrdersCreate({
                                                query: {
                                                    product_id: item.product_id,
                                                    qty: item.recommended_reorder_qty,
                                                },
                                            })
                                        "
                                    >
                                        <span>Create PO</span>
                                        <ArrowUpRight class="size-3.5" />
                                    </Link>
                                </Button>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    class="h-9 rounded-xl text-xs"
                                >
                                    <Link :href="productsShow(item.product_id)">
                                        <Eye class="mr-1 size-3.5" />
                                        <span>View</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
