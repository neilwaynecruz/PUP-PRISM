<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Boxes,
    CalendarClock,
    CheckCircle2,
    FileSpreadsheet,
    Gauge,
    PackageSearch,
    Search,
    SlidersHorizontal,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import {
    expirations as reportExpirations,
    forecasting as reportForecasting,
    optimization as reportOptimization,
} from '@/actions/App/Http/Controllers/Inventory/InventoryReportController';
import Heading from '@/components/Heading.vue';
import ForecastWidget from '@/components/inventory/ForecastWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    index as forecastingIndex,
    show as forecastingShow,
} from '@/routes/inventory/forecasting';
import { create as purchaseOrdersCreate } from '@/routes/inventory/purchase-orders';

type ForecastSnapshotRow = {
    forecast_method: string;
    current_on_hand_qty: number;
    predicted_daily_consumption: number;
    predicted_days_until_stockout: number | null;
    predicted_stockout_date: string | null;
    recommended_reorder_qty: number;
    confidence_score: number | null;
    forecast_date: string | null;
};

type ProductRow = {
    id: number;
    sku: string;
    name: string;
    on_hand_qty: number;
    profile_method: string | null;
    snapshot: ForecastSnapshotRow | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

type MethodOption = {
    value: string;
    label: string;
};

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

type AbcItem = {
    product_id: number;
    product_name: string;
    sku: string;
    unit_price: number;
    annual_consumption: number;
    annual_value: number;
    cumulative_value: number;
    cumulative_percentage: number;
    classification: string;
};

type AbcSummary = {
    total_products: number;
    total_annual_value: number;
    class_a_count: number;
    class_b_count: number;
    class_c_count: number;
    class_a_value: number;
    class_b_value: number;
    class_c_value: number;
    class_a_percentage: number;
    class_b_percentage: number;
    class_c_percentage: number;
};

type ExpiringLotRow = {
    id: number;
    reference_no: string;
    product_id: number;
    sku: string;
    product_name: string;
    qty_remaining: number;
    received_at: string;
    expires_at: string;
    days_until_expiry: number;
    status: string;
};

const props = defineProps<{
    filters: {
        search: string;
        urgency: string;
        method: string;
        min_confidence: number | null;
    };
    forecastSummary: {
        forecast_date: string | null;
        last_generated_at: string | null;
        urgent_count: number;
        at_risk_count: number;
        average_confidence: number | null;
        items: ForecastItem[];
    };
    methodOptions: MethodOption[];
    products: Paginated<ProductRow>;
    optimization?: {
        items: AbcItem[];
        summary: AbcSummary;
    } | null;
    expiringLots?: ExpiringLotRow[] | null;
}>();

defineOptions({
    name: 'InventoryForecastingIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: forecastingIndex() },
            { title: 'Forecasting & Optimization', href: forecastingIndex() },
        ],
    },
});

const activeTab = ref<'forecast' | 'optimization' | 'expirations'>('forecast');

// Forecast filters
const search = ref(props.filters.search ?? '');
const urgency = ref(props.filters.urgency ?? '');
const method = ref(props.filters.method ?? '');
const minConfidence = ref(
    props.filters.min_confidence !== null
        ? String(props.filters.min_confidence)
        : '',
);

// Optimization filters
const optimizationSearch = ref('');
const optimizationClass = ref('');

// Expiration filters
const expiringSearch = ref('');
const expiringStatus = ref('');

let refreshTimer: number | undefined;

watch([search, urgency, method, minConfidence], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            forecastingIndex().url,
            {
                search: search.value || undefined,
                urgency: urgency.value || undefined,
                method: method.value || undefined,
                min_confidence:
                    minConfidence.value === ''
                        ? undefined
                        : Number(minConfidence.value),
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
    window.clearTimeout(refreshTimer);
});

function methodLabel(value: string | null | undefined): string {
    return (
        props.methodOptions.find((option) => option.value === value)?.label ??
        value ??
        'Default'
    );
}

function riskClass(days: number | null): string {
    if (days === null) {
        return 'border-slate-500/25 bg-slate-500/10 text-slate-600 dark:text-slate-300';
    }

    if (days <= 7) {
        return 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (days <= 14) {
        return 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

function riskRowClass(days: number | null): string {
    if (days === null) {
        return 'hover:bg-slate-500/5';
    }

    if (days <= 7) {
        return 'hover:bg-rose-500/5';
    }

    if (days <= 14) {
        return 'hover:bg-amber-500/5';
    }

    return 'hover:bg-emerald-500/5';
}

function confidenceClass(score: number | null | undefined): string {
    if (score === null || score === undefined) {
        return 'border-slate-500/25 bg-slate-500/10 text-slate-600 dark:text-slate-300';
    }

    if (score >= 80) {
        return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (score >= 60) {
        return 'border-blue-500/25 bg-blue-500/10 text-blue-700 dark:text-blue-300';
    }

    return 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300';
}

function abcBadgeClass(classification: string): string {
    if (classification === 'A') {
        return 'border-purple-500/30 bg-purple-500/15 text-purple-700 dark:text-purple-300';
    }

    if (classification === 'B') {
        return 'border-blue-500/30 bg-blue-500/15 text-blue-700 dark:text-blue-300';
    }

    return 'border-slate-500/30 bg-slate-500/15 text-slate-700 dark:text-slate-300';
}

function expiryTone(status: string): string {
    if (status === 'expired') {
        return 'border-rose-600/30 bg-rose-500/20 text-rose-800 dark:text-rose-200';
    }

    if (status === 'critical') {
        return 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (status === 'warning') {
        return 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

const filteredOptimizationItems = computed(() => {
    if (!props.optimization?.items) {
        return [];
    }

    return props.optimization.items.filter((item) => {
        const matchesSearch =
            !optimizationSearch.value ||
            item.sku
                .toLowerCase()
                .includes(optimizationSearch.value.toLowerCase()) ||
            item.product_name
                .toLowerCase()
                .includes(optimizationSearch.value.toLowerCase());

        const matchesClass =
            !optimizationClass.value ||
            item.classification === optimizationClass.value;

        return matchesSearch && matchesClass;
    });
});

const filteredExpiringLots = computed(() => {
    if (!props.expiringLots) {
        return [];
    }

    return props.expiringLots.filter((lot) => {
        const matchesSearch =
            !expiringSearch.value ||
            lot.reference_no
                .toLowerCase()
                .includes(expiringSearch.value.toLowerCase()) ||
            lot.sku
                .toLowerCase()
                .includes(expiringSearch.value.toLowerCase()) ||
            lot.product_name
                .toLowerCase()
                .includes(expiringSearch.value.toLowerCase());

        const matchesStatus =
            !expiringStatus.value || lot.status === expiringStatus.value;

        return matchesSearch && matchesStatus;
    });
});
</script>

<template>
    <Head title="Forecasting & Optimization" />

    <div
        class="flex flex-col gap-6 bg-[radial-gradient(circle_at_top_right,rgba(139,92,246,0.11),transparent_32%),radial-gradient(circle_at_15%_0%,rgba(20,184,166,0.08),transparent_28%)] p-4 sm:p-6"
    >
        <!-- Header Command Deck -->
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border/60 bg-card/80 p-5 shadow-sm backdrop-blur sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="mb-3 inline-flex items-center gap-2 rounded-full border border-violet-500/20 bg-violet-500/10 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-violet-700 uppercase dark:text-violet-300"
                >
                    <Gauge class="h-3.5 w-3.5" />
                    PRISM Intelligence Hub
                </div>
                <Heading
                    variant="small"
                    title="Forecasting & Inventory Optimization"
                    description="Predict stockouts, automate restock orders, analyze spend via ABC/EOQ, and monitor lot expirations under strict FEFO."
                />
            </div>

            <!-- Quick Export Dropdown / Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-xl text-xs"
                >
                    <a :href="reportForecasting.url('csv')">
                        <FileSpreadsheet class="h-3.5 w-3.5 text-emerald-600" />
                        Forecast CSV
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-xl text-xs"
                >
                    <a :href="reportOptimization.url('csv')">
                        <Boxes class="h-3.5 w-3.5 text-purple-600" />
                        Optimization CSV
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-xl text-xs"
                >
                    <a :href="reportExpirations.url('csv')">
                        <CalendarClock class="h-3.5 w-3.5 text-rose-600" />
                        Expirations CSV
                    </a>
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div
            class="grid grid-cols-1 gap-2 border-b border-border/40 pb-2 sm:grid-cols-3"
            role="tablist"
            aria-label="Forecasting views"
        >
            <button
                id="forecast-tab"
                type="button"
                role="tab"
                :aria-selected="activeTab === 'forecast'"
                aria-controls="forecast-panel"
                @click="activeTab = 'forecast'"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-center text-sm font-semibold transition-colors"
                :class="
                    activeTab === 'forecast'
                        ? 'bg-violet-600 text-white shadow-sm'
                        : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground'
                "
            >
                <TrendingUp class="h-4 w-4" />
                Demand Forecasting
                <span
                    class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        activeTab === 'forecast'
                            ? 'bg-white/20 text-white'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    {{ products.data.length }}
                </span>
            </button>

            <button
                id="optimization-tab"
                type="button"
                role="tab"
                :aria-selected="activeTab === 'optimization'"
                aria-controls="optimization-panel"
                @click="activeTab = 'optimization'"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-center text-sm font-semibold transition-colors"
                :class="
                    activeTab === 'optimization'
                        ? 'bg-violet-600 text-white shadow-sm'
                        : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground'
                "
            >
                <Boxes class="h-4 w-4" />
                Optimization & ABC Analysis
                <span
                    v-if="optimization?.summary"
                    class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        activeTab === 'optimization'
                            ? 'bg-white/20 text-white'
                            : 'bg-purple-500/10 text-purple-600 dark:text-purple-300'
                    "
                >
                    {{ optimization.summary.class_a_percentage }}% A-Value
                </span>
            </button>

            <button
                id="expirations-tab"
                type="button"
                role="tab"
                :aria-selected="activeTab === 'expirations'"
                aria-controls="expirations-panel"
                @click="activeTab = 'expirations'"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-center text-sm font-semibold transition-colors"
                :class="
                    activeTab === 'expirations'
                        ? 'bg-violet-600 text-white shadow-sm'
                        : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground'
                "
            >
                <CalendarClock class="h-4 w-4" />
                Expiration & FEFO Tracker
                <span
                    v-if="expiringLots"
                    class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        activeTab === 'expirations'
                            ? 'bg-white/20 text-white'
                            : 'bg-rose-500/10 text-rose-600 dark:text-rose-300'
                    "
                >
                    {{ expiringLots.length }}
                </span>
            </button>
        </div>

        <!-- ================= TAB 1: DEMAND FORECASTING ================= -->
        <div
            v-if="activeTab === 'forecast'"
            id="forecast-panel"
            class="flex flex-col gap-6"
            role="tabpanel"
            aria-labelledby="forecast-tab"
        >
            <ForecastWidget :summary="forecastSummary" />

            <section
                class="overflow-hidden rounded-3xl border border-blue-500/20 bg-card/90 shadow-xl shadow-blue-950/10"
            >
                <div
                    class="flex flex-col gap-4 border-b border-blue-500/15 bg-linear-to-r from-blue-500/10 via-violet-500/5 to-transparent px-5 py-5 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-600 ring-1 ring-blue-500/20 dark:text-blue-300"
                        >
                            <PackageSearch class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold tracking-tight">
                                Consumable forecast registry
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Filter by urgency, confidence, or forecasting
                                method.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="search"
                                placeholder="Search SKU or product name"
                                aria-label="Search forecasts"
                                class="h-11 rounded-xl border-border/70 bg-background/80 pl-9 shadow-inner shadow-black/5 focus-visible:ring-blue-500/30"
                            />
                        </div>
                        <select
                            v-model="urgency"
                            class="h-11 rounded-xl border border-border/70 bg-background/80 px-3 text-sm shadow-inner shadow-black/5 transition-colors hover:border-blue-400/40 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
                            aria-label="Urgency filter"
                        >
                            <option value="">All urgency levels</option>
                            <option value="urgent">Urgent (≤ 7 days)</option>
                            <option value="at_risk">At risk (8–14 days)</option>
                            <option value="stable">Stable (> 14 days)</option>
                            <option value="unknown">No stockout date</option>
                        </select>
                        <select
                            v-model="method"
                            class="h-11 rounded-xl border border-border/70 bg-background/80 px-3 text-sm shadow-inner shadow-black/5 transition-colors hover:border-violet-400/40 focus:border-violet-400 focus:ring-2 focus:ring-violet-500/20 focus:outline-none"
                            aria-label="Method filter"
                        >
                            <option value="">All methods</option>
                            <option
                                v-for="option in methodOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <Input
                            v-model="minConfidence"
                            type="number"
                            min="0"
                            max="100"
                            step="1"
                            placeholder="Min confidence %"
                            aria-label="Minimum confidence"
                            class="h-11 rounded-xl border-border/70 bg-background/80 shadow-inner shadow-black/5 focus-visible:ring-cyan-500/30"
                        />
                    </div>
                </div>

                <!-- Mobile Cards -->
                <div
                    v-if="products.data.length > 0"
                    class="grid gap-3 p-4 md:hidden"
                    data-testid="forecast-mobile-cards"
                >
                    <div
                        v-for="product in products.data"
                        :key="`mobile-${product.id}`"
                        class="rounded-2xl border border-border/60 bg-linear-to-br from-background/80 via-card to-card p-4 shadow-sm transition-colors"
                        :class="
                            riskRowClass(
                                product.snapshot
                                    ?.predicted_days_until_stockout ?? null,
                            )
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">
                                    {{ product.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ product.sku }}
                                </div>
                            </div>
                            <span
                                class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                :class="
                                    product.snapshot
                                        ?.predicted_days_until_stockout !==
                                        null &&
                                    product.snapshot
                                        ?.predicted_days_until_stockout !==
                                        undefined
                                        ? riskClass(
                                              product.snapshot
                                                  .predicted_days_until_stockout,
                                          )
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    product.snapshot
                                        ?.predicted_days_until_stockout !==
                                        null &&
                                    product.snapshot
                                        ?.predicted_days_until_stockout !==
                                        undefined
                                        ? `${product.snapshot.predicted_days_until_stockout} days`
                                        : 'No stockout date'
                                }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div
                                class="rounded-lg border border-border/50 bg-muted/20 p-3"
                            >
                                <div
                                    class="text-[11px] tracking-wider text-muted-foreground uppercase"
                                >
                                    On hand
                                </div>
                                <div class="mt-1 font-semibold">
                                    {{
                                        product.snapshot?.current_on_hand_qty ??
                                        product.on_hand_qty
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-border/50 bg-muted/20 p-3"
                            >
                                <div
                                    class="text-[11px] tracking-wider text-muted-foreground uppercase"
                                >
                                    Reorder qty
                                </div>
                                <div class="mt-1 font-semibold">
                                    {{
                                        product.snapshot
                                            ?.recommended_reorder_qty ?? '—'
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-border/50 bg-muted/20 p-3"
                            >
                                <div
                                    class="text-[11px] tracking-wider text-muted-foreground uppercase"
                                >
                                    Daily demand
                                </div>
                                <div class="mt-1 font-semibold">
                                    {{
                                        product.snapshot
                                            ? product.snapshot.predicted_daily_consumption.toFixed(
                                                  2,
                                              )
                                            : '—'
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-border/50 bg-muted/20 p-3"
                            >
                                <div
                                    class="text-[11px] tracking-wider text-muted-foreground uppercase"
                                >
                                    Confidence
                                </div>
                                <div
                                    class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold"
                                    :class="
                                        confidenceClass(
                                            product.snapshot?.confidence_score,
                                        )
                                    "
                                >
                                    {{
                                        product.snapshot?.confidence_score?.toFixed(
                                            0,
                                        ) ?? '—'
                                    }}%
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 flex items-center justify-between border-t border-border/40 pt-3"
                        >
                            <span class="text-xs text-muted-foreground">
                                {{
                                    methodLabel(
                                        product.snapshot?.forecast_method ??
                                            product.profile_method,
                                    )
                                }}
                            </span>
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="
                                        product.snapshot &&
                                        product.snapshot
                                            .recommended_reorder_qty > 0
                                    "
                                    size="sm"
                                    as-child
                                    class="h-8 rounded-lg bg-violet-600 px-2.5 text-xs font-semibold text-white hover:bg-violet-700"
                                >
                                    <Link
                                        :href="
                                            purchaseOrdersCreate({
                                                query: {
                                                    product_id: product.id,
                                                    qty: product.snapshot
                                                        .recommended_reorder_qty,
                                                },
                                            })
                                        "
                                    >
                                        Create PO
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="forecastingShow(product.id)">
                                        Details
                                        <ArrowUpRight
                                            class="ml-1 h-3.5 w-3.5"
                                        />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border/60 bg-muted/30 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3">On Hand</th>
                                <th class="px-5 py-3">Daily Demand</th>
                                <th class="px-5 py-3">Days Until Stockout</th>
                                <th class="px-5 py-3">Reorder Qty</th>
                                <th class="px-5 py-3">Confidence</th>
                                <th class="px-5 py-3">Method</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="product in products.data"
                                :key="`desktop-${product.id}`"
                                class="transition-colors"
                                :class="
                                    riskRowClass(
                                        product.snapshot
                                            ?.predicted_days_until_stockout ??
                                            null,
                                    )
                                "
                            >
                                <td class="px-5 py-4">
                                    <div class="font-medium text-foreground">
                                        {{ product.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ product.sku }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        product.snapshot?.current_on_hand_qty ??
                                        product.on_hand_qty
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        product.snapshot
                                            ? product.snapshot.predicted_daily_consumption.toFixed(
                                                  2,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            riskClass(
                                                product.snapshot
                                                    ?.predicted_days_until_stockout ??
                                                    null,
                                            )
                                        "
                                    >
                                        {{
                                            product.snapshot
                                                ?.predicted_days_until_stockout !==
                                                null &&
                                            product.snapshot
                                                ?.predicted_days_until_stockout !==
                                                undefined
                                                ? `${product.snapshot.predicted_days_until_stockout} days`
                                                : 'No date'
                                        }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 font-mono text-xs font-semibold"
                                        :class="
                                            product.snapshot
                                                ?.recommended_reorder_qty
                                                ? 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                                : 'border-slate-500/25 bg-slate-500/10 text-slate-600 dark:text-slate-300'
                                        "
                                    >
                                        {{
                                            product.snapshot
                                                ?.recommended_reorder_qty ?? '—'
                                        }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            confidenceClass(
                                                product.snapshot
                                                    ?.confidence_score,
                                            )
                                        "
                                    >
                                        {{
                                            product.snapshot?.confidence_score?.toFixed(
                                                0,
                                            ) ?? '—'
                                        }}%
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full border border-violet-500/20 bg-violet-500/10 px-2.5 py-1 text-xs font-medium text-violet-700 dark:text-violet-300"
                                    >
                                        {{
                                            methodLabel(
                                                product.snapshot
                                                    ?.forecast_method ??
                                                    product.profile_method,
                                            )
                                        }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            v-if="
                                                product.snapshot &&
                                                product.snapshot
                                                    .recommended_reorder_qty > 0
                                            "
                                            size="sm"
                                            as-child
                                            class="h-8 rounded-lg bg-violet-600 px-2.5 text-xs font-semibold text-white hover:bg-violet-700"
                                        >
                                            <Link
                                                :href="
                                                    purchaseOrdersCreate({
                                                        query: {
                                                            product_id:
                                                                product.id,
                                                            qty: product
                                                                .snapshot
                                                                .recommended_reorder_qty,
                                                        },
                                                    })
                                                "
                                            >
                                                Create PO
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                            class="h-8 rounded-xl border border-violet-500/20 bg-violet-500/10 text-violet-700 hover:bg-violet-500/15 dark:text-violet-300"
                                        >
                                            <Link
                                                :href="
                                                    forecastingShow(product.id)
                                                "
                                                class="gap-1"
                                            >
                                                View
                                                <ArrowUpRight
                                                    class="h-3.5 w-3.5"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="products.data.length === 0">
                                <td
                                    colspan="8"
                                    class="px-5 py-12 text-center text-muted-foreground"
                                >
                                    <SlidersHorizontal
                                        class="mx-auto mb-3 h-8 w-8 text-muted-foreground/70"
                                    />
                                    No consumable forecasts match the current
                                    filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="products.links.length > 3"
                    class="flex flex-wrap gap-2 border-t border-border/50 px-5 py-4"
                >
                    <Button
                        v-for="link in products.links"
                        :key="`${link.label}-${link.url}`"
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
            </section>
        </div>

        <!-- ================= TAB 2: INVENTORY OPTIMIZATION (ABC / EOQ) ================= -->
        <div
            v-else-if="activeTab === 'optimization'"
            id="optimization-panel"
            class="flex flex-col gap-6"
            role="tabpanel"
            aria-labelledby="optimization-tab"
        >
            <!-- ABC Overview Cards -->
            <div
                v-if="optimization?.summary"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    class="rounded-2xl border border-border/60 bg-card p-5 shadow-sm"
                >
                    <div
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Total Annual Consumption Spend
                    </div>
                    <div class="mt-2 font-display text-3xl font-bold">
                        ₱{{
                            optimization.summary.total_annual_value.toLocaleString()
                        }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Across {{ optimization.summary.total_products }} active
                        consumable items
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-purple-500/25 bg-linear-to-br from-purple-500/10 via-card to-card p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-semibold tracking-wider text-purple-700 uppercase dark:text-purple-300"
                        >
                            Class A (High Value)
                        </span>
                        <span
                            class="rounded-full bg-purple-500/20 px-2 py-0.5 text-xs font-bold text-purple-700 dark:text-purple-300"
                        >
                            {{ optimization.summary.class_a_percentage }}% Spend
                        </span>
                    </div>
                    <div
                        class="mt-2 font-display text-3xl font-bold text-purple-700 dark:text-purple-300"
                    >
                        {{ optimization.summary.class_a_count }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >items</span
                        >
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        ₱{{
                            optimization.summary.class_a_value.toLocaleString()
                        }}
                        · Strict daily/weekly review
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-blue-500/25 bg-linear-to-br from-blue-500/10 via-card to-card p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-semibold tracking-wider text-blue-700 uppercase dark:text-blue-300"
                        >
                            Class B (Moderate)
                        </span>
                        <span
                            class="rounded-full bg-blue-500/20 px-2 py-0.5 text-xs font-bold text-blue-700 dark:text-blue-300"
                        >
                            {{ optimization.summary.class_b_percentage }}% Spend
                        </span>
                    </div>
                    <div
                        class="mt-2 font-display text-3xl font-bold text-blue-700 dark:text-blue-300"
                    >
                        {{ optimization.summary.class_b_count }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >items</span
                        >
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        ₱{{
                            optimization.summary.class_b_value.toLocaleString()
                        }}
                        · Bi-weekly reorder check
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-slate-500/25 bg-linear-to-br from-slate-500/10 via-card to-card p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-semibold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                        >
                            Class C (Bulk / Low)
                        </span>
                        <span
                            class="rounded-full bg-slate-500/20 px-2 py-0.5 text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            {{ optimization.summary.class_c_percentage }}% Spend
                        </span>
                    </div>
                    <div class="mt-2 font-display text-3xl font-bold">
                        {{ optimization.summary.class_c_count }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >items</span
                        >
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        ₱{{
                            optimization.summary.class_c_value.toLocaleString()
                        }}
                        · Periodic bulk replenishment
                    </div>
                </div>
            </div>

            <!-- Optimization Table -->
            <section
                class="overflow-hidden rounded-3xl border border-purple-500/20 bg-card/90 shadow-xl shadow-purple-950/10"
            >
                <div
                    class="flex flex-col gap-4 border-b border-purple-500/15 bg-linear-to-r from-purple-500/10 via-blue-500/5 to-transparent px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-base font-semibold tracking-tight">
                            ABC Classification & Spend Ranking
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Ranked by annual issued consumption value to
                            optimize capital and storage.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="optimizationSearch"
                                placeholder="Filter SKU or name"
                                class="h-10 pl-9"
                            />
                        </div>
                        <select
                            v-model="optimizationClass"
                            class="h-10 rounded-xl border border-border/70 bg-background px-3 text-sm focus:outline-none"
                        >
                            <option value="">All ABC classes</option>
                            <option value="A">Class A only</option>
                            <option value="B">Class B only</option>
                            <option value="C">Class C only</option>
                        </select>
                    </div>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-3 p-4 md:hidden">
                    <div
                        v-for="item in filteredOptimizationItems"
                        :key="`abc-mobile-${item.product_id}`"
                        class="flex flex-col gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-medium text-foreground">
                                    {{ item.product_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.sku }}
                                </div>
                            </div>
                            <span
                                class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-bold"
                                :class="abcBadgeClass(item.classification)"
                            >
                                Class {{ item.classification }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Unit Price
                                </div>
                                <div
                                    class="mt-0.5 font-semibold text-foreground"
                                >
                                    ₱{{ item.unit_price.toFixed(2) }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Annual Spend
                                </div>
                                <div
                                    class="mt-0.5 font-semibold text-foreground"
                                >
                                    ₱{{ item.annual_value.toLocaleString() }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Annual Usage
                                </div>
                                <div
                                    class="mt-0.5 font-mono font-medium text-foreground"
                                >
                                    {{ item.annual_consumption }} units
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Cumulative %
                                </div>
                                <div class="mt-0.5 font-medium text-foreground">
                                    {{ item.cumulative_percentage }}%
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end pt-1">
                            <Button
                                variant="outline"
                                size="sm"
                                as-child
                                class="h-8 rounded-xl border-border/80 text-xs"
                            >
                                <Link :href="forecastingShow(item.product_id)">
                                    Optimize
                                    <ArrowUpRight class="ml-1 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="filteredOptimizationItems.length === 0"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No items match the optimization criteria.
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border/60 bg-muted/30 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Unit Price</th>
                                <th class="px-5 py-3">Annual Usage</th>
                                <th class="px-5 py-3">Annual Spend</th>
                                <th class="px-5 py-3">Cumulative %</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="item in filteredOptimizationItems"
                                :key="`abc-${item.product_id}`"
                                class="hover:bg-muted/20"
                            >
                                <td class="px-5 py-4">
                                    <div class="font-medium text-foreground">
                                        {{ item.product_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.sku }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold"
                                        :class="
                                            abcBadgeClass(item.classification)
                                        "
                                    >
                                        Class {{ item.classification }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    ₱{{ item.unit_price.toFixed(2) }}
                                </td>
                                <td class="px-5 py-4 font-mono">
                                    {{ item.annual_consumption }} units
                                </td>
                                <td class="px-5 py-4 font-semibold">
                                    ₱{{ item.annual_value.toLocaleString() }}
                                </td>
                                <td
                                    class="px-5 py-4 text-xs text-muted-foreground"
                                >
                                    {{ item.cumulative_percentage }}%
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                        class="h-8 rounded-xl border border-border/70 hover:bg-muted"
                                    >
                                        <Link
                                            :href="
                                                forecastingShow(item.product_id)
                                            "
                                        >
                                            Optimize
                                            <ArrowUpRight
                                                class="ml-1 h-3.5 w-3.5"
                                            />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="filteredOptimizationItems.length === 0">
                                <td
                                    colspan="7"
                                    class="px-5 py-10 text-center text-muted-foreground"
                                >
                                    No items match the optimization criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ================= TAB 3: EXPIRATION & FEFO TRACKER ================= -->
        <div
            v-else-if="activeTab === 'expirations'"
            id="expirations-panel"
            class="flex flex-col gap-6"
            role="tabpanel"
            aria-labelledby="expirations-tab"
        >
            <!-- FEFO Compliance Banner -->
            <div
                class="flex items-start gap-3 rounded-2xl border border-blue-500/25 bg-blue-500/10 p-4 text-sm text-blue-900 dark:text-blue-100"
            >
                <CheckCircle2
                    class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400"
                />
                <div>
                    <div class="font-semibold">
                        FEFO (First Expired, First Out) Enforcement Active
                    </div>
                    <div
                        class="mt-1 text-xs text-blue-800/80 dark:text-blue-200/80"
                    >
                        PRISM automatically allocates lots with earliest valid
                        expiration dates during requisition issuance. Expired
                        lots are automatically quarantined and prevented from
                        being issued.
                    </div>
                </div>
            </div>

            <section
                class="overflow-hidden rounded-3xl border border-rose-500/20 bg-card/90 shadow-xl shadow-rose-950/10"
            >
                <div
                    class="flex flex-col gap-4 border-b border-rose-500/15 bg-linear-to-r from-rose-500/10 via-amber-500/5 to-transparent px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-base font-semibold tracking-tight">
                            Stock Lot Expiration Tracker
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Active consumable lots approaching expiry. Monitor
                            aging and avoid supply waste.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="expiringSearch"
                                placeholder="Search lot or SKU"
                                class="h-10 pl-9"
                            />
                        </div>
                        <select
                            v-model="expiringStatus"
                            class="h-10 rounded-xl border border-border/70 bg-background px-3 text-sm focus:outline-none"
                        >
                            <option value="">All statuses</option>
                            <option value="expired">Expired only</option>
                            <option value="critical">
                                Critical (≤ 15 days)
                            </option>
                            <option value="warning">
                                Warning (16–30 days)
                            </option>
                            <option value="healthy">Healthy (> 30 days)</option>
                        </select>
                    </div>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-3 p-4 md:hidden">
                    <div
                        v-for="lot in filteredExpiringLots"
                        :key="`lot-mobile-${lot.id}`"
                        class="flex flex-col gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div
                                    class="font-mono text-xs font-semibold text-foreground"
                                >
                                    {{ lot.reference_no }}
                                </div>
                                <div
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{ lot.product_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ lot.sku }}
                                </div>
                            </div>
                            <span
                                class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-bold uppercase"
                                :class="expiryTone(lot.status)"
                            >
                                {{ lot.status }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Remaining Qty
                                </div>
                                <div
                                    class="mt-0.5 font-semibold text-foreground"
                                >
                                    {{ lot.qty_remaining }} units
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Days Left
                                </div>
                                <div
                                    class="mt-0.5 font-mono font-semibold"
                                    :class="
                                        lot.days_until_expiry < 0
                                            ? 'text-rose-600 dark:text-rose-400'
                                            : 'text-foreground'
                                    "
                                >
                                    {{
                                        lot.days_until_expiry < 0
                                            ? `Expired (${Math.abs(lot.days_until_expiry)}d ago)`
                                            : `${lot.days_until_expiry}d`
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Received Date
                                </div>
                                <div class="mt-0.5 text-foreground">
                                    {{ lot.received_at }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2.5"
                            >
                                <div class="text-muted-foreground">
                                    Expiration Date
                                </div>
                                <div class="mt-0.5 font-medium text-foreground">
                                    {{ lot.expires_at }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end pt-1">
                            <Button
                                variant="outline"
                                size="sm"
                                as-child
                                class="h-8 rounded-xl border-border/80 text-xs"
                            >
                                <Link :href="forecastingShow(lot.product_id)">
                                    View Forecast
                                    <ArrowUpRight class="ml-1 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="filteredExpiringLots.length === 0"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No expiring lots match the filter criteria.
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border/60 bg-muted/30 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-5 py-3">Lot Reference</th>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3">Remaining Qty</th>
                                <th class="px-5 py-3">Received Date</th>
                                <th class="px-5 py-3">Expiration Date</th>
                                <th class="px-5 py-3">Days Left</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="lot in filteredExpiringLots"
                                :key="`lot-${lot.id}`"
                                class="hover:bg-muted/20"
                            >
                                <td class="px-5 py-4 font-mono font-medium">
                                    {{ lot.reference_no }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-foreground">
                                        {{ lot.product_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ lot.sku }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-semibold">
                                    {{ lot.qty_remaining }} units
                                </td>
                                <td
                                    class="px-5 py-4 text-xs text-muted-foreground"
                                >
                                    {{ lot.received_at }}
                                </td>
                                <td class="px-5 py-4 font-medium">
                                    {{ lot.expires_at }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="font-mono text-xs font-semibold"
                                    >
                                        {{
                                            lot.days_until_expiry < 0
                                                ? `Passed (${Math.abs(lot.days_until_expiry)}d ago)`
                                                : `${lot.days_until_expiry} days`
                                        }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold uppercase"
                                        :class="expiryTone(lot.status)"
                                    >
                                        {{ lot.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                        class="h-8 rounded-xl border border-border/70 hover:bg-muted"
                                    >
                                        <Link
                                            :href="
                                                forecastingShow(lot.product_id)
                                            "
                                        >
                                            View
                                            <ArrowUpRight
                                                class="ml-1 h-3.5 w-3.5"
                                            />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="filteredExpiringLots.length === 0">
                                <td
                                    colspan="8"
                                    class="px-5 py-10 text-center text-muted-foreground"
                                >
                                    No stock lots currently match the expiration
                                    filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
