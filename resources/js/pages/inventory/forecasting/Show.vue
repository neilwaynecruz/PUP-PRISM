<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    Calculator,
    CheckCircle2,
    ShoppingCart,
} from 'lucide-vue-next';
import ForecastController from '@/actions/App/Http/Controllers/Inventory/ForecastController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProductForecastPanel from '@/components/inventory/ProductForecastPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as forecastingIndex } from '@/routes/inventory/forecasting';
import { create as purchaseOrdersCreate } from '@/routes/inventory/purchase-orders';

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

type ProductOptimization = {
    product_id: number;
    sku: string;
    name: string;
    on_hand_qty: number;
    abc_classification: 'A' | 'B' | 'C' | string;
    safety_stock: {
        mean_daily_demand: number;
        demand_std_dev: number;
        lead_time_days: number;
        service_level_z: number;
        safety_stock: number;
        reorder_point: number;
        lookback_days: number;
        data_points_count: number;
        non_zero_days_count: number;
    };
    eoq: {
        annual_demand: number;
        unit_price: number;
        ordering_cost: number;
        holding_cost_rate: number;
        holding_cost_per_unit: number;
        eoq: number;
        orders_per_year: number;
        order_cycle_days: number;
        annual_ordering_cost: number;
        annual_holding_cost: number;
        total_annual_inventory_cost: number;
    };
    recommended_batch_order_qty: number;
};

type BacktestMethodMetrics = {
    label: string;
    mae: number;
    rmse: number;
    wape_pct: number | null;
    bias: number;
};

type BacktestResult = {
    has_sufficient_data: boolean;
    evaluation_days: number;
    total_history_days: number;
    non_zero_history_days: number;
    methods: Record<string, BacktestMethodMetrics>;
    recommended_method: string | null;
};

type MethodOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    product: {
        id: number;
        sku: string;
        name: string;
        on_hand_qty: number;
        reorder_threshold: number | null;
    };
    forecast: ProductForecast | null;
    profile: {
        method: string;
        lookback_days: number;
        forecast_horizon_days: number;
        lead_time_days: number;
        safety_stock_days: number;
    };
    optimization?: ProductOptimization | null;
    backtest?: BacktestResult | null;
    confidenceExplanation: string;
    methodOptions: MethodOption[];
}>();

defineOptions({
    name: 'InventoryForecastingShowPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: forecastingIndex() },
            { title: 'Forecasting', href: forecastingIndex() },
            { title: 'Detail', href: forecastingIndex() },
        ],
    },
});

const form = useForm({
    method: props.profile.method,
    lookback_days: props.profile.lookback_days,
    forecast_horizon_days: props.profile.forecast_horizon_days,
    lead_time_days: props.profile.lead_time_days,
    safety_stock_days: props.profile.safety_stock_days,
});

function submitProfile(): void {
    form.put(
        ForecastController.updateProfile.url({ product: props.product.id }),
        {
            preserveScroll: true,
        },
    );
}

function abcBadgeClass(classification: string | undefined): string {
    if (classification === 'A') {
        return 'border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300';
    }

    if (classification === 'B') {
        return 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300';
    }

    return 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300';
}
</script>

<template>
    <Head :title="`${product.name} forecast`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                :title="product.name"
                :description="`${product.sku} · On hand ${product.on_hand_qty}`"
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="
                        (forecast?.recommended_reorder_qty ?? 0) > 0 ||
                        (optimization?.recommended_batch_order_qty ?? 0) > 0
                    "
                    size="sm"
                    as-child
                    class="h-9 rounded-xl bg-violet-600 px-3.5 text-xs font-semibold text-white shadow-xs hover:bg-violet-700"
                >
                    <Link
                        :href="
                            purchaseOrdersCreate({
                                query: {
                                    product_id: product.id,
                                    qty:
                                        forecast?.recommended_reorder_qty ||
                                        optimization?.recommended_batch_order_qty ||
                                        1,
                                },
                            })
                        "
                    >
                        <ShoppingCart class="mr-1.5 h-4 w-4" />
                        Create Purchase Order
                    </Link>
                </Button>

                <Button variant="ghost" as-child>
                    <Link :href="forecastingIndex()">Back to forecasting</Link>
                </Button>
            </div>
        </div>

        <ProductForecastPanel :forecast="forecast" />

        <!-- Inventory Optimization & Scientific Parameters -->
        <section
            v-if="optimization"
            class="rounded-3xl border border-border/60 bg-card/90 p-5 shadow-sm backdrop-blur"
        >
            <div
                class="mb-4 flex flex-col gap-2 border-b border-border/40 pb-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-500/15 text-purple-600 ring-1 ring-purple-500/20 dark:text-purple-300"
                    >
                        <Calculator class="h-4 w-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold tracking-tight">
                            Inventory optimization & parameters
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            ABC classification, Economic Order Quantity (EOQ),
                            and scientific safety stock.
                        </p>
                    </div>
                </div>

                <div
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="abcBadgeClass(optimization.abc_classification)"
                >
                    <Boxes class="h-3.5 w-3.5" />
                    Class {{ optimization.abc_classification }} Item
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-2xl border border-border/50 bg-background/60 p-4"
                >
                    <div
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Scientific safety stock
                    </div>
                    <div class="mt-2 font-display text-2xl font-semibold">
                        {{ optimization.safety_stock.safety_stock }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >units</span
                        >
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        At 95% service level (Z = 1.65)
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-border/50 bg-background/60 p-4"
                >
                    <div
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Scientific reorder point
                    </div>
                    <div class="mt-2 font-display text-2xl font-semibold">
                        {{ optimization.safety_stock.reorder_point }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >units</span
                        >
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Mean daily:
                        {{ optimization.safety_stock.mean_daily_demand }}/d (σ =
                        {{ optimization.safety_stock.demand_std_dev }})
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-border/50 bg-background/60 p-4"
                >
                    <div
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Optimal EOQ batch
                    </div>
                    <div class="mt-2 font-display text-2xl font-semibold">
                        {{
                            optimization.eoq.eoq > 0
                                ? optimization.eoq.eoq
                                : '—'
                        }}
                        <span
                            v-if="optimization.eoq.eoq > 0"
                            class="text-xs font-normal text-muted-foreground"
                            >units</span
                        >
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{
                            optimization.eoq.orders_per_year > 0
                                ? `${optimization.eoq.orders_per_year} orders/yr (~every ${optimization.eoq.order_cycle_days}d)`
                                : 'Insufficient demand data'
                        }}
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-border/50 bg-background/60 p-4"
                >
                    <div
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Annual consumption
                    </div>
                    <div class="mt-2 font-display text-2xl font-semibold">
                        {{ optimization.eoq.annual_demand }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >units</span
                        >
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Holding: ₱{{
                            optimization.eoq.holding_cost_per_unit
                        }}/unit/yr
                    </div>
                </div>
            </div>
        </section>

        <!-- Model Accuracy & Empirical Backtesting Comparison -->
        <section
            class="rounded-3xl border border-border/60 bg-card/90 p-5 shadow-sm backdrop-blur"
        >
            <div
                class="mb-4 flex flex-col gap-2 border-b border-border/40 pb-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 ring-1 ring-blue-500/20 dark:text-blue-300"
                    >
                        <BarChart3 class="h-4 w-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold tracking-tight">
                            Historical Model Backtesting & Accuracy
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Evaluated against the last
                            {{ backtest?.evaluation_days ?? 14 }} days of actual
                            issuance history to measure prediction precision.
                        </p>
                    </div>
                </div>

                <div
                    v-if="
                        backtest?.recommended_method &&
                        backtest.methods[backtest.recommended_method]
                    "
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                >
                    <CheckCircle2
                        class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                    />
                    Best fit:
                    {{ backtest.methods[backtest.recommended_method].label }}
                </div>
            </div>

            <div
                v-if="
                    backtest?.has_sufficient_data &&
                    Object.keys(backtest.methods).length > 0
                "
            >
                <!-- Mobile Cards -->
                <div class="grid gap-3 sm:hidden">
                    <div
                        v-for="(metrics, methodKey) in backtest.methods"
                        :key="`backtest-m-${methodKey}`"
                        class="rounded-2xl border p-4 shadow-xs"
                        :class="
                            methodKey === backtest.recommended_method
                                ? 'border-emerald-500/30 bg-emerald-500/5'
                                : 'border-border/60 bg-background/60'
                        "
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold">{{
                                metrics.label
                            }}</span>
                            <span
                                v-if="methodKey === backtest.recommended_method"
                                class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:text-emerald-300"
                            >
                                Recommended
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2"
                            >
                                <div class="text-muted-foreground">
                                    MAE (Error)
                                </div>
                                <div class="font-mono font-semibold">
                                    {{ metrics.mae }} units/day
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2"
                            >
                                <div class="text-muted-foreground">WAPE %</div>
                                <div class="font-mono font-semibold">
                                    {{
                                        metrics.wape_pct === null
                                            ? 'Not available'
                                            : `${metrics.wape_pct}%`
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2"
                            >
                                <div class="text-muted-foreground">RMSE</div>
                                <div class="font-mono font-semibold">
                                    {{ metrics.rmse }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-border/40 bg-muted/20 p-2"
                            >
                                <div class="text-muted-foreground">
                                    Forecast Bias
                                </div>
                                <div
                                    class="font-mono font-semibold"
                                    :class="
                                        metrics.bias > 0
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-blue-600 dark:text-blue-400'
                                    "
                                >
                                    {{
                                        metrics.bias > 0
                                            ? `+${metrics.bias}`
                                            : metrics.bias
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border/60 bg-muted/25 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Forecasting Method</th>
                                <th class="px-4 py-3">MAE (Mean Error)</th>
                                <th class="px-4 py-3">RMSE (Root MSE)</th>
                                <th class="px-4 py-3">WAPE %</th>
                                <th class="px-4 py-3">Forecast Bias</th>
                                <th class="px-4 py-3 text-right">Model Fit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="(metrics, methodKey) in backtest.methods"
                                :key="`backtest-d-${methodKey}`"
                                :class="
                                    methodKey === backtest.recommended_method
                                        ? 'bg-emerald-500/5'
                                        : 'hover:bg-muted/15'
                                "
                            >
                                <td class="px-4 py-3.5 font-medium">
                                    {{ metrics.label }}
                                    <span
                                        v-if="methodKey === profile.method"
                                        class="ml-2 text-xs text-muted-foreground"
                                        >(Active)</span
                                    >
                                </td>
                                <td class="px-4 py-3.5 font-mono">
                                    {{ metrics.mae }}
                                    <span class="text-xs text-muted-foreground"
                                        >units/day</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3.5 font-mono text-muted-foreground"
                                >
                                    {{ metrics.rmse }}
                                </td>
                                <td class="px-4 py-3.5 font-mono">
                                    {{
                                        metrics.wape_pct === null
                                            ? 'Not available'
                                            : `${metrics.wape_pct}%`
                                    }}
                                </td>
                                <td class="px-4 py-3.5 font-mono text-xs">
                                    <span
                                        :class="
                                            metrics.bias > 0
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : metrics.bias < 0
                                                  ? 'text-blue-600 dark:text-blue-400'
                                                  : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            metrics.bias > 0
                                                ? `+${metrics.bias} (Over-projects)`
                                                : metrics.bias < 0
                                                  ? `${metrics.bias} (Under-projects)`
                                                  : '0.00 (Neutral)'
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <span
                                        v-if="
                                            methodKey ===
                                            backtest.recommended_method
                                        "
                                        class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                                    >
                                        Optimal Accuracy
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        Suboptimal
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-xs text-muted-foreground">
                    <span class="font-semibold text-foreground"
                        >Interpretation Note:</span
                    >
                    Lower MAE (Mean Absolute Error) indicates greater prediction
                    accuracy against real historical issuances. A positive bias
                    means the model tends to estimate higher daily use, while a
                    negative bias means it tends to estimate lower daily use.
                </div>
            </div>

            <div v-else class="py-6 text-center text-xs text-muted-foreground">
                There isn't enough historical consumption activity yet. At least
                three days with recorded issuances are needed within the
                analysis period.
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div
                class="rounded-2xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <h2 class="text-sm font-semibold tracking-tight">
                    Confidence guidance
                </h2>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    {{ confidenceExplanation }}
                </p>
                <div
                    v-if="forecast"
                    class="mt-4 grid gap-3 text-sm sm:grid-cols-2"
                >
                    <div
                        class="rounded-xl border border-border/50 bg-background/70 p-4"
                    >
                        <div class="text-muted-foreground">
                            Reorder threshold
                        </div>
                        <div class="mt-1 font-semibold">
                            {{ product.reorder_threshold ?? 'Not set' }}
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-border/50 bg-background/70 p-4"
                    >
                        <div class="text-muted-foreground">Forecast source</div>
                        <div class="mt-1 font-semibold capitalize">
                            {{ forecast.source }}
                        </div>
                    </div>
                </div>
            </div>

            <form
                class="rounded-2xl border border-border/60 bg-card p-5 shadow-sm"
                @submit.prevent="submitProfile"
            >
                <h2 class="text-sm font-semibold tracking-tight">
                    Forecast profile
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Adjust the model inputs used by the nightly forecast job and
                    live preview.
                </p>

                <div class="mt-5 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="method">Method</Label>
                        <select
                            id="method"
                            v-model="form.method"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="option in methodOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.method" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="lookback_days">Lookback days</Label>
                        <Input
                            id="lookback_days"
                            v-model.number="form.lookback_days"
                            type="number"
                            min="14"
                            max="365"
                        />
                        <InputError :message="form.errors.lookback_days" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="forecast_horizon_days"
                            >Forecast horizon days</Label
                        >
                        <Input
                            id="forecast_horizon_days"
                            v-model.number="form.forecast_horizon_days"
                            type="number"
                            min="7"
                            max="90"
                        />
                        <InputError
                            :message="form.errors.forecast_horizon_days"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="lead_time_days">Lead time days</Label>
                        <Input
                            id="lead_time_days"
                            v-model.number="form.lead_time_days"
                            type="number"
                            min="1"
                            max="90"
                        />
                        <InputError :message="form.errors.lead_time_days" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="safety_stock_days">Safety stock days</Label>
                        <Input
                            id="safety_stock_days"
                            v-model.number="form.safety_stock_days"
                            type="number"
                            min="0"
                            max="90"
                        />
                        <InputError :message="form.errors.safety_stock_days" />
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        Save profile
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>
