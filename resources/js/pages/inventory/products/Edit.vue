<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Inventory/ProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as productsIndex, show as productsShow } from '@/routes/inventory/products';

type Option = { id: number; name: string };

type Product = {
    id: number;
    sku: string;
    name: string;
    category_id: number | null;
    origin_id: number | null;
    type: 'asset' | 'consumable';
    reorder_threshold: number;
    is_active: boolean;
};

defineProps<{
    product: Product;
    categories: Option[];
    origins: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Products', href: productsIndex() },
            { title: 'Edit', href: productsIndex() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            :title="`Edit product`"
            :description="product.name"
        />

        <Form
            v-bind="ProductController.update.form(product.id)"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="sku">SKU / Barcode</Label>
                <Input id="sku" name="sku" required :default-value="product.sku" />
                <InputError :message="errors.sku" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Product name</Label>
                <Input id="name" name="name" required :default-value="product.name" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Type</Label>
                    <Input :default-value="product.type" disabled />
                </div>

                <div class="grid gap-2">
                    <Label for="reorder_threshold">Reorder threshold</Label>
                    <Input
                        id="reorder_threshold"
                        name="reorder_threshold"
                        type="number"
                        min="0"
                        :default-value="String(product.reorder_threshold ?? 0)"
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
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        :defaultValue="product.category_id ?? ''"
                    >
                        <option value="">None</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">
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
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        :defaultValue="product.origin_id ?? ''"
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
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    :defaultValue="product.is_active ? '1' : '0'"
                    required
                >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
                <InputError :message="errors.is_active" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="productsShow(product.id)">View</Link>
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="productsIndex()">Back to list</Link>
                </Button>
                <Button :disabled="processing">Save</Button>
            </div>
        </Form>
    </div>
</template>

