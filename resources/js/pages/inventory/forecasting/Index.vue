<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import ForecastWidget from '@/components/inventory/ForecastWidget.vue';
import Heading from '@/components/Heading.vue';
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
        return 'text-muted-foreground';
    }

    if (days <= 7) {
        return 'text-rose-600 dark:text-rose-400';
    }

    if (days <= 14) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-emerald-600 dark:text-emerald-400';
}
</script>

<template>
    <Head title="Forecasting" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Demand forecasting"
            description="Review consumable forecasts, tune model settings, and prioritize replenishment risks."
        />

        <ForecastWidget :summary="forecastSummary" />

        <section class="rounded-2xl border border-border/60 bg-card shadow-sm">
            <div
                class="flex flex-col gap-4 border-b border-border/50 px-5 py-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <h2 class="text-sm font-semibold tracking-tight">
                        Consumable forecast registry
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Filter by urgency, confidence, or forecasting method.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Input
                        v-model="search"
                        placeholder="Search SKU or product name"
                        aria-label="Search forecasts"
                    />
                    <select
                        v-model="urgency"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
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
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
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
                    class="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ product.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ product.sku }}
                            </div>
                        </div>
                        <span
                            class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                            :class="
                                product.snapshot?.predicted_days_until_stockout !== null &&
                                product.snapshot?.predicted_days_until_stockout !== undefined
                                    ? riskClass(product.snapshot.predicted_days_until_stockout)
                                    : 'text-muted-foreground'
                            "
                        >
                            {{
                                product.snapshot?.predicted_days_until_stockout !== null &&
                                product.snapshot?.predicted_days_until_stockout !== undefined
                                    ? `${product.snapshot.predicted_days_until_stockout} days`
                                    : 'No stockout date'
                            }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                            <div class="text-[11px] uppercase tracking-wider text-muted-foreground">
                                On hand
                            </div>
                            <div class="mt-1 font-semibold">
                                {{
                                    product.snapshot?.current_on_hand_qty ??
                                    product.on_hand_qty
                                }}
                            </div>
                        </div>
                        <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                            <div class="text-[11px] uppercase tracking-wider text-muted-foreground">
                                Reorder qty
                            </div>
                            <div class="mt-1 font-semibold">
                                {{
                                    product.snapshot?.recommended_reorder_qty ?? '—'
                                }}
                            </div>
                        </div>
                        <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                            <div class="text-[11px] uppercase tracking-wider text-muted-foreground">
                                Daily demand
                            </div>
                            <div class="mt-1 font-semibold">
                                {{
                                    product.snapshot
                                        ? product.snapshot.predicted_daily_consumption.toFixed(2)
                                        : '—'
                                }}
                            </div>
                        </div>
                        <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                            <div class="text-[11px] uppercase tracking-wider text-muted-foreground">
                                Confidence
                            </div>
                            <div class="mt-1 font-semibold">
                                {{
                                    product.snapshot?.confidence_score?.toFixed(0) ?? '—'
                                }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                        <span>
                            Method:
                            {{
                                methodLabel(
                                    product.snapshot?.forecast_method ??
                                        product.profile_method,
                                )
                            }}
                        </span>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="forecastingShow(product.id)">View</Link>
                        </Button>
                    </div>
                </div>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-border/50 bg-muted/30 text-left">
                        <tr>
                            <th class="px-5 py-3 font-medium">Product</th>
                            <th class="px-5 py-3 font-medium">On hand</th>
                            <th class="px-5 py-3 font-medium">Daily demand</th>
                            <th class="px-5 py-3 font-medium">Stockout horizon</th>
                            <th class="px-5 py-3 font-medium">Reorder qty</th>
                            <th class="px-5 py-3 font-medium">Confidence</th>
                            <th class="px-5 py-3 font-medium">Method</th>
                            <th class="px-5 py-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="border-b border-border/30"
                        >
                            <td class="px-5 py-4">
                                <div class="font-medium">{{ product.name }}</div>
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
                                        ? product.snapshot.predicted_daily_consumption.toFixed(2)
                                        : '—'
                                }}
                            </td>
                            <td
                                class="px-5 py-4 font-medium"
                                :class="
                                    riskClass(
                                        product.snapshot?.predicted_days_until_stockout ??
                                            null,
                                    )
                                "
                            >
                                {{
                                    product.snapshot?.predicted_days_until_stockout ??
                                    '—'
                                }}
                            </td>
                            <td class="px-5 py-4">
                                {{
                                    product.snapshot?.recommended_reorder_qty ?? '—'
                                }}
                            </td>
                            <td class="px-5 py-4">
                                {{
                                    product.snapshot?.confidence_score?.toFixed(0) ??
                                    '—'
                                }}
                            </td>
                            <td class="px-5 py-4">
                                {{
                                    methodLabel(
                                        product.snapshot?.forecast_method ??
                                            product.profile_method,
                                    )
                                }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="forecastingShow(product.id)">
                                        View
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td
                                colspan="8"
                                class="px-5 py-10 text-center text-muted-foreground"
                            >
                                No consumable forecasts match the current filters.
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
                        v-html="link.label"
                    />
                    <span v-else v-html="link.label" />
                </Button>
            </div>
        </section>
    </div>
</template>
