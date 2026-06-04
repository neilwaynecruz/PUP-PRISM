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
}>();

defineOptions({
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
            description="Create a new catalog item."
        />

        <Form
            v-bind="ProductController.store.form()"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="sku">SKU / Barcode</Label>
                <Input
                    id="sku"
                    name="sku"
                    data-testid="product-sku-input"
                    required
                    placeholder="e.g. 4801234567890"
                />
                <InputError :message="errors.sku" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Product name</Label>
                <Input
                    id="name"
                    name="name"
                    data-testid="product-name-input"
                    required
                    placeholder="e.g. 1L Fresh Milk"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="type">Type</Label>
                    <select
                        id="type"
                        name="type"
                        data-testid="product-type-input"
                        class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                        required
                    >
                        <option value="consumable" selected>Consumable</option>
                        <option value="asset">Asset</option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="reorder_threshold">Reorder threshold</Label>
                    <Input
                        id="reorder_threshold"
                        name="reorder_threshold"
                        data-testid="product-reorder-threshold-input"
                        type="number"
                        min="0"
                        placeholder="0"
                    />
                    <InputError :message="errors.reorder_threshold" />
                </div>
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="category_id">Category</Label>
                    <select
                        id="category_id"
                        name="category_id"
                        data-testid="product-category-input"
                        class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
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
                        class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option v-for="s in origins" :key="s.id" :value="s.id">
                            {{ s.name }}
                        </option>
                    </select>
                    <InputError :message="errors.origin_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="is_active">Status</Label>
                <select
                    id="is_active"
                    name="is_active"
                    data-testid="product-status-input"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
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
                    >Create</Button
                >
            </div>
        </Form>
    </div>
</template>
