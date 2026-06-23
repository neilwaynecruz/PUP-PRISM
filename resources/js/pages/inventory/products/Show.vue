<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import ProductForecastPanel from '@/components/inventory/ProductForecastPanel.vue';
import { Button } from '@/components/ui/button';
import { formatPhilippinePeso } from '@/lib/utils';
import {
    edit as productsEdit,
    index as productsIndex,
    label as productLabel,
} from '@/routes/inventory/products';

type Product = {
    id: number;
    sku: string;
    name: string;
    type: 'asset' | 'consumable';
    is_active: boolean;
    reorder_threshold: number;
    category_id: number | null;
    category: string | null;
    origin_id: number | null;
    origin: string | null;
    supplier_id: number | null;
    supplier: string | null;
    lead_time_days: number | null;
    unit_price: number | null;
    on_hand_qty: number | null;
    assets_count: number;
};

type StockMovement = {
    id: number;
    movement_type: string;
    qty_delta: number;
    qty_before: number | null;
    qty_after: number | null;
    performed_by: string;
    performed_at: string | null;
    notes: string | null;
    source: string;
    reference: string | null;
    accountable_position: string | null;
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
    historical_daily: { date: string; qty: number }[];
    forecast_daily: { date: string; predicted_qty: number }[];
    history_window_days: number;
    forecast_horizon_days: number;
    lead_time_days: number;
    safety_stock_days: number;
    has_sufficient_history: boolean;
};

defineProps<{
    product: Product;
    forecast: ProductForecast | null;
    stockMovements: StockMovement[];
    can: {
        edit: boolean;
        printLabel: boolean;
    };
    isDeleted?: boolean;
}>();

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

defineOptions({
    name: 'InventoryProductShowPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Products', href: productsIndex() },
            { title: 'View', href: productsIndex() },
        ],
    },
});
</script>

<template>
    <Head :title="product.name" />

    <div class="flex flex-col gap-6 p-4" data-testid="product-show-page">
        <div
            v-if="isDeleted"
            class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950"
        >
            <div class="flex items-center gap-3">
                <span class="h-2 w-2 rounded-full bg-rose-500" />
                <span class="font-medium text-rose-900 dark:text-rose-100"
                    >This product has been deleted and is in trash.</span
                >
                <Button variant="ghost" size="sm" as-child class="ml-auto">
                    <Link href="/inventory/products/trash">Go to Trash</Link>
                </Button>
            </div>
        </div>

        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <Heading
                variant="small"
                :title="product.name"
                :description="`SKU: ${product.sku}`"
            />

            <div class="flex items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="productsIndex()">Back</Link>
                </Button>
                <Button v-if="can.printLabel" variant="ghost" as-child>
                    <Link :href="productLabel(product.id)">Print label</Link>
                </Button>
                <Button
                    v-if="can.edit"
                    as-child
                    data-test="show-edit-product-button"
                    data-testid="show-edit-product-button"
                >
                    <Link :href="productsEdit(product.id)">Edit</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Type</div>
                <div class="mt-1 font-medium capitalize">
                    {{ product.type }}
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Status</div>
                <div class="mt-1 font-medium">
                    {{ product.is_active ? 'Active' : 'Inactive' }}
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Category</div>
                <div class="mt-1 font-medium">
                    {{ product.category ?? '—' }}
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Supplier</div>
                <div class="mt-1 font-medium">
                    {{ product.supplier ?? '—' }}
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Origin</div>
                <div class="mt-1 font-medium">{{ product.origin ?? '—' }}</div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">
                    Reorder threshold
                </div>
                <div
                    class="mt-1 font-medium"
                    data-testid="product-reorder-threshold-value"
                >
                    {{ product.reorder_threshold ?? 0 }}
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Lead time</div>
                <div class="mt-1 font-medium">
                    {{
                        product.lead_time_days !== null
                            ? `${product.lead_time_days} day(s)`
                            : '—'
                    }}
                </div>
            </div>

            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">
                    Default unit price
                </div>
                <div class="mt-1 font-medium">
                    {{
                        product.unit_price !== null
                            ? formatPhilippinePeso(product.unit_price)
                            : '—'
                    }}
                </div>
            </div>

            <div
                v-if="product.type === 'consumable'"
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Stock</div>
                <div
                    class="mt-1 font-medium"
                    data-testid="product-on-hand-value"
                >
                    On hand: {{ product.on_hand_qty ?? 0 }}
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Assets</div>
                <div
                    class="mt-1 font-medium"
                    data-testid="product-assets-count-value"
                >
                    {{ product.assets_count }}
                </div>
            </div>
        </div>

        <ProductForecastPanel
            v-if="product.type === 'consumable'"
            :forecast="forecast"
        />

        <!-- Stock movement history -->
        <div
            v-if="product.type === 'consumable'"
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="mb-4 text-sm font-semibold tracking-tight">
                Stock movement history
            </div>

            <div
                v-if="stockMovements.length === 0"
                class="text-sm text-muted-foreground"
            >
                No stock movements recorded yet.
            </div>

            <div v-else class="grid gap-3">
                <div class="grid gap-3 md:hidden">
                    <div
                        v-for="m in stockMovements"
                        :key="m.id"
                        class="rounded-xl border border-border/40 p-4 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ m.source }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ formatDateTime(m.performed_at) }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    m.movement_type === 'receive'
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : m.movement_type === 'issue'
                                          ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                          : 'bg-slate-500/10 text-slate-700 dark:text-slate-400'
                                "
                            >
                                {{ m.movement_type }}
                            </span>
                        </div>
                        <div class="mt-3 grid gap-2 text-sm">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >Change</span
                                >
                                <span
                                    class="font-mono"
                                    :class="
                                        m.qty_delta > 0
                                            ? 'text-emerald-600'
                                            : m.qty_delta < 0
                                              ? 'text-rose-600'
                                              : ''
                                    "
                                >
                                    {{ m.qty_delta > 0 ? '+' : ''
                                    }}{{ m.qty_delta }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >Before</span
                                >
                                <span class="font-mono">{{
                                    m.qty_before ?? '—'
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground">After</span>
                                <span class="font-mono">{{
                                    m.qty_after ?? '—'
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground">By</span>
                                <span>{{ m.performed_by }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >Reference</span
                                >
                                <span>{{ m.reference ?? '—' }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >Accountable</span
                                >
                                <span>{{ m.accountable_position ?? '—' }}</span>
                            </div>
                            <div>
                                <div class="text-muted-foreground">Notes</div>
                                <div class="mt-1">{{ m.notes ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead
                            class="text-left text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                        >
                            <tr class="border-b border-border/60">
                                <th class="py-2 pr-3">Date</th>
                                <th class="py-2 pr-3">Source</th>
                                <th class="py-2 pr-3">Type</th>
                                <th class="py-2 pr-3 text-right">Before</th>
                                <th class="py-2 pr-3 text-right">Change</th>
                                <th class="py-2 pr-3 text-right">After</th>
                                <th class="py-2 pr-3">By</th>
                                <th class="py-2 pr-3">Reference</th>
                                <th class="py-2 pr-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in stockMovements"
                                :key="m.id"
                                class="border-b border-border/40"
                            >
                                <td class="py-2 pr-3 text-muted-foreground">
                                    {{ formatDateTime(m.performed_at) }}
                                </td>
                                <td class="py-2 pr-3">{{ m.source }}</td>
                                <td class="py-2 pr-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="
                                            m.movement_type === 'receive'
                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                : m.movement_type === 'issue'
                                                  ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                                  : 'bg-slate-500/10 text-slate-700 dark:text-slate-400'
                                        "
                                    >
                                        {{ m.movement_type }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-right font-mono">
                                    {{ m.qty_before ?? '—' }}
                                </td>
                                <td
                                    class="py-2 pr-3 text-right font-mono"
                                    :class="
                                        m.qty_delta > 0
                                            ? 'text-emerald-600'
                                            : m.qty_delta < 0
                                              ? 'text-rose-600'
                                              : ''
                                    "
                                >
                                    {{ m.qty_delta > 0 ? '+' : ''
                                    }}{{ m.qty_delta }}
                                </td>
                                <td class="py-2 pr-3 text-right font-mono">
                                    {{ m.qty_after ?? '—' }}
                                </td>
                                <td class="py-2 pr-3">{{ m.performed_by }}</td>
                                <td class="py-2 pr-3 text-muted-foreground">
                                    {{
                                        m.reference ??
                                        m.accountable_position ??
                                        '—'
                                    }}
                                </td>
                                <td class="py-2 pr-3 text-muted-foreground">
                                    {{ m.notes ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
