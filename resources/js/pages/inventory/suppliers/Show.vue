<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    ArrowUpRight,
    Building2,
    Clock,
    CreditCard,
    Eye,
    FileText,
    Globe,
    Mail,
    MapPin,
    Package,
    Pencil,
    Phone,
    ShoppingCart,
    User,
} from 'lucide-vue-next';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatPhilippinePeso } from '@/lib/utils';
import { show as productsShow } from '@/routes/inventory/products';
import { show as purchaseOrdersShow } from '@/routes/inventory/purchase-orders';
import {
    edit as suppliersEdit,
    index as suppliersIndex,
} from '@/routes/inventory/suppliers';

type Supplier = {
    id: number;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    website: string | null;
    payment_terms: string | null;
    lead_time_days: number | null;
    is_active: boolean;
    notes: string | null;
    products_count: number | null;
    purchase_orders_count: number | null;
};

type ProductRow = {
    id: number;
    sku: string;
    name: string;
    type: 'asset' | 'consumable';
    category: string | null;
    on_hand_qty: number | null;
    reorder_threshold: number | null;
    unit_price: number | null;
};

type PurchaseOrderRow = {
    id: number;
    po_number: string;
    status_label: string;
    total_amount: number;
    progress_pct: number;
};

defineProps<{
    supplier: Supplier;
    products: ProductRow[];
    recentPurchaseOrders: PurchaseOrderRow[];
    can: {
        edit: boolean;
        delete: boolean;
        createPurchaseOrder: boolean;
    };
}>();

defineOptions({
    name: 'InventorySupplierShowPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: suppliersIndex() },
            { title: 'Suppliers', href: suppliersIndex() },
            { title: 'View', href: suppliersIndex() },
        ],
    },
});

function stockPill(product: ProductRow): { label: string; tone: string } {
    if (product.type !== 'consumable') {
        return {
            label: 'Asset tracked',
            tone: 'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
        };
    }

    const onHand = product.on_hand_qty ?? 0;
    const threshold = product.reorder_threshold ?? 0;

    if (onHand <= 0) {
        return {
            label: 'Out of stock',
            tone: 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        };
    }

    if (onHand <= threshold) {
        return {
            label: 'Low stock',
            tone: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        };
    }

    return {
        label: 'In stock',
        tone: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    };
}
</script>

<template>
    <Head :title="supplier.name" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="supplier-show-page"
    >
        <!-- Top Navigation & Action Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="-ml-2 h-8 gap-1.5 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <Link :href="suppliersIndex()">
                            <ArrowLeft class="h-3.5 w-3.5" />
                            Back to suppliers
                        </Link>
                    </Button>
                </div>
                <div class="flex items-center gap-2.5">
                    <h1
                        class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                    >
                        {{ supplier.name }}
                    </h1>
                    <AdminStatusBadge :active="supplier.is_active" />
                </div>
                <p
                    v-if="supplier.contact_person"
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <User class="h-3.5 w-3.5 text-muted-foreground/70" />
                    Contact:
                    <span class="font-medium text-foreground">{{
                        supplier.contact_person
                    }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can.edit"
                    as-child
                    size="sm"
                    class="h-9 gap-1.5 rounded-lg px-3 text-xs"
                >
                    <Link :href="suppliersEdit(supplier.id)">
                        <Pencil class="h-3.5 w-3.5" />
                        Edit supplier
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Email -->
            <div
                class="flex items-start gap-3.5 rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400"
                >
                    <Mail class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-medium text-muted-foreground">
                        Email address
                    </div>
                    <div
                        class="mt-0.5 truncate text-sm font-semibold text-foreground"
                    >
                        <a
                            v-if="supplier.email"
                            :href="`mailto:${supplier.email}`"
                            class="hover:text-primary hover:underline"
                        >
                            {{ supplier.email }}
                        </a>
                        <span v-else class="text-muted-foreground/60">—</span>
                    </div>
                </div>
            </div>

            <!-- Phone -->
            <div
                class="flex items-start gap-3.5 rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                >
                    <Phone class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-medium text-muted-foreground">
                        Phone number
                    </div>
                    <div
                        class="mt-0.5 truncate text-sm font-semibold text-foreground"
                    >
                        <a
                            v-if="supplier.phone"
                            :href="`tel:${supplier.phone}`"
                            class="hover:text-primary hover:underline"
                        >
                            {{ supplier.phone }}
                        </a>
                        <span v-else class="text-muted-foreground/60">—</span>
                    </div>
                </div>
            </div>

            <!-- Lead Time -->
            <div
                class="flex items-start gap-3.5 rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                >
                    <Clock class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-medium text-muted-foreground">
                        Lead time
                    </div>
                    <div class="mt-0.5 text-sm font-semibold text-foreground">
                        {{
                            supplier.lead_time_days !== null
                                ? `${supplier.lead_time_days} ${supplier.lead_time_days === 1 ? 'day' : 'days'}`
                                : '—'
                        }}
                    </div>
                </div>
            </div>

            <!-- Status / Activity -->
            <div
                class="flex items-start gap-3.5 rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                >
                    <Activity class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-medium text-muted-foreground">
                        Record status
                    </div>
                    <div class="mt-1 flex items-center">
                        <AdminStatusBadge :active="supplier.is_active" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 2 Column Layout: Assigned Products & Recent Purchase Orders -->
        <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <!-- Assigned Products -->
            <div
                class="flex flex-col rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Package class="h-4 w-4 text-primary" />
                        <h2
                            class="text-sm font-bold tracking-tight text-foreground uppercase"
                        >
                            Assigned products
                        </h2>
                        <span
                            class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                        >
                            {{ products.length }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="products.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border/70 py-10 text-center"
                >
                    <Package class="h-8 w-8 text-muted-foreground/40" />
                    <p class="mt-2 text-xs font-medium text-muted-foreground">
                        No products are assigned to this supplier yet.
                    </p>
                </div>

                <div v-else class="grid gap-3">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="group flex flex-col justify-between rounded-lg border border-border/60 bg-muted/20 p-3.5 transition duration-150 hover:border-primary/40 hover:bg-muted/40"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <AdminCodeBadge :value="product.sku" />
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[0.7rem] font-semibold"
                                        :class="stockPill(product).tone"
                                    >
                                        {{ stockPill(product).label }}
                                    </span>
                                </div>
                                <h3
                                    class="mt-2 truncate font-semibold text-foreground transition-colors group-hover:text-primary"
                                >
                                    {{ product.name }}
                                </h3>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                class="h-8 gap-1 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <Link :href="productsShow(product.id)">
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>View</span>
                                </Link>
                            </Button>
                        </div>

                        <div
                            class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-border/40 pt-2 text-xs text-muted-foreground"
                        >
                            <span
                                >Category:
                                <strong class="text-foreground">{{
                                    product.category ?? '—'
                                }}</strong></span
                            >
                            <span
                                >Default price:
                                <strong class="font-mono text-foreground">{{
                                    product.unit_price !== null
                                        ? formatPhilippinePeso(
                                              product.unit_price,
                                          )
                                        : '—'
                                }}</strong></span
                            >
                            <span v-if="product.type === 'consumable'">
                                On hand:
                                <strong class="text-foreground">{{
                                    product.on_hand_qty ?? 0
                                }}</strong>
                                / reorder at
                                {{ product.reorder_threshold ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Purchase Orders -->
            <div
                class="flex flex-col rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="h-4 w-4 text-primary" />
                        <h2
                            class="text-sm font-bold tracking-tight text-foreground uppercase"
                        >
                            Recent purchase orders
                        </h2>
                        <span
                            class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                        >
                            {{ recentPurchaseOrders.length }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="recentPurchaseOrders.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border/70 py-10 text-center"
                >
                    <ShoppingCart class="h-8 w-8 text-muted-foreground/40" />
                    <p class="mt-2 text-xs font-medium text-muted-foreground">
                        No purchase orders recorded yet.
                    </p>
                </div>

                <div v-else class="grid gap-3">
                    <div
                        v-for="purchaseOrder in recentPurchaseOrders"
                        :key="purchaseOrder.id"
                        class="group flex flex-col justify-between rounded-lg border border-border/60 bg-muted/20 p-3.5 transition duration-150 hover:border-primary/40 hover:bg-muted/40"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <AdminCodeBadge
                                        :value="purchaseOrder.po_number"
                                    />
                                    <span
                                        class="rounded-md border border-border/60 bg-muted/50 px-2 py-0.5 text-[0.7rem] font-semibold text-foreground"
                                    >
                                        {{ purchaseOrder.status_label }}
                                    </span>
                                </div>
                                <div
                                    class="mt-2 text-sm font-semibold text-foreground"
                                >
                                    {{
                                        formatPhilippinePeso(
                                            purchaseOrder.total_amount,
                                        )
                                    }}
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                class="h-8 gap-1 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <Link
                                    :href="purchaseOrdersShow(purchaseOrder.id)"
                                >
                                    <span>Open</span>
                                    <ArrowUpRight class="h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-3 space-y-1">
                            <div
                                class="flex justify-between text-[0.7rem] text-muted-foreground"
                            >
                                <span>Fulfilled:</span>
                                <span class="font-semibold text-foreground"
                                    >{{ purchaseOrder.progress_pct }}%</span
                                >
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all duration-300"
                                    :style="{
                                        width: `${purchaseOrder.progress_pct}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Details Card -->
        <div
            v-if="
                supplier.address ||
                supplier.website ||
                supplier.payment_terms ||
                supplier.notes
            "
            class="rounded-xl border border-border/70 bg-card p-5 shadow-xs"
        >
            <div
                class="mb-4 flex items-center gap-2 text-xs font-bold tracking-tight text-foreground uppercase"
            >
                <Building2 class="h-4 w-4 text-primary" />
                Additional vendor details
            </div>
            <div class="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-if="supplier.address"
                    class="rounded-lg border border-border/50 bg-muted/20 p-3"
                >
                    <div
                        class="flex items-center gap-1.5 font-medium text-muted-foreground"
                    >
                        <MapPin class="h-3.5 w-3.5 text-primary" />
                        Office address
                    </div>
                    <div class="mt-1 font-semibold text-foreground">
                        {{ supplier.address }}
                    </div>
                </div>
                <div
                    v-if="supplier.website"
                    class="rounded-lg border border-border/50 bg-muted/20 p-3"
                >
                    <div
                        class="flex items-center gap-1.5 font-medium text-muted-foreground"
                    >
                        <Globe class="h-3.5 w-3.5 text-primary" />
                        Website
                    </div>
                    <div class="mt-1 truncate font-semibold text-foreground">
                        <a
                            :href="supplier.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-primary hover:underline"
                        >
                            {{ supplier.website }}
                        </a>
                    </div>
                </div>
                <div
                    v-if="supplier.payment_terms"
                    class="rounded-lg border border-border/50 bg-muted/20 p-3"
                >
                    <div
                        class="flex items-center gap-1.5 font-medium text-muted-foreground"
                    >
                        <CreditCard class="h-3.5 w-3.5 text-primary" />
                        Payment terms
                    </div>
                    <div class="mt-1 font-semibold text-foreground">
                        {{ supplier.payment_terms }}
                    </div>
                </div>
                <div
                    v-if="supplier.notes"
                    class="rounded-lg border border-border/50 bg-muted/20 p-3 sm:col-span-2 lg:col-span-1"
                >
                    <div
                        class="flex items-center gap-1.5 font-medium text-muted-foreground"
                    >
                        <FileText class="h-3.5 w-3.5 text-primary" />
                        Remarks / Notes
                    </div>
                    <div class="mt-1 font-semibold text-foreground">
                        {{ supplier.notes }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
