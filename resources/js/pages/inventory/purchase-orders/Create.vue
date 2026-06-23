<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    create as purchaseOrdersCreate,
    index as purchaseOrdersIndex,
} from '@/routes/inventory/purchase-orders';

type SupplierOption = {
    id: number;
    name: string;
};

type ProductOption = {
    id: number;
    sku: string;
    name: string;
    type: 'asset' | 'consumable';
    supplier_id: number | null;
    supplier: string | null;
    unit_price: number | null;
};

type DraftLine = {
    product_id: string;
    qty_ordered: string;
    unit_price: string;
};

const props = defineProps<{
    suppliers: SupplierOption[];
    products: ProductOption[];
}>();

defineOptions({
    name: 'InventoryPurchaseOrderCreatePage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: purchaseOrdersIndex() },
            { title: 'Purchase Orders', href: purchaseOrdersIndex() },
            { title: 'New', href: purchaseOrdersCreate() },
        ],
    },
});

const form = useForm({
    supplier_id: '',
    expected_delivery_at: '',
    tax: '',
    notes: '',
    lines: [
        {
            product_id: '',
            qty_ordered: '',
            unit_price: '',
        },
    ] as DraftLine[],
});

const filteredProducts = computed(() => {
    if (!form.supplier_id) {
        return props.products;
    }

    return props.products.filter(
        (product) =>
            product.supplier_id === null ||
            String(product.supplier_id) === form.supplier_id,
    );
});

function productById(productId: string): ProductOption | undefined {
    return props.products.find((product) => String(product.id) === productId);
}

function addLine(): void {
    form.lines.push({
        product_id: '',
        qty_ordered: '',
        unit_price: '',
    });
}

function removeLine(index: number): void {
    if (form.lines.length <= 1) {
        return;
    }

    form.lines.splice(index, 1);
}

function onProductChange(index: number): void {
    const product = productById(form.lines[index]?.product_id ?? '');

    if (!product) {
        return;
    }

    form.lines[index].unit_price =
        product.unit_price !== null ? String(product.unit_price) : '';
}

function submit(): void {
    router.post(
        PurchaseOrderController.store().url,
        {
            supplier_id: Number(form.supplier_id),
            expected_delivery_at: form.expected_delivery_at || null,
            tax: form.tax === '' ? null : Number(form.tax),
            notes: form.notes || null,
            lines: form.lines.map((line) => ({
                product_id: Number(line.product_id),
                qty_ordered: Number(line.qty_ordered),
                unit_price:
                    line.unit_price === '' ? null : Number(line.unit_price),
            })),
        },
        {
            onStart: () => form.clearErrors(),
            onError: (errors) => form.setError(errors),
        },
    );
}
</script>

<template>
    <Head title="New Purchase Order" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="New purchase order"
            description="Create a supplier draft with ordered quantities and negotiated pricing."
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="supplier_id">Supplier</Label>
                    <select
                        id="supplier_id"
                        v-model="form.supplier_id"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                        required
                    >
                        <option value="">Select supplier</option>
                        <option
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            :value="String(supplier.id)"
                        >
                            {{ supplier.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.supplier_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="expected_delivery_at">Expected delivery</Label>
                    <Input
                        id="expected_delivery_at"
                        v-model="form.expected_delivery_at"
                        type="datetime-local"
                    />
                    <InputError :message="form.errors.expected_delivery_at" />
                </div>

                <div class="grid gap-2">
                    <Label for="tax">Tax / adjustments</Label>
                    <Input
                        id="tax"
                        v-model="form.tax"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="form.errors.tax" />
                </div>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold tracking-tight">Line items</div>
                    <Button type="button" variant="outline" size="sm" @click="addLine">
                        Add line
                    </Button>
                </div>

                <div class="grid gap-4">
                    <div
                        v-for="(line, index) in form.lines"
                        :key="index"
                        class="grid gap-3 rounded-lg border border-border/50 p-4 lg:grid-cols-[2fr_0.8fr_0.8fr_auto]"
                    >
                        <div class="grid gap-2">
                            <Label :for="`line-product-${index}`">Product</Label>
                            <select
                                :id="`line-product-${index}`"
                                v-model="line.product_id"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                                required
                                @change="onProductChange(index)"
                            >
                                <option value="">Select product</option>
                                <option
                                    v-for="product in filteredProducts"
                                    :key="product.id"
                                    :value="String(product.id)"
                                >
                                    {{ product.name }} ({{ product.sku }})
                                </option>
                            </select>
                            <InputError
                                :message="
                                    form.errors[`lines.${index}.product_id`]
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`line-qty-${index}`">Quantity</Label>
                            <Input
                                :id="`line-qty-${index}`"
                                v-model="line.qty_ordered"
                                type="number"
                                min="1"
                                required
                            />
                            <InputError
                                :message="
                                    form.errors[`lines.${index}.qty_ordered`]
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`line-price-${index}`">Unit price</Label>
                            <Input
                                :id="`line-price-${index}`"
                                v-model="line.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                            <InputError
                                :message="
                                    form.errors[`lines.${index}.unit_price`]
                                "
                            />
                        </div>

                        <div class="flex items-end">
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-rose-600"
                                @click="removeLine(index)"
                            >
                                Remove
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    class="min-h-24 rounded-lg border border-input bg-background px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="purchaseOrdersIndex()">Cancel</Link>
                </Button>
                <Button :disabled="form.processing">Create draft</Button>
            </div>
        </form>
    </div>
</template>
