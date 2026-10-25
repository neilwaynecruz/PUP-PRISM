<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
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
            { title: 'Products', href: productsIndex() },
            { title: 'Product Label', href: '#' },
        ],
    },
});

function printLabel(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Label — ${product.name}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8 print:p-0">
        <!-- Control Bar (hidden in print) -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden"
        >
            <div>
                <Heading
                    variant="small"
                    title="Product Label"
                    :description="`${product.name} • ${product.sku}`"
                />
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-lg text-xs"
                >
                    <Link :href="productShow(product.id)">
                        <ArrowLeft class="h-3.5 w-3.5" />
                        <span>Back to details</span>
                    </Link>
                </Button>
                <Button
                    size="sm"
                    class="h-9 gap-1.5 rounded-lg px-4 text-xs font-semibold shadow-xs"
                    @click="printLabel"
                >
                    <Printer class="h-3.5 w-3.5" />
                    <span>Print label</span>
                </Button>
            </div>
        </div>

        <!-- Printable Label Presentation Card -->
        <div class="mx-auto w-full max-w-md print:m-0 print:max-w-none">
            <div
                class="flex flex-col items-center justify-center rounded-2xl border-2 border-border/80 bg-white p-8 text-center text-slate-900 shadow-sm print:rounded-none print:border print:border-black print:p-6 print:shadow-none"
            >
                <!-- University / System Header -->
                <div
                    class="mb-4 flex w-full flex-col items-center gap-0.5 border-b border-slate-200 pb-3 print:border-black"
                >
                    <span
                        class="text-[10px] font-bold tracking-[0.2em] text-slate-500 uppercase print:text-black"
                    >
                        Polytechnic University of the Philippines
                    </span>
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-700 uppercase print:text-black"
                    >
                        PRISM Inventory Catalog
                    </span>
                </div>

                <!-- Product Name (gracefully wraps) -->
                <h1
                    class="text-lg leading-snug font-bold tracking-tight break-words text-slate-900 print:text-black"
                >
                    {{ product.name }}
                </h1>

                <!-- Identifier Code -->
                <div class="mt-2 flex items-center justify-center gap-2">
                    <span
                        class="inline-flex rounded-md border border-slate-300 bg-slate-50 px-2.5 py-1 font-mono text-xs font-bold text-slate-800 print:border-black print:bg-transparent print:text-black"
                    >
                        {{ product.sku }}
                    </span>
                    <span
                        class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold tracking-wider text-slate-600 uppercase print:text-black"
                    >
                        {{ product.type }}
                    </span>
                </div>

                <!-- QR Code SVG -->
                <div
                    class="my-5 flex w-48 justify-center print:my-4 print:w-44 [&>svg]:h-auto [&>svg]:w-full"
                    v-html="qr_svg"
                />

                <!-- Scan Prompt -->
                <div class="text-[11px] text-slate-500 print:text-black">
                    Scan with PRISM mobile scanner for real-time stock lookup
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body {
        background: white !important;
        color: black !important;
    }
    @page {
        margin: 10mm;
        size: auto;
    }
}
</style>
