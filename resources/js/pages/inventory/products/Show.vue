<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    Building2,
    History,
    Package,
    Pencil,
    Printer,
    Tag,
    Trash2,
} from 'lucide-vue-next';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import Heading from '@/components/Heading.vue';
import ProductForecastPanel from '@/components/inventory/ProductForecastPanel.vue';
import { Badge } from '@/components/ui/badge';
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
    variant: string | null;
    brand: string | null;
    model: string | null;
    color: string | null;
    size: string | null;
    specifications: Record<string, string> | null;
    type: 'asset' | 'consumable';
    is_active: boolean;
    reorder_threshold: number;
    category_id: number | null;
    category: string | null;
    origin_id: number | null;
    origin: string | null;
    supplier_id: number | null;
    supplier: string | null;
    unit_of_measurement: {
        id: number;
        name: string;
        abbreviation: string;
    } | null;
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

const props = defineProps<{
    product: Product;
    forecast: ProductForecast | null;
    stockMovements: StockMovement[];
    can: {
        edit: boolean;
        printLabel: boolean;
    };
    isDeleted?: boolean;
}>();

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

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return 'Not recorded';
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

function stockLabel(): string {
    if (props.product.type !== 'consumable') {
        return 'Asset tracked';
    }

    const onHand = props.product.on_hand_qty ?? 0;

    if (onHand <= 0) {
        return 'Out of stock';
    }

    if (onHand <= props.product.reorder_threshold) {
        return 'Low stock';
    }

    return 'In stock';
}

function stockToneClass(): string {
    const label = stockLabel();

    if (label === 'Out of stock') {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300';
    }

    if (label === 'Low stock') {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300';
    }

    return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300';
}
</script>

<template>
    <Head :title="product.name" />

    <div
        class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="product-show-page"
    >
        <!-- Trashed Banner -->
        <div
            v-if="isDeleted"
            class="flex flex-col gap-3 rounded-xl border border-rose-500/30 bg-rose-50/60 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-rose-900/60 dark:bg-rose-950/40"
        >
            <div class="flex items-center gap-3">
                <Trash2
                    class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400"
                />
                <div>
                    <span
                        class="font-semibold text-rose-900 dark:text-rose-200"
                    >
                        This product is currently in the trash.
                    </span>
                    <p class="text-xs text-rose-700/80 dark:text-rose-300/80">
                        It has been soft-deleted and is hidden from standard
                        requisitioning and receiving catalogs.
                    </p>
                </div>
            </div>

            <Button
                variant="outline"
                size="sm"
                as-child
                class="border-rose-300 bg-white text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-800 dark:bg-rose-900/40 dark:text-rose-200"
            >
                <Link href="/inventory/products/trash">
                    <span>Manage in Trash</span>
                </Link>
            </Button>
        </div>

        <!-- Page Header & Action Controls -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1.5">
                <Heading
                    variant="small"
                    :title="product.name"
                    :description="`SKU: ${product.sku}`"
                />

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <AdminCodeBadge :value="product.sku" />

                    <span
                        class="inline-flex items-center gap-1 rounded-md px-2.5 py-0.5 text-xs font-semibold tracking-wider uppercase"
                        :class="
                            product.type === 'consumable'
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                        "
                    >
                        <Boxes
                            v-if="product.type === 'consumable'"
                            class="h-3.5 w-3.5"
                        />
                        <Tag v-else class="h-3.5 w-3.5" />
                        {{ product.type }}
                    </span>

                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            product.is_active
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                product.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-muted-foreground/40'
                            "
                        />
                        {{ product.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-lg text-xs"
                >
                    <Link :href="productsIndex()">
                        <ArrowLeft class="h-3.5 w-3.5" />
                        <span>Catalog</span>
                    </Link>
                </Button>

                <Button
                    v-if="can.printLabel"
                    variant="outline"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-lg text-xs"
                >
                    <Link :href="productLabel(product.id)">
                        <Printer class="h-3.5 w-3.5 text-primary" />
                        <span>Print label</span>
                    </Link>
                </Button>

                <Button
                    v-if="can.edit"
                    as-child
                    size="sm"
                    data-test="show-edit-product-button"
                    data-testid="show-edit-product-button"
                    class="h-9 gap-1.5 rounded-lg px-4 text-xs font-semibold shadow-xs"
                >
                    <Link :href="productsEdit(product.id)">
                        <Pencil class="h-3.5 w-3.5" />
                        <span>Edit product</span>
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Cohesive Information Cards -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Card 1: Overview & Classification -->
            <div
                class="flex flex-col overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-2xs ring-1 ring-black/2 dark:border-border/60 dark:bg-card/90 dark:ring-white/3"
            >
                <div class="border-b border-border/60 bg-muted/30 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <Tag class="h-4 w-4 text-primary" />
                        <h2 class="text-sm font-semibold text-foreground">
                            Classification
                        </h2>
                    </div>
                </div>

                <div class="grid divide-y divide-border/40 p-5 pt-1 text-sm">
                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Product Type</span
                        >
                        <span class="font-medium text-foreground capitalize">{{
                            product.type
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Unit of measurement</span
                        >
                        <span class="text-right font-medium text-foreground">{{
                            product.unit_of_measurement
                                ? `${product.unit_of_measurement.name} (${product.unit_of_measurement.abbreviation})`
                                : 'Not specified'
                        }}</span>
                    </div>

                    <div
                        v-if="product.variant"
                        class="flex items-center justify-between gap-4 py-2.5"
                    >
                        <span class="text-xs text-muted-foreground"
                            >Variant</span
                        >
                        <span class="text-right font-medium text-foreground">{{
                            product.variant
                        }}</span>
                    </div>

                    <div
                        v-if="product.brand || product.model"
                        class="flex items-center justify-between gap-4 py-2.5"
                    >
                        <span class="text-xs text-muted-foreground"
                            >Brand / model</span
                        >
                        <span class="text-right font-medium text-foreground">{{
                            [product.brand, product.model]
                                .filter(Boolean)
                                .join(' · ')
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Category</span
                        >
                        <span class="font-medium text-foreground">{{
                            product.category ?? 'Not assigned'
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Origin</span
                        >
                        <span class="font-medium text-foreground">{{
                            product.origin ?? 'Not specified'
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Catalog Status</span
                        >
                        <span class="font-medium text-foreground">{{
                            product.is_active ? 'Active' : 'Inactive'
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Inventory & Stock Status -->
            <div
                class="flex flex-col overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-2xs ring-1 ring-black/2 dark:border-border/60 dark:bg-card/90 dark:ring-white/3"
            >
                <div class="border-b border-border/60 bg-muted/30 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <Package class="h-4 w-4 text-primary" />
                        <h2 class="text-sm font-semibold text-foreground">
                            {{
                                product.type === 'consumable'
                                    ? 'Inventory Status'
                                    : 'Asset Register'
                            }}
                        </h2>
                    </div>
                </div>

                <div
                    v-if="product.type === 'consumable'"
                    class="flex flex-1 flex-col justify-between p-5"
                >
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Stock Status
                        </div>
                        <div class="mt-2 flex items-baseline gap-3">
                            <span
                                class="font-mono text-3xl font-bold tracking-tight text-foreground"
                                data-testid="product-on-hand-value"
                            >
                                <span
                                    class="font-sans text-xs font-medium text-muted-foreground"
                                    >On hand: </span
                                >{{ product.on_hand_qty ?? 0 }}
                            </span>
                            <span
                                class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                :class="stockToneClass()"
                            >
                                {{ stockLabel() }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-border/40 pt-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground"
                                >Reorder Threshold</span
                            >
                            <span
                                class="font-mono font-semibold text-foreground"
                                data-testid="product-reorder-threshold-value"
                            >
                                {{ product.reorder_threshold ?? 0 }} units
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Alert triggered when available inventory is at or
                            below this number.
                        </p>
                    </div>
                </div>

                <div v-else class="flex flex-1 flex-col justify-between p-5">
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Registered Assets
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span
                                class="font-mono text-3xl font-bold tracking-tight text-foreground"
                                data-testid="product-assets-count-value"
                            >
                                {{ product.assets_count }}
                            </span>
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >individual items</span
                            >
                        </div>
                    </div>

                    <div
                        class="mt-4 border-t border-border/40 pt-3 text-xs text-muted-foreground"
                    >
                        Each asset is individually tagged with a barcode
                        property identifier and custodian assignment.
                    </div>
                </div>
            </div>

            <!-- Card 3: Procurement & Replenishment -->
            <div
                class="flex flex-col overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-2xs ring-1 ring-black/2 dark:border-border/60 dark:bg-card/90 dark:ring-white/3"
            >
                <div class="border-b border-border/60 bg-muted/30 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <Building2 class="h-4 w-4 text-primary" />
                        <h2 class="text-sm font-semibold text-foreground">
                            Procurement
                        </h2>
                    </div>
                </div>

                <div class="grid divide-y divide-border/40 p-5 pt-1 text-sm">
                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Preferred Supplier</span
                        >
                        <span class="font-medium text-foreground">{{
                            product.supplier ?? 'No preferred supplier recorded'
                        }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Lead Time</span
                        >
                        <span class="font-medium text-foreground">
                            {{
                                product.lead_time_days !== null
                                    ? `${product.lead_time_days} ${product.lead_time_days === 1 ? 'day' : 'days'}`
                                    : 'Not specified'
                            }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Default Unit Price</span
                        >
                        <span class="font-mono font-medium text-foreground">
                            {{
                                product.unit_price !== null
                                    ? formatPhilippinePeso(product.unit_price)
                                    : 'No unit price recorded'
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demand Forecast Panel (Consumables) -->
        <ProductForecastPanel
            v-if="product.type === 'consumable'"
            :forecast="forecast"
        />

        <!-- Stock Movement History (Consumables) -->
        <div
            v-if="product.type === 'consumable'"
            class="overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-2xs ring-1 ring-black/2 dark:border-border/60 dark:bg-card/90 dark:ring-white/3"
        >
            <div
                class="flex items-center justify-between border-b border-border/60 bg-muted/30 px-5 py-3.5"
            >
                <div class="flex items-center gap-2">
                    <History class="h-4 w-4 text-primary" />
                    <h2 class="text-sm font-semibold text-foreground">
                        Stock Movement History
                    </h2>
                </div>
                <Badge
                    variant="secondary"
                    class="h-5 px-2 text-[11px] font-normal"
                >
                    {{ stockMovements.length }}
                    {{ stockMovements.length === 1 ? 'movement' : 'movements' }}
                    recorded
                </Badge>
            </div>

            <div
                v-if="stockMovements.length === 0"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                No stock movements have been recorded yet for this product.
            </div>

            <div v-else>
                <!-- Mobile Stock Movements (< 768px) -->
                <div class="grid divide-y divide-border/40 md:hidden">
                    <div
                        v-for="m in stockMovements"
                        :key="m.id"
                        class="flex flex-col gap-2 p-4 text-xs"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="font-semibold text-foreground">{{
                                    m.source
                                }}</span>
                                <div class="text-[11px] text-muted-foreground">
                                    {{ formatDateTime(m.performed_at) }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase"
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

                        <div
                            class="grid grid-cols-3 gap-2 rounded-lg bg-muted/30 p-2 text-center"
                        >
                            <div>
                                <span class="text-[10px] text-muted-foreground"
                                    >Before</span
                                >
                                <div class="font-mono font-medium">
                                    {{ m.qty_before ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted-foreground"
                                    >Delta</span
                                >
                                <div
                                    class="font-mono font-semibold"
                                    :class="
                                        m.qty_delta > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : m.qty_delta < 0
                                              ? 'text-rose-600 dark:text-rose-400'
                                              : ''
                                    "
                                >
                                    {{ m.qty_delta > 0 ? '+' : ''
                                    }}{{ m.qty_delta }}
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted-foreground"
                                    >After</span
                                >
                                <div class="font-mono font-medium">
                                    {{ m.qty_after ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="text-[11px] text-muted-foreground">
                            <span>By: </span>
                            <span class="font-medium text-foreground">{{
                                m.performed_by
                            }}</span>
                            <span v-if="m.reference">
                                • Ref: {{ m.reference }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Desktop Stock Movements Table (>= 768px) -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border/60 bg-muted/20 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Date / Time</th>
                                <th class="px-4 py-3">Source</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3 text-right">Before</th>
                                <th class="px-4 py-3 text-right">Change</th>
                                <th class="px-4 py-3 text-right">After</th>
                                <th class="px-4 py-3">Performed By</th>
                                <th class="px-4 py-3">
                                    Reference / Accountability
                                </th>
                                <th class="px-4 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40 text-xs">
                            <tr
                                v-for="m in stockMovements"
                                :key="m.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ formatDateTime(m.performed_at) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ m.source }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
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
                                <td
                                    class="px-4 py-3 text-right font-mono text-muted-foreground"
                                >
                                    {{ m.qty_before ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-semibold"
                                    :class="
                                        m.qty_delta > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : m.qty_delta < 0
                                              ? 'text-rose-600 dark:text-rose-400'
                                              : 'text-foreground'
                                    "
                                >
                                    {{ m.qty_delta > 0 ? '+' : ''
                                    }}{{ m.qty_delta }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono text-foreground"
                                >
                                    {{ m.qty_after ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-foreground"
                                >
                                    {{ m.performed_by }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        m.reference ??
                                        m.accountable_position ??
                                        '—'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 text-muted-foreground italic"
                                >
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
