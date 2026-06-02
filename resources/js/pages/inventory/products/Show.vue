<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit as productsEdit, index as productsIndex, label as productLabel } from '@/routes/inventory/products';

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
    on_hand_qty: number | null;
    assets_count: number;
};

defineProps<{ product: Product }>();

defineOptions({
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

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading
                variant="small"
                :title="product.name"
                :description="`SKU: ${product.sku}`"
            />

            <div class="flex items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="productsIndex()">Back</Link>
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="productLabel(product.id)">Print label</Link>
                </Button>
                <Button as-child>
                    <Link :href="productsEdit(product.id)">Edit</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="text-sm text-muted-foreground">Type</div>
                <div class="mt-1 font-medium capitalize">{{ product.type }}</div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="text-sm text-muted-foreground">Status</div>
                <div class="mt-1 font-medium">
                    {{ product.is_active ? 'Active' : 'Inactive' }}
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="text-sm text-muted-foreground">Category</div>
                <div class="mt-1 font-medium">{{ product.category ?? '—' }}</div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="text-sm text-muted-foreground">Origin</div>
                <div class="mt-1 font-medium">{{ product.origin ?? '—' }}</div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="text-sm text-muted-foreground">Reorder threshold</div>
                <div class="mt-1 font-medium">{{ product.reorder_threshold ?? 0 }}</div>
            </div>

            <div
                v-if="product.type === 'consumable'"
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="text-sm text-muted-foreground">Stock</div>
                <div class="mt-1 font-medium">On hand: {{ product.on_hand_qty ?? 0 }}</div>
            </div>

            <div
                v-else
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="text-sm text-muted-foreground">Assets</div>
                <div class="mt-1 font-medium">{{ product.assets_count }}</div>
            </div>
        </div>
    </div>
</template>

