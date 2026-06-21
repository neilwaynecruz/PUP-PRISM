<script setup lang="ts">
import { BadgeAlert, Radar, ShieldCheck, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

type ForecastPoint = {
    date: string;
    qty: number;
};

type ProductForecast = {
    method: string;
    source: 'snapshot' | 'live';
    current_on_hand_qty: number;
    reorder_point_qty: number;
    predicted_daily_consumption: number;
    predicted_days_until_stockout: number | null;
    predicted_stockout_date: string | null;
    recommended_reorder_qty: number;
    confidence_score: number | null;
    generated_at: string;
    historical_daily: ForecastPoint[];
    forecast_daily: { date: string; predicted_qty: number }[];
    history_window_days: number;
    forecast_horizon_days: number;
    lead_time_days: number;
    safety_stock_days: number;
    has_sufficient_history: boolean;
};

const props = defineProps<{
    forecast: ProductForecast | null;
}>();

const chartWidth = 360;
const chartHeight = 144;
const chartPadding = 12;

const historicalPoints = computed(() =>
    props.forecast ? props.forecast.historical_daily.slice(-21) : [],
);

const projectedPoints = computed(() =>
    props.forecast ? props.forecast.forecast_daily.slice(0, 14) : [],
);

const chartSeries = computed(() => {
    const history = historicalPoints.value.map((point) => ({
        date: point.date,
        value: point.qty,
        type: 'history' as const,
    }));

    const forecast = projectedPoints.value.map((point) => ({
        date: point.date,
        value: point.predicted_qty,
        type: 'forecast' as const,
    }));

    return [...history, ...forecast];
});

const chartCoordinates = computed(() => {
    if (chartSeries.value.length === 0) {
        return [];
    }

    const values = chartSeries.value.map((point) => point.value);
    const maxValue = Math.max(1, ...values);
    const minValue = Math.min(0, ...values);
    const spread = Math.max(1, maxValue - minValue);
    const stepX =
        chartSeries.value.length > 1
            ? (chartWidth - chartPadding * 2) / (chartSeries.value.length - 1)
            : 0;

    return chartSeries.value.map((point, index) => {
        const x = chartPadding + index * stepX;
        const y =
            chartHeight -
            chartPadding -
            ((point.value - minValue) / spread) * (chartHeight - chartPadding * 2);

        return { ...point, x, y };
    });
});

const historyLine = computed(() =>
    chartCoordinates.value
        .filter((point) => point.type === 'history')
        .map((point) => `${point.x},${point.y}`)
        .join(' '),
);

const forecastLine = computed(() => {
    const coordinates = chartCoordinates.value;
    const forecastStart = coordinates.findIndex((point) => point.type === 'forecast');

    if (forecastStart === -1) {
        return '';
    }

    const points = coordinates.slice(Math.max(0, forecastStart - 1));

    return points.map((point) => `${point.x},${point.y}`).join(' ');
});

const forecastDividerX = computed(() => {
    const forecastStart = chartCoordinates.value.find((point) => point.type === 'forecast');

    return forecastStart ? forecastStart.x : null;
});

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not projected';
    }

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function methodLabel(method: string): string {
    const labels: Record<string, string> = {
        moving_average: 'Moving average',
        exponential_smoothing: 'Exponential smoothing',
        seasonal: 'Seasonal pattern',
    };

    return labels[method] ?? method;
}
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm"
    >
        <div
            class="border-b border-border/50 bg-linear-to-r from-sky-500/10 via-primary/8 to-transparent px-5 py-4"
        >
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-xs font-semibold tracking-[0.24em] text-primary/80 uppercase"
                    >
                        <Radar class="h-3.5 w-3.5" />
                        Demand Forecast
                    </div>
                    <h2 class="mt-2 font-display text-xl font-semibold">
                        Forward-looking consumption signal
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                        This panel translates recent issuance history into a
                        short-term stockout forecast and reorder target.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full border border-border/60 bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground"
                    >
                        {{ forecast?.source === 'live' ? 'Live preview' : 'Daily snapshot' }}
                    </span>
                    <span
                        class="rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                    >
                        {{ methodLabel(forecast?.method ?? '') }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="!forecast" class="px-5 py-8 text-sm text-muted-foreground">
            No forecast is available for this product yet.
        </div>

        <div v-else class="px-5 py-5">
            <div
                v-if="!forecast.has_sufficient_history"
                class="mb-5 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200"
            >
                The model is still warming up. Recent issuance history is thin,
                so treat this forecast as directional guidance rather than a
                final reorder decision.
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-border/50 bg-background/70 p-4">
                    <div
                        class="flex items-center justify-between text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        Avg daily demand
                        <Sparkles class="h-4 w-4 text-primary" />
                    </div>
                    <div class="mt-2 font-display text-3xl font-semibold">
                        {{ forecast.predicted_daily_consumption.toFixed(2) }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Based on {{ forecast.history_window_days }} days of history
                    </div>
                </div>

                <div class="rounded-2xl border border-border/50 bg-background/70 p-4">
                    <div
                        class="flex items-center justify-between text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        Stockout horizon
                        <BadgeAlert class="h-4 w-4 text-rose-500" />
                    </div>
                    <div class="mt-2 font-display text-3xl font-semibold">
                        {{
                            forecast.predicted_days_until_stockout === null
                                ? '--'
                                : forecast.predicted_days_until_stockout
                        }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{
                            forecast.predicted_stockout_date
                                ? `Projected around ${formatDate(forecast.predicted_stockout_date)}`
                                : 'No stockout date projected'
                        }}
                    </div>
                </div>

                <div class="rounded-2xl border border-border/50 bg-background/70 p-4">
                    <div
                        class="flex items-center justify-between text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        Suggested reorder
                        <ShieldCheck class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div class="mt-2 font-display text-3xl font-semibold">
                        {{ forecast.recommended_reorder_qty }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Covers lead time plus safety stock
                    </div>
                </div>

                <div class="rounded-2xl border border-border/50 bg-background/70 p-4">
                    <div
                        class="text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        Confidence
                    </div>
                    <div class="mt-2 font-display text-3xl font-semibold">
                        {{ forecast.confidence_score?.toFixed(0) ?? '0' }}%
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Generated {{ formatDateTime(forecast.generated_at) }}
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-4 xl:grid-cols-[1.25fr_0.75fr]">
                <div class="rounded-2xl border border-border/50 bg-background/70 p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Recent demand vs forecast
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Last 21 history points and next 14 forecast days
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-slate-400" />
                                History
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-primary" />
                                Forecast
                            </span>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-border/40 bg-card/80 p-3">
                        <svg
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            class="h-52 w-full"
                            role="img"
                            aria-label="Demand forecast chart"
                        >
                            <line
                                v-for="guide in [0.25, 0.5, 0.75]"
                                :key="guide"
                                :x1="chartPadding"
                                :x2="chartWidth - chartPadding"
                                :y1="chartPadding + (chartHeight - chartPadding * 2) * guide"
                                :y2="chartPadding + (chartHeight - chartPadding * 2) * guide"
                                stroke="currentColor"
                                class="text-border/50"
                                stroke-dasharray="4 6"
                            />

                            <line
                                v-if="forecastDividerX !== null"
                                :x1="forecastDividerX"
                                :x2="forecastDividerX"
                                :y1="chartPadding"
                                :y2="chartHeight - chartPadding"
                                stroke="currentColor"
                                class="text-primary/50"
                                stroke-dasharray="6 4"
                            />

                            <polyline
                                v-if="historyLine"
                                :points="historyLine"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                class="text-slate-400"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <polyline
                                v-if="forecastLine"
                                :points="forecastLine"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3.5"
                                class="text-primary"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </div>
                </div>

                <div class="grid gap-4">
                    <div class="rounded-2xl border border-border/50 bg-background/70 p-4">
                        <div class="text-sm font-semibold tracking-tight">
                            Inventory posture
                        </div>
                        <div class="mt-4 grid gap-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-muted-foreground">On hand</span>
                                <span class="font-semibold">
                                    {{ forecast.current_on_hand_qty }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-muted-foreground">Reorder point</span>
                                <span class="font-semibold">
                                    {{ forecast.reorder_point_qty }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-muted-foreground">Lead time buffer</span>
                                <span class="font-semibold">
                                    {{ forecast.lead_time_days }} days
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-muted-foreground">Safety stock</span>
                                <span class="font-semibold">
                                    {{ forecast.safety_stock_days }} days
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border/50 bg-background/70 p-4">
                        <div class="text-sm font-semibold tracking-tight">
                            Analyst note
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            {{
                                forecast.predicted_days_until_stockout !== null &&
                                forecast.predicted_days_until_stockout <= forecast.lead_time_days
                                    ? 'Demand is projected to outrun current stock before the normal replenishment lead time closes. Prioritize this item in the next purchasing cycle.'
                                    : 'Current stock still covers the projected lead time window, but the reorder target helps avoid a reactive stockout later in the month.'
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
