<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    BrainCircuit,
    Gauge,
    PackageSearch,
    Radar,
    TrendingUp,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { show as productsShow } from '@/routes/inventory/products';

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

defineProps<{
    summary: {
        forecast_date: string | null;
        last_generated_at: string | null;
        urgent_count: number;
        at_risk_count: number;
        average_confidence: number | null;
        items: ForecastItem[];
    };
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not scheduled yet';
    }

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Waiting for first run';
    }

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function riskTone(days: number | null): string {
    if (days === null) {
        return 'border-slate-500/25 bg-slate-500/10 text-slate-600 dark:text-slate-300';
    }

    if (days <= 7) {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (days <= 14) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

function riskCardClass(days: number | null): string {
    if (days === null) {
        return 'border-slate-500/20 bg-slate-500/5 hover:border-slate-400/35';
    }

    if (days <= 7) {
        return 'border-rose-500/25 bg-rose-500/10 hover:border-rose-400/45';
    }

    if (days <= 14) {
        return 'border-amber-500/25 bg-amber-500/10 hover:border-amber-400/45';
    }

    return 'border-emerald-500/25 bg-emerald-500/10 hover:border-emerald-400/45';
}

function confidenceTone(score: number | null): string {
    if (score === null) {
        return 'border-slate-500/25 bg-slate-500/10 text-slate-600 dark:text-slate-300';
    }

    if (score >= 80) {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (score >= 60) {
        return 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300';
    }

    return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
}
</script>

<template>
    <section
        class="relative overflow-hidden rounded-3xl border border-violet-500/25 bg-linear-to-br from-violet-950/25 via-card to-blue-950/20 shadow-xl shadow-violet-950/10"
    >
        <div
            class="pointer-events-none absolute -top-24 -left-20 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"
        />
        <div
            class="pointer-events-none absolute right-12 -bottom-24 h-64 w-64 rounded-full bg-cyan-500/15 blur-3xl"
        />
        <div class="relative border-b border-violet-500/15 px-5 py-5 sm:px-6">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-xs font-semibold tracking-[0.24em] text-violet-700 uppercase dark:text-violet-300"
                    >
                        <Radar class="h-3.5 w-3.5" />
                        Predictive intelligence
                    </div>
                    <h2 class="mt-2 font-display text-2xl font-semibold">
                        Demand and stockout intelligence
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Forecasts refresh nightly and rank the consumables most
                        likely to run short before the next replenishment cycle.
                    </p>
                </div>

                <div
                    class="grid gap-2 text-sm text-muted-foreground sm:grid-cols-2"
                >
                    <div
                        class="rounded-2xl border border-border/50 bg-background/50 px-4 py-3"
                    >
                        <div
                            class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            Forecast date
                        </div>
                        <div class="mt-1 font-medium text-foreground">
                            {{ formatDate(summary.forecast_date) }}
                        </div>
                    </div>
                    <div
                        class="rounded-2xl border border-border/50 bg-background/50 px-4 py-3"
                    >
                        <div
                            class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            Last generated
                        </div>
                        <div class="mt-1 font-medium text-foreground">
                            {{ formatDateTime(summary.last_generated_at) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="relative grid gap-3 border-b border-violet-500/15 px-5 py-5 sm:px-6 md:grid-cols-3"
        >
            <div
                class="rounded-2xl border border-rose-500/25 bg-linear-to-br from-rose-500/15 via-card to-card p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div
                        class="text-[11px] font-semibold tracking-[0.22em] text-rose-700 uppercase dark:text-rose-300"
                    >
                        Urgent
                    </div>
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 ring-1 ring-rose-500/20 dark:text-rose-300"
                    >
                        <AlertTriangle class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-2 font-display text-3xl font-semibold">
                    {{ summary.urgent_count }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Predicted to stock out within 7 days
                </div>
            </div>

            <div
                class="rounded-2xl border border-amber-500/25 bg-linear-to-br from-amber-500/15 via-card to-card p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div
                        class="text-[11px] font-semibold tracking-[0.22em] text-amber-700 uppercase dark:text-amber-300"
                    >
                        At Risk
                    </div>
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-300"
                    >
                        <TrendingUp class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-2 font-display text-3xl font-semibold">
                    {{ summary.at_risk_count }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Need reordering attention in 14 days
                </div>
            </div>

            <div
                class="rounded-2xl border border-cyan-500/25 bg-linear-to-br from-cyan-500/15 via-card to-card p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div
                        class="text-[11px] font-semibold tracking-[0.22em] text-cyan-700 uppercase dark:text-cyan-300"
                    >
                        Confidence
                    </div>
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-500/15 text-cyan-600 ring-1 ring-cyan-500/20 dark:text-cyan-300"
                    >
                        <Gauge class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-2 font-display text-3xl font-semibold">
                    {{ summary.average_confidence?.toFixed(0) ?? '0' }}%
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Average model confidence across active forecasts
                </div>
            </div>
        </div>

        <div class="relative px-5 py-5 sm:px-6">
            <div
                class="mb-4 flex flex-col gap-2 border-b border-border/30 pb-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-500/15 text-violet-600 ring-1 ring-violet-500/20 dark:text-violet-300"
                    >
                        <BrainCircuit class="h-4 w-4" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-tight">
                            Priority reorder queue
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Ranked by projected stockout risk
                        </div>
                    </div>
                </div>
                <div class="text-xs text-muted-foreground">
                    Based on the latest forecast snapshot
                </div>
            </div>

            <div
                v-if="summary.items.length === 0"
                class="rounded-2xl border border-dashed border-emerald-500/25 bg-emerald-500/5 px-5 py-8 text-center"
            >
                <PackageSearch class="mx-auto mb-3 h-8 w-8 text-emerald-500" />
                <div class="font-medium">
                    No near-term stockout risks detected.
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    Once the forecast engine sees a replenishment risk, the most
                    urgent items will appear here automatically.
                </p>
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-for="item in summary.items"
                    :key="item.product_id"
                    class="rounded-2xl border p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:bg-background/80 hover:shadow-lg"
                    :class="riskCardClass(item.predicted_days_until_stockout)"
                >
                    <div
                        class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
                    >
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold">
                                    {{ item.product_name }}
                                </h3>
                                <span
                                    class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase"
                                    :class="
                                        riskTone(
                                            item.predicted_days_until_stockout,
                                        )
                                    "
                                >
                                    {{
                                        item.predicted_days_until_stockout ===
                                        null
                                            ? 'No stockout date'
                                            : `${item.predicted_days_until_stockout} day horizon`
                                    }}
                                </span>
                                <span
                                    class="rounded-full border border-blue-500/20 bg-blue-500/10 px-2.5 py-0.5 text-[11px] font-medium text-blue-700 dark:text-blue-300"
                                >
                                    {{ item.sku }}
                                </span>
                            </div>

                            <div
                                class="grid gap-2 text-sm text-muted-foreground md:grid-cols-2 xl:grid-cols-4"
                            >
                                <div>
                                    <div class="text-[11px] uppercase">
                                        On hand
                                    </div>
                                    <div class="font-semibold text-foreground">
                                        {{ item.current_on_hand_qty }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase">
                                        Reorder point
                                    </div>
                                    <div class="font-semibold text-foreground">
                                        {{ item.reorder_point_qty }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase">
                                        Daily demand
                                    </div>
                                    <div class="font-semibold text-foreground">
                                        {{
                                            item.predicted_daily_consumption.toFixed(
                                                2,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase">
                                        Confidence
                                    </div>
                                    <div
                                        class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            confidenceTone(
                                                item.confidence_score,
                                            )
                                        "
                                    >
                                        {{
                                            item.confidence_score?.toFixed(0) ??
                                            '0'
                                        }}%
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 xl:flex-col xl:items-end"
                        >
                            <div class="text-right">
                                <div
                                    class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                >
                                    Recommended reorder
                                </div>
                                <div
                                    class="mt-1 font-display text-2xl font-semibold"
                                >
                                    {{ item.recommended_reorder_qty }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        item.predicted_stockout_date
                                            ? `Projected stockout ${formatDate(item.predicted_stockout_date)}`
                                            : 'No stockout date projected'
                                    }}
                                </div>
                            </div>

                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                class="h-9 rounded-xl border border-violet-500/20 bg-violet-500/10 text-violet-700 hover:bg-violet-500/15 dark:text-violet-300"
                            >
                                <Link :href="productsShow(item.product_id)">
                                    View product
                                    <ArrowUpRight class="ml-1 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
