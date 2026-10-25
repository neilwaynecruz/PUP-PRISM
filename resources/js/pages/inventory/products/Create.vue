<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Info,
    Package,
    Plus,
    Tag,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Inventory/ProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    create as productsCreate,
    index as productsIndex,
} from '@/routes/inventory/products';

type Option = { id: number; name: string };

defineProps<{
    categories: Option[];
    origins: Option[];
    suppliers: Option[];
}>();

defineOptions({
    name: 'InventoryProductCreatePage',
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Products', href: productsIndex() },
            { title: 'New', href: productsCreate() },
        ],
    },
});

const selectedType = ref<'consumable' | 'asset'>('consumable');
</script>

<template>
    <Head title="New product" />

    <div
        class="mx-auto flex max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="product-create-page"
    >
        <!-- Header -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <Heading
                    variant="small"
                    title="New product"
                    description="Register an asset or consumable item into the university catalog. Stock quantities are recorded separately when batches are received."
                />
            </div>

            <Button
                variant="outline"
                size="sm"
                as-child
                class="h-9 w-fit gap-1.5 rounded-lg text-xs"
            >
                <Link :href="productsIndex()">
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Back to catalog</span>
                </Link>
            </Button>
        </div>

        <Form
            v-bind="ProductController.store.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <!-- Section 1: Product Identity -->
            <div
                class="overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-2xs ring-1 ring-black/[0.02] dark:border-border/60 dark:bg-card/90 dark:ring-white/[0.03]"
            >
                <div class="border-b border-border/60 bg-muted/30 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <Package class="h-4 w-4 text-primary" />
                        <h2 class="text-sm font-semibold text-foreground">
                            1. Product Identity
                        </h2>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Provide a unique identification code and official
                        catalog description.
                    </p>
                </div>

                <div class="grid gap-5 p-5">
                    <!-- SKU / Barcode -->
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label
                                for="sku"
                                class="text-xs font-semibold text-foreground"
                            >
                                SKU or Barcode
                                <span class="text-rose-500">*</span>
                            </Label>
                            <span class="text-[11px] text-muted-foreground"
                                >Must be unique across catalog</span
                            >
                        </div>
                        <Input
                            id="sku"
                            name="sku"
                            data-testid="product-sku-input"
                            required
                            autocomplete="off"
                            aria-describedby="sku-help sku-error"
                            placeholder="e.g. 4801234567890 or PAP-A4-70G"
                            class="h-10 rounded-lg font-mono text-sm uppercase placeholder:font-sans placeholder:normal-case focus-visible:ring-2"
                        />
                        <p id="sku-help" class="text-xs text-muted-foreground">
                            Standard SKU, barcode, or property code used for
                            physical inventory and scanning.
                        </p>
                        <InputError id="sku-error" :message="errors.sku" />
                    </div>

                    <!-- Product Name -->
                    <div class="grid gap-2">
                        <Label
                            for="name"
                            class="text-xs font-semibold text-foreground"
                        >
                            Product Name
                            <span class="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="name"
                            name="name"
                            data-testid="product-name-input"
                            required
                            aria-describedby="product-name-error"
                            placeholder="e.g. Multicopy Bond Paper, A4, 70 GSM, 500 Sheets/Ream"
                            class="h-10 rounded-lg text-sm focus-visible:ring-2"
                        />
                        <p class="text-xs text-muted-foreground">
                            Use specific descriptions that distinguish this item
                            from similar models or variations.
                        </p>
                        <InputError
                            id="product-name-error"
                            :message="errors.name"
                        />
                    </div>
                </div>
            </div>

            <!-- Section 2: Classification -->
            <div
                class="overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-2xs ring-1 ring-black/[0.02] dark:border-border/60 dark:bg-card/90 dark:ring-white/[0.03]"
            >
                <div class="border-b border-border/60 bg-muted/30 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <Tag class="h-4 w-4 text-primary" />
                        <h2 class="text-sm font-semibold text-foreground">
                            2. Classification & Status
                        </h2>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Determine whether this item is tracked by quantity or by
                        individual property tags.
                    </p>
                </div>

                <div class="grid gap-5 p-5">
                    <!-- Product Type -->
                    <div class="grid gap-2">
                        <Label
                            for="type"
                            class="text-xs font-semibold text-foreground"
                        >
                            Product Type
                            <span class="text-rose-500">*</span>
                        </Label>
                        <select
                            id="type"
                            name="type"
                            v-model="selectedType"
                            data-testid="product-type-input"
                            aria-describedby="type-help type-error"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            required
                        >
                            <option value="consumable">
                                Consumable (issued by quantity)
                            </option>
                            <option value="asset">
                                Asset (individual property tagged)
                            </option>
                        </select>

                        <!-- Type Guidance Banner -->
                        <div
                            class="flex items-start gap-2.5 rounded-lg border p-3 text-xs"
                            :class="
                                selectedType === 'consumable'
                                    ? 'border-amber-500/25 bg-amber-500/10 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300'
                                    : 'border-sky-500/25 bg-sky-500/10 text-sky-800 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-300'
                            "
                        >
                            <Info class="mt-0.5 h-4 w-4 shrink-0" />
                            <div id="type-help">
                                <span v-if="selectedType === 'consumable'">
                                    <strong>Consumable items:</strong> Issued by
                                    quantity on requisitions (e.g. stationery,
                                    lab supplies, toners). Stock is tracked as
                                    aggregated on-hand units with reorder
                                    thresholds.
                                </span>
                                <span v-else>
                                    <strong>Asset items:</strong> Receive
                                    individual property tags, serial numbers,
                                    custodians, and lifecycle tracking (e.g.
                                    laptops, projectors, laboratory equipment).
                                </span>
                            </div>
                        </div>
                        <InputError id="type-error" :message="errors.type" />
                    </div>

                    <!-- Category & Origin Grid -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label
                                for="category_id"
                                class="text-xs font-semibold text-foreground"
                            >
                                Category
                            </Label>
                            <select
                                id="category_id"
                                name="category_id"
                                data-testid="product-category-input"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">None (uncategorized)</option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                            <InputError :message="errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="origin_id"
                                class="text-xs font-semibold text-foreground"
                            >
                                Origin / Acquisition Source
                            </Label>
                            <select
                                id="origin_id"
                                name="origin_id"
                                data-testid="product-origin-input"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">None (unspecified)</option>
                                <option
                                    v-for="s in origins"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                            <InputError :message="errors.origin_id" />
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="grid gap-2">
                        <Label
                            for="is_active"
                            class="text-xs font-semibold text-foreground"
                        >
                            Record Status
                            <span class="text-rose-500">*</span>
                        </Label>
                        <select
                            id="is_active"
                            name="is_active"
                            data-testid="product-status-input"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            required
                        >
                            <option value="1" selected>
                                Active (available for requisitions and
                                receiving)
                            </option>
                            <option value="0">
                                Inactive (hidden from standard selection)
                            </option>
                        </select>
                        <InputError :message="errors.is_active" />
                    </div>
                </div>
            </div>

            <!-- Section 3: Inventory & Replenishment -->
            <div
                class="overflow-hidden rounded-xl border border-border/70 bg-card/95 shadow-2xs ring-1 ring-black/[0.02] dark:border-border/60 dark:bg-card/90 dark:ring-white/[0.03]"
            >
                <div class="border-b border-border/60 bg-muted/30 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <Building2 class="h-4 w-4 text-primary" />
                        <h2 class="text-sm font-semibold text-foreground">
                            3. Procurement & Replenishment
                        </h2>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Configure low-stock alert thresholds, supplier
                        relationships, and default procurement pricing.
                    </p>
                </div>

                <div class="grid gap-5 p-5">
                    <!-- Reorder Threshold -->
                    <div class="grid gap-2">
                        <Label
                            for="reorder_threshold"
                            class="text-xs font-semibold text-foreground"
                        >
                            Reorder Threshold
                        </Label>
                        <div class="relative">
                            <Input
                                id="reorder_threshold"
                                name="reorder_threshold"
                                data-testid="product-reorder-threshold-input"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                aria-describedby="reorder-threshold-help reorder-threshold-error"
                                placeholder="0"
                                class="h-10 rounded-lg text-sm focus-visible:ring-2"
                            />
                        </div>
                        <p
                            id="reorder-threshold-help"
                            class="text-xs text-muted-foreground"
                        >
                            PRISM flags this consumable as low stock when
                            on-hand quantity falls to or below this number.
                        </p>
                        <InputError
                            id="reorder-threshold-error"
                            :message="errors.reorder_threshold"
                        />
                    </div>

                    <!-- Preferred Supplier & Lead Time -->
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label
                                for="supplier_id"
                                class="text-xs font-semibold text-foreground"
                            >
                                Preferred Supplier
                            </Label>
                            <select
                                id="supplier_id"
                                name="supplier_id"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">
                                    None (no preferred supplier)
                                </option>
                                <option
                                    v-for="supplier in suppliers"
                                    :key="supplier.id"
                                    :value="supplier.id"
                                >
                                    {{ supplier.name }}
                                </option>
                            </select>
                            <InputError :message="errors.supplier_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="lead_time_days"
                                class="text-xs font-semibold text-foreground"
                            >
                                Lead Time (Days)
                            </Label>
                            <div class="relative">
                                <Input
                                    id="lead_time_days"
                                    name="lead_time_days"
                                    type="number"
                                    min="0"
                                    inputmode="numeric"
                                    placeholder="e.g. 5"
                                    class="h-10 rounded-lg pr-12 text-sm focus-visible:ring-2"
                                />
                                <span
                                    class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground"
                                >
                                    days
                                </span>
                            </div>
                            <InputError :message="errors.lead_time_days" />
                        </div>
                    </div>

                    <!-- Default Unit Price -->
                    <div class="grid gap-2">
                        <Label
                            for="unit_price"
                            class="text-xs font-semibold text-foreground"
                        >
                            Default Unit Price (PHP)
                        </Label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-mono text-sm text-muted-foreground"
                            >
                                ₱
                            </span>
                            <Input
                                id="unit_price"
                                name="unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="h-10 rounded-lg pl-8 text-sm focus-visible:ring-2"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Estimated or contract reference price for purchase
                            orders and budget allocation.
                        </p>
                        <InputError :message="errors.unit_price" />
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div
                class="flex items-center justify-end gap-3 border-t border-border/40 pt-4"
            >
                <Button
                    variant="outline"
                    as-child
                    class="h-10 rounded-lg px-4 text-xs font-medium"
                >
                    <Link :href="productsIndex()">Cancel</Link>
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="create-product-submit"
                    data-testid="create-product-submit"
                    class="h-10 min-h-[44px] gap-2 rounded-lg px-6 text-sm font-semibold shadow-xs sm:min-h-10"
                >
                    <Plus class="h-4 w-4" />
                    <span>{{
                        processing ? 'Adding product…' : 'Add product'
                    }}</span>
                </Button>
            </div>
        </Form>
    </div>
</template>
