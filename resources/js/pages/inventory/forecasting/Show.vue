<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ProductForecastPanel from '@/components/inventory/ProductForecastPanel.vue';
import ForecastController from '@/actions/App/Http/Controllers/Inventory/ForecastController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as forecastingIndex,
} from '@/routes/inventory/forecasting';

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

            <Button variant="ghost" as-child>
                <Link :href="forecastingIndex()">Back to forecasting</Link>
            </Button>
        </div>

        <ProductForecastPanel :forecast="forecast" />

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-2xl border border-border/60 bg-card p-5 shadow-sm">
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
                    <div class="rounded-xl border border-border/50 bg-background/70 p-4">
                        <div class="text-muted-foreground">Reorder threshold</div>
                        <div class="mt-1 font-semibold">
                            {{ product.reorder_threshold ?? 'Not set' }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-border/50 bg-background/70 p-4">
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
                        <Label for="forecast_horizon_days">Forecast horizon days</Label>
                        <Input
                            id="forecast_horizon_days"
                            v-model.number="form.forecast_horizon_days"
                            type="number"
                            min="7"
                            max="90"
                        />
                        <InputError :message="form.errors.forecast_horizon_days" />
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
