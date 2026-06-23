<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
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
</script>

<template>
    <Head :title="supplier.name" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                :title="supplier.name"
                :description="supplier.contact_person ?? 'Supplier profile'"
            />

            <div class="flex items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="suppliersIndex()">Back</Link>
                </Button>
                <Button v-if="can.edit" as-child>
                    <Link :href="suppliersEdit(supplier.id)">Edit</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Email</div>
                <div class="mt-1 font-medium">{{ supplier.email ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Phone</div>
                <div class="mt-1 font-medium">{{ supplier.phone ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Lead time</div>
                <div class="mt-1 font-medium">
                    {{
                        supplier.lead_time_days !== null
                            ? `${supplier.lead_time_days} day(s)`
                            : '—'
                    }}
                </div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Status</div>
                <div class="mt-1 font-medium">
                    {{ supplier.is_active ? 'Active' : 'Inactive' }}
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 text-sm font-semibold tracking-tight">
                    Assigned products
                </div>

                <div v-if="products.length === 0" class="text-sm text-muted-foreground">
                    No products are assigned to this supplier yet.
                </div>

                <div v-else class="grid gap-3">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="rounded-lg border border-border/50 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ product.name }}</div>
                                <div class="font-mono text-xs text-muted-foreground">
                                    {{ product.sku }}
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="productsShow(product.id)">View</Link>
                            </Button>
                        </div>
                        <div class="mt-3 grid gap-1 text-sm text-muted-foreground">
                            <div>Category: {{ product.category ?? '—' }}</div>
                            <div>
                                Default price:
                                {{
                                    product.unit_price !== null
                                        ? formatPhilippinePeso(product.unit_price)
                                        : '—'
                                }}
                            </div>
                            <div v-if="product.type === 'consumable'">
                                On hand: {{ product.on_hand_qty ?? 0 }} / reorder
                                at {{ product.reorder_threshold ?? 0 }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 text-sm font-semibold tracking-tight">
                    Recent purchase orders
                </div>

                <div v-if="recentPurchaseOrders.length === 0" class="text-sm text-muted-foreground">
                    No purchase orders recorded yet.
                </div>

                <div v-else class="grid gap-3">
                    <div
                        v-for="purchaseOrder in recentPurchaseOrders"
                        :key="purchaseOrder.id"
                        class="rounded-lg border border-border/50 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">
                                    {{ purchaseOrder.po_number }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ purchaseOrder.status_label }}
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="purchaseOrdersShow(purchaseOrder.id)"
                                    >Open</Link
                                >
                            </Button>
                        </div>
                        <div class="mt-3 text-sm text-muted-foreground">
                            Total: {{ formatPhilippinePeso(purchaseOrder.total_amount) }}
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-primary"
                                :style="{
                                    width: `${purchaseOrder.progress_pct}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="supplier.address || supplier.website || supplier.notes"
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="mb-3 text-sm font-semibold tracking-tight">
                Additional details
            </div>
            <div class="grid gap-2 text-sm text-muted-foreground">
                <div v-if="supplier.address">Address: {{ supplier.address }}</div>
                <div v-if="supplier.website">Website: {{ supplier.website }}</div>
                <div v-if="supplier.payment_terms">
                    Payment terms: {{ supplier.payment_terms }}
                </div>
                <div v-if="supplier.notes">Notes: {{ supplier.notes }}</div>
            </div>
        </div>
    </div>
</template>
