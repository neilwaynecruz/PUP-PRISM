<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowUpRight, Sparkles, TrendingUp } from 'lucide-vue-next';
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
        return 'border-border/50 bg-muted/50 text-muted-foreground';
    }

    if (days <= 7) {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (days <= 14) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm"
    >
        <div
            class="border-b border-border/50 bg-linear-to-r from-primary/10 via-primary/5 to-transparent px-5 py-4"
        >
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-xs font-semibold tracking-[0.24em] text-primary/80 uppercase"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        Predictive Forecasting
                    </div>
                    <h2 class="mt-2 font-display text-xl font-semibold">
                        Demand and stockout intelligence
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Forecasts refresh nightly and rank the consumables most
                        likely to run short before the next replenishment cycle.
                    </p>
                </div>

                <div class="text-sm text-muted-foreground">
                    <div>Forecast date: {{ formatDate(summary.forecast_date) }}</div>
                    <div>
                        Last generated:
                        {{ formatDateTime(summary.last_generated_at) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-3 border-b border-border/40 px-5 py-4 md:grid-cols-3">
            <div class="rounded-xl border border-border/50 bg-background/70 p-4">
                <div class="flex items-center justify-between">
                    <div
                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        Urgent
                    </div>
                    <AlertTriangle class="h-4 w-4 text-rose-500" />
                </div>
                <div class="mt-2 font-display text-3xl font-semibold">
                    {{ summary.urgent_count }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Predicted to stock out within 7 days
                </div>
            </div>

            <div class="rounded-xl border border-border/50 bg-background/70 p-4">
                <div class="flex items-center justify-between">
                    <div
                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        At Risk
                    </div>
                    <TrendingUp class="h-4 w-4 text-amber-500" />
                </div>
                <div class="mt-2 font-display text-3xl font-semibold">
                    {{ summary.at_risk_count }}
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Need reordering attention in 14 days
                </div>
            </div>

            <div class="rounded-xl border border-border/50 bg-background/70 p-4">
                <div class="flex items-center justify-between">
                    <div
                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        Confidence
                    </div>
                    <Sparkles class="h-4 w-4 text-primary" />
                </div>
                <div class="mt-2 font-display text-3xl font-semibold">
                    {{ summary.average_confidence?.toFixed(0) ?? '0' }}%
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    Average model confidence across active forecasts
                </div>
            </div>
        </div>

        <div class="px-5 py-4">
            <div
                class="mb-4 flex items-center justify-between gap-3 border-b border-border/30 pb-3"
            >
                <div class="text-sm font-semibold tracking-tight">
                    Priority reorder queue
                </div>
                <div class="text-xs text-muted-foreground">
                    Based on the latest forecast snapshot
                </div>
            </div>

            <div
                v-if="summary.items.length === 0"
                class="rounded-2xl border border-dashed border-border/60 bg-muted/30 px-5 py-8 text-center"
            >
                <div class="font-medium">No near-term stockout risks detected.</div>
                <p class="mt-2 text-sm text-muted-foreground">
                    Once the forecast engine sees a replenishment risk, the
                    most urgent items will appear here automatically.
                </p>
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-for="item in summary.items"
                    :key="item.product_id"
                    class="rounded-2xl border border-border/50 bg-background/80 p-4 transition-colors hover:border-primary/20 hover:bg-background"
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
                                    :class="riskTone(item.predicted_days_until_stockout)"
                                >
                                    {{
                                        item.predicted_days_until_stockout === null
                                            ? 'No stockout date'
                                            : `${item.predicted_days_until_stockout} day horizon`
                                    }}
                                </span>
                                <span
                                    class="rounded-full border border-border/60 bg-muted/50 px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                >
                                    {{ item.sku }}
                                </span>
                            </div>

                            <div
                                class="grid gap-2 text-sm text-muted-foreground md:grid-cols-2 xl:grid-cols-4"
                            >
                                <div>
                                    <div class="text-[11px] uppercase">On hand</div>
                                    <div class="font-semibold text-foreground">
                                        {{ item.current_on_hand_qty }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase">Reorder point</div>
                                    <div class="font-semibold text-foreground">
                                        {{ item.reorder_point_qty }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase">Daily demand</div>
                                    <div class="font-semibold text-foreground">
                                        {{ item.predicted_daily_consumption.toFixed(2) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase">Confidence</div>
                                    <div class="font-semibold text-foreground">
                                        {{ item.confidence_score?.toFixed(0) ?? '0' }}%
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 xl:flex-col xl:items-end">
                            <div class="text-right">
                                <div
                                    class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                                >
                                    Recommended reorder
                                </div>
                                <div class="mt-1 font-display text-2xl font-semibold">
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

                            <Button variant="ghost" size="sm" as-child class="h-8 rounded-lg">
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
