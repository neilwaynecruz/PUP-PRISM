<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Inventory/ProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as productsIndex,
    show as productsShow,
} from '@/routes/inventory/products';

type Option = { id: number; name: string };

type Product = {
    id: number;
    sku: string;
    name: string;
    category_id: number | null;
    origin_id: number | null;
    supplier_id: number | null;
    type: 'asset' | 'consumable';
    reorder_threshold: number;
    lead_time_days: number | null;
    unit_price: number | null;
    is_active: boolean;
};

const props = defineProps<{
    product: Product;
    categories: Option[];
    origins: Option[];
    suppliers: Option[];
    can: {
        delete: boolean;
    };
}>();

defineOptions({
    name: 'InventoryProductEditPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Products', href: productsIndex() },
            { title: 'Edit', href: productsIndex() },
        ],
    },
});

const reason = ref('');
const dialogOpen = ref(false);

function confirmDelete(): void {
    router.delete(ProductController.destroy(props.product.id).url, {
        data: { deletion_reason: reason.value },
        onSuccess: () => {
            reason.value = '';
            dialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <div class="flex flex-col gap-6 p-4" data-testid="product-edit-page">
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
                <Input
                    id="sku"
                    name="sku"
                    data-testid="product-sku-input"
                    required
                    :default-value="product.sku"
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
                    :default-value="product.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Type</Label>
                    <Input
                        :default-value="product.type"
                        data-testid="product-type-input"
                        disabled
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="reorder_threshold">Reorder threshold</Label>
                    <Input
                        id="reorder_threshold"
                        name="reorder_threshold"
                        data-testid="product-reorder-threshold-input"
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
                        data-testid="product-category-input"
                        class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                        :defaultValue="product.category_id ?? ''"
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

            <div class="grid gap-2 md:grid-cols-3">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="supplier_id">Preferred supplier</Label>
                    <select
                        id="supplier_id"
                        name="supplier_id"
                        class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                        :defaultValue="product.supplier_id ?? ''"
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
                        :default-value="
                            product.lead_time_days !== null
                                ? String(product.lead_time_days)
                                : ''
                        "
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
                    :default-value="
                        product.unit_price !== null
                            ? String(product.unit_price)
                            : ''
                    "
                />
                <InputError :message="errors.unit_price" />
            </div>

            <div class="grid gap-2">
                <Label for="is_active">Status</Label>
                <select
                    id="is_active"
                    name="is_active"
                    data-testid="product-status-input"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
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
                <Dialog v-if="can.delete" v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button
                            type="button"
                            variant="destructive"
                            data-testid="delete-product-button"
                        >
                            Delete
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Move product to trash?</DialogTitle>
                            <DialogDescription>
                                This product will be moved to trash and can be
                                restored later. You may optionally provide a
                                reason for deletion.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="grid gap-2">
                            <Label for="deletion_reason"
                                >Reason (optional)</Label
                            >
                            <textarea
                                id="deletion_reason"
                                v-model="reason"
                                placeholder="Enter a reason for deletion..."
                                rows="3"
                                class="min-h-[80px] rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" @click="reason = ''"
                                    >Cancel</Button
                                >
                            </DialogClose>
                            <Button
                                variant="destructive"
                                @click="confirmDelete"
                            >
                                Move to trash
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Button
                    :disabled="processing"
                    data-test="save-product-button"
                    data-testid="save-product-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>
</template>
