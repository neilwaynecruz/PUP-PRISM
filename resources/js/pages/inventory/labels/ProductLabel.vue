<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    index as productsIndex,
    show as productShow,
} from '@/routes/inventory/products';

defineProps<{
    product: {
        id: number;
        sku: string;
        name: string;
        type: 'asset' | 'consumable';
    };
    qr_svg: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Label', href: productsIndex() },
        ],
    },
});

function printLabel() {
    window.print();
}
</script>

<template>
    <Head :title="`Label - ${product.name}`" />

    <div class="flex flex-col gap-6 p-4 print:p-0">
        <div class="flex items-center justify-between gap-3 print:hidden">
            <Heading
                variant="small"
                title="Product label"
                :description="`${product.name} • ${product.sku}`"
            />

            <div class="flex items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="productShow(product.id)">Back</Link>
                </Button>
                <Button @click="printLabel">Print</Button>
            </div>
        </div>

        <div
            class="mx-auto w-full max-w-sm rounded-xl border border-border/60 bg-background p-6 text-center dark:border-sidebar-border print:max-w-none print:border-0 print:p-0"
        >
            <div class="text-lg font-semibold">{{ product.name }}</div>
            <div class="mt-1 font-mono text-xs text-muted-foreground">
                {{ product.sku }}
            </div>

            <div class="mx-auto mt-4 w-64" v-html="qr_svg" />
        </div>
    </div>
</template>
