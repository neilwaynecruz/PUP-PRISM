<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Inventory/ProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as productsIndex,
    create as productsCreate,
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
</script>

<template>
    <Head title="New product" />

    <div class="flex flex-col gap-6 p-4" data-testid="product-create-page">
        <Heading
            variant="small"
            title="New product"
            description="Add an asset or consumable to the inventory catalog. Stock is recorded separately when items are received."
        />

        <Form
            v-bind="ProductController.store.form()"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="sku">SKU or barcode</Label>
                <Input
                    id="sku"
                    name="sku"
                    data-testid="product-sku-input"
                    required
                    autocomplete="off"
                    aria-describedby="sku-help sku-error"
                    placeholder="Example: 4801234567890"
                />
                <p id="sku-help" class="text-sm text-muted-foreground">
                    Enter the unique code used to identify this product.
                </p>
                <InputError id="sku-error" :message="errors.sku" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Product name</Label>
                <Input
                    id="name"
                    name="name"
                    data-testid="product-name-input"
                    required
                    aria-describedby="product-name-error"
                    placeholder="Example: Bond paper, A4"
                />
                <InputError id="product-name-error" :message="errors.name" />
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="type">Type</Label>
                    <select
                        id="type"
                        name="type"
                        data-testid="product-type-input"
                        aria-describedby="type-help type-error"
                        class="h-11 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:h-9"
                        required
                    >
                        <option value="consumable" selected>Consumable</option>
                        <option value="asset">Asset</option>
                    </select>
                    <p id="type-help" class="text-sm text-muted-foreground">
                        Consumables are issued by quantity. Assets receive
                        individual property tags.
                    </p>
                    <InputError id="type-error" :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="reorder_threshold">Reorder threshold</Label>
                    <Input
                        id="reorder_threshold"
                        name="reorder_threshold"
                        data-testid="product-reorder-threshold-input"
                        type="number"
                        min="0"
                        inputmode="numeric"
                        aria-describedby="reorder-threshold-help reorder-threshold-error"
                        placeholder="0"
                    />
                    <p
                        id="reorder-threshold-help"
                        class="text-sm text-muted-foreground"
                    >
                        PRISM flags a consumable as low stock at or below this
                        quantity.
                    </p>
                    <InputError
                        id="reorder-threshold-error"
                        :message="errors.reorder_threshold"
                    />
                </div>
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="category_id">Category</Label>
                    <select
                        id="category_id"
                        name="category_id"
                        data-testid="product-category-input"
                        class="h-11 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:h-9"
                    >
                        <option value="">None</option>
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
                    <Label for="origin_id">Origin</Label>
                    <select
                        id="origin_id"
                        name="origin_id"
                        data-testid="product-origin-input"
                        class="h-11 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:h-9"
                    >
                        <option value="">None</option>
                        <option v-for="s in origins" :key="s.id" :value="s.id">
                            {{ s.name }}
                        </option>
                    </select>
                    <InputError :message="errors.origin_id" />
                </div>
            </div>

            <div class="grid gap-2 md:grid-cols-3">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="supplier_id">Preferred supplier</Label>
                    <select
                        id="supplier_id"
                        name="supplier_id"
                        class="h-11 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:h-9"
                    >
                        <option value="">None</option>
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
                    <Label for="lead_time_days">Lead time (days)</Label>
                    <Input
                        id="lead_time_days"
                        name="lead_time_days"
                        type="number"
                        min="0"
                        inputmode="numeric"
                        placeholder="Example: 5"
                    />
                    <InputError :message="errors.lead_time_days" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="unit_price">Default unit price</Label>
                <Input
                    id="unit_price"
                    name="unit_price"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="Example: 125.50"
                />
                <InputError :message="errors.unit_price" />
            </div>

            <div class="grid gap-2">
                <Label for="is_active">Status</Label>
                <select
                    id="is_active"
                    name="is_active"
                    data-testid="product-status-input"
                    class="h-11 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none sm:h-9"
                    required
                >
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                </select>
                <InputError :message="errors.is_active" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="productsIndex()">Cancel</Link>
                </Button>
                <Button
                    :disabled="processing"
                    data-test="create-product-submit"
                    data-testid="create-product-submit"
                    >{{
                        processing ? 'Creating Product…' : 'Create Product'
                    }}</Button
                >
            </div>
        </Form>
    </div>
</template>
