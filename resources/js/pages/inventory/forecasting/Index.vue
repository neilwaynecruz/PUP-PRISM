<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Gauge,
    PackageSearch,
    Search,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ForecastWidget from '@/components/inventory/ForecastWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    index as forecastingIndex,
    show as forecastingShow,
} from '@/routes/inventory/forecasting';

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
}>();

defineOptions({
    name: 'InventoryForecastingIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: forecastingIndex() },
            { title: 'Forecasting', href: forecastingIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const urgency = ref(props.filters.urgency ?? '');
const method = ref(props.filters.method ?? '');
const minConfidence = ref(
    props.filters.min_confidence !== null
        ? String(props.filters.min_confidence)
        : '',
);

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
</script>

<template>
    <Head title="Forecasting" />

    <div
        class="flex flex-col gap-6 bg-[radial-gradient(circle_at_top_right,rgba(139,92,246,0.11),transparent_32%),radial-gradient(circle_at_15%_0%,rgba(20,184,166,0.08),transparent_28%)] p-4 sm:p-6"
    >
        <div
            class="rounded-3xl border border-border/60 bg-card/80 p-5 shadow-sm backdrop-blur"
        >
            <div
                class="mb-3 inline-flex items-center gap-2 rounded-full border border-violet-500/20 bg-violet-500/10 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-violet-700 uppercase dark:text-violet-300"
            >
                <Gauge class="h-3.5 w-3.5" />
                Forecast command deck
            </div>
            <Heading
                variant="small"
                title="Demand forecasting"
                description="Review consumable forecasts, tune model settings, and prioritize replenishment risks."
            />
        </div>

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
                        <option value="stable">Stable (&gt; 14 days)</option>
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
                            product.snapshot?.predicted_days_until_stockout ??
                                null,
                        )
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ product.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ product.sku }}
                            </div>
                        </div>
                        <span
                            class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                            :class="
                                product.snapshot
                                    ?.predicted_days_until_stockout !== null &&
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
                                    ?.predicted_days_until_stockout !== null &&
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
                                    product.snapshot?.recommended_reorder_qty ??
                                    '—'
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
                                }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between gap-3 text-xs text-muted-foreground"
                    >
                        <span>
                            Method:
                            {{
                                methodLabel(
                                    product.snapshot?.forecast_method ??
                                        product.profile_method,
                                )
                            }}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="rounded-xl border border-violet-500/20 bg-violet-500/10 text-violet-700 hover:bg-violet-500/15 dark:text-violet-300"
                        >
                            <Link
                                :href="forecastingShow(product.id)"
                                class="gap-1"
                            >
                                View
                                <ArrowUpRight class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead
                        class="border-b border-blue-500/15 bg-blue-500/10 text-left text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3 font-semibold">Product</th>
                            <th class="px-5 py-3 font-semibold">On hand</th>
                            <th class="px-5 py-3 font-semibold">
                                Daily demand
                            </th>
                            <th class="px-5 py-3 font-semibold">
                                Stockout horizon
                            </th>
                            <th class="px-5 py-3 font-semibold">Reorder qty</th>
                            <th class="px-5 py-3 font-semibold">Confidence</th>
                            <th class="px-5 py-3 font-semibold">Method</th>
                            <th class="px-5 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="border-b border-border/30 transition-colors"
                            :class="
                                riskRowClass(
                                    product.snapshot
                                        ?.predicted_days_until_stockout ?? null,
                                )
                            "
                        >
                            <td class="px-5 py-4">
                                <div class="font-medium">
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
                                            ?.predicted_days_until_stockout ??
                                        'No date'
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
                                            product.snapshot?.confidence_score,
                                        )
                                    "
                                >
                                    {{
                                        product.snapshot?.confidence_score?.toFixed(
                                            0,
                                        ) ?? '—'
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full border border-violet-500/20 bg-violet-500/10 px-2.5 py-1 text-xs font-medium text-violet-700 dark:text-violet-300"
                                >
                                    {{
                                        methodLabel(
                                            product.snapshot?.forecast_method ??
                                                product.profile_method,
                                        )
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="rounded-xl border border-violet-500/20 bg-violet-500/10 text-violet-700 hover:bg-violet-500/15 dark:text-violet-300"
                                >
                                    <Link
                                        :href="forecastingShow(product.id)"
                                        class="gap-1"
                                    >
                                        View
                                        <ArrowUpRight class="h-3.5 w-3.5" />
                                    </Link>
                                </Button>
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

            <div
                v-if="products.data.length === 0"
                class="px-5 py-10 text-center text-muted-foreground md:hidden"
            >
                No consumable forecasts match the current filters.
            </div>

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
</template>
