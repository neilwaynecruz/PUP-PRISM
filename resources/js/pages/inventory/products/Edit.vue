<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Boxes,
    Building2,
    Eye,
    Package,
    Save,
    Tag,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Inventory/ProductController';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
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
type UnitOption = Option & { abbreviation: string };

type Product = {
    id: number;
    sku: string;
    name: string;
    category_id: number | null;
    origin_id: number | null;
    supplier_id: number | null;
    unit_of_measurement_id: number | null;
    variant: string | null;
    brand: string | null;
    model: string | null;
    color: string | null;
    size: string | null;
    type: 'asset' | 'consumable';
    reorder_threshold: number;
    lead_time_days: number | null;
    unit_price: number | null;
    is_active: boolean;
    is_bookable: boolean;
};

const props = defineProps<{
    product: Product;
    categories: Option[];
    origins: Option[];
    suppliers: Option[];
    unitsOfMeasurement: UnitOption[];
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

    <div
        class="mx-auto flex max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="product-edit-page"
    >
        <!-- Header & Identity Context -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <Heading
                        variant="small"
                        title="Edit product"
                        :description="product.name"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <AdminCodeBadge :value="product.sku" />
                    <span
                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase"
                        :class="
                            product.type === 'consumable'
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                        "
                    >
                        <Boxes
                            v-if="product.type === 'consumable'"
                            class="h-3 w-3"
                        />
                        <Tag v-else class="h-3 w-3" />
                        {{ product.type }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                        :class="
                            product.is_active
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                product.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-muted-foreground/40'
                            "
                        />
                        {{ product.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>

            <!-- Quick Navigation -->
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-lg text-xs"
                >
                    <Link :href="productsShow(product.id)">
                        <Eye class="h-3.5 w-3.5" />
                        <span>View details</span>
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="h-9 gap-1.5 rounded-lg text-xs text-muted-foreground hover:text-foreground"
                >
                    <Link :href="productsIndex()">
                        <ArrowLeft class="h-3.5 w-3.5" />
                        <span>Catalog</span>
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Edit Form -->
        <Form
            v-bind="ProductController.update.form(product.id)"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <!-- Form Validation Summary Banner -->
            <div
                v-if="Object.keys(errors).length > 0"
                class="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
                role="alert"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <AlertCircle class="h-4 w-4 shrink-0" />
                    <span
                        >Unable to save changes. Please review the errors
                        below:</span
                    >
                </div>
                <ul
                    class="mt-2 list-inside list-disc space-y-1 text-xs text-destructive/90"
                >
                    <li v-for="(errMsg, errField) in errors" :key="errField">
                        {{ errMsg }}
                    </li>
                </ul>
            </div>

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
                        Update the unique SKU identifier or product catalog
                        title.
                    </p>
                </div>

                <div class="grid gap-5 p-5">
                    <!-- SKU / QR Identifier -->
                    <div class="grid gap-2">
                        <Label
                            for="sku"
                            class="text-xs font-semibold text-foreground"
                        >
                            SKU / QR Identifier
                            <span class="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="sku"
                            name="sku"
                            data-testid="product-sku-input"
                            required
                            :default-value="product.sku"
                            class="h-10 rounded-lg font-mono text-sm uppercase focus-visible:ring-2"
                        />
                        <p class="text-xs text-muted-foreground">
                            Unique alphanumeric code identifying this item.
                            Modifying this affects future QR label printing and
                            scanning.
                        </p>
                        <InputError :message="errors.sku" />
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
                            :default-value="product.name"
                            class="h-10 rounded-lg text-sm focus-visible:ring-2"
                        />
                        <InputError :message="errors.name" />
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
                        Category classification and active availability.
                    </p>
                </div>

                <div class="grid gap-5 p-5">
                    <!-- Type (Disabled with clear explanation) -->
                    <div class="grid gap-2">
                        <Label
                            for="type-disabled"
                            class="text-xs font-semibold text-foreground"
                        >
                            Product Type (Locked)
                        </Label>
                        <input
                            type="hidden"
                            name="type"
                            :value="product.type"
                        />
                        <Input
                            id="type-disabled"
                            :default-value="product.type"
                            data-testid="product-type-input"
                            disabled
                            class="h-10 cursor-not-allowed rounded-lg bg-muted/50 font-medium text-foreground capitalize opacity-90"
                        />
                        <p class="text-xs text-muted-foreground">
                            Product classification (Consumable vs Asset) cannot
                            be changed after creation because existing inventory
                            stock lots and property tags depend on it.
                        </p>
                        <InputError :message="errors.type" />
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
                                :value="product.category_id ?? ''"
                            >
                                <option
                                    value=""
                                    :selected="!product.category_id"
                                >
                                    None (uncategorized)
                                </option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                    :selected="c.id === product.category_id"
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
                                :value="product.origin_id ?? ''"
                            >
                                <option value="" :selected="!product.origin_id">
                                    None (unspecified)
                                </option>
                                <option
                                    v-for="s in origins"
                                    :key="s.id"
                                    :value="s.id"
                                    :selected="s.id === product.origin_id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                            <InputError :message="errors.origin_id" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label
                                for="unit_of_measurement_id"
                                class="text-xs font-semibold text-foreground"
                                >Unit of measurement</Label
                            >
                            <select
                                id="unit_of_measurement_id"
                                name="unit_of_measurement_id"
                                :value="product.unit_of_measurement_id ?? ''"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option
                                    value=""
                                    :selected="!product.unit_of_measurement_id"
                                >
                                    Not specified
                                </option>
                                <option
                                    v-for="unit in unitsOfMeasurement"
                                    :key="unit.id"
                                    :value="unit.id"
                                    :selected="
                                        unit.id ===
                                        product.unit_of_measurement_id
                                    "
                                >
                                    {{ unit.name }} ({{ unit.abbreviation }})
                                </option>
                            </select>
                            <InputError
                                :message="errors.unit_of_measurement_id"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="variant"
                                class="text-xs font-semibold text-foreground"
                                >Variant or specification</Label
                            >
                            <Input
                                id="variant"
                                name="variant"
                                :default-value="product.variant ?? ''"
                                placeholder="e.g. A4 · 80 gsm · 500 sheets"
                                class="h-10 rounded-lg"
                            />
                            <InputError :message="errors.variant" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <Label
                                for="brand"
                                class="text-xs font-semibold text-foreground"
                                >Brand</Label
                            >
                            <Input
                                id="brand"
                                name="brand"
                                :default-value="product.brand ?? ''"
                                class="h-10 rounded-lg"
                            />
                            <InputError :message="errors.brand" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="model"
                                class="text-xs font-semibold text-foreground"
                                >Model</Label
                            >
                            <Input
                                id="model"
                                name="model"
                                :default-value="product.model ?? ''"
                                class="h-10 rounded-lg"
                            />
                            <InputError :message="errors.model" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="color"
                                class="text-xs font-semibold text-foreground"
                                >Color</Label
                            >
                            <Input
                                id="color"
                                name="color"
                                :default-value="product.color ?? ''"
                                class="h-10 rounded-lg"
                            />
                            <InputError :message="errors.color" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="size"
                                class="text-xs font-semibold text-foreground"
                                >Size / capacity</Label
                            >
                            <Input
                                id="size"
                                name="size"
                                :default-value="product.size ?? ''"
                                class="h-10 rounded-lg"
                            />
                            <InputError :message="errors.size" />
                        </div>
                    </div>

                    <label
                        v-if="product.type === 'asset'"
                        class="flex min-h-11 items-center gap-3 rounded-lg border border-border/70 bg-muted/20 px-3 text-sm"
                    >
                        <input type="hidden" name="is_bookable" value="0" />
                        <input
                            type="checkbox"
                            name="is_bookable"
                            value="1"
                            :checked="product.is_bookable"
                            class="size-4 accent-primary"
                        />
                        <span
                            ><strong class="font-medium"
                                >Bookable resource</strong
                            ><span class="block text-xs text-muted-foreground"
                                >Allow availability-managed use when individual
                                assets are serviceable.</span
                            ></span
                        >
                    </label>

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
                            :value="product.is_active ? '1' : '0'"
                            required
                        >
                            <option value="1" :selected="product.is_active">
                                Active (available for requisitions and
                                receiving)
                            </option>
                            <option value="0" :selected="!product.is_active">
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
                        <Input
                            id="reorder_threshold"
                            name="reorder_threshold"
                            data-testid="product-reorder-threshold-input"
                            type="number"
                            min="0"
                            :default-value="
                                String(product.reorder_threshold ?? 0)
                            "
                            class="h-10 rounded-lg text-sm focus-visible:ring-2"
                        />
                        <p class="text-xs text-muted-foreground">
                            Consumable inventory is flagged as low stock when
                            on-hand quantity falls to or below this count.
                        </p>
                        <InputError :message="errors.reorder_threshold" />
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
                                :value="product.supplier_id ?? ''"
                            >
                                <option
                                    value=""
                                    :selected="!product.supplier_id"
                                >
                                    None (no preferred supplier)
                                </option>
                                <option
                                    v-for="supplier in suppliers"
                                    :key="supplier.id"
                                    :value="supplier.id"
                                    :selected="
                                        supplier.id === product.supplier_id
                                    "
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
                                    :default-value="
                                        product.lead_time_days !== null
                                            ? String(product.lead_time_days)
                                            : ''
                                    "
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
                                :default-value="
                                    product.unit_price !== null
                                        ? String(product.unit_price)
                                        : ''
                                "
                                class="h-10 rounded-lg pl-8 text-sm focus-visible:ring-2"
                            />
                        </div>
                        <InputError :message="errors.unit_price" />
                    </div>
                </div>
            </div>

            <!-- Primary Form Actions -->
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
                    data-test="save-product-button"
                    data-testid="save-product-button"
                    class="h-10 min-h-[44px] gap-2 rounded-lg px-6 text-sm font-semibold shadow-xs sm:min-h-10"
                >
                    <Save class="h-4 w-4" />
                    <span>{{
                        processing ? 'Saving changes…' : 'Save changes'
                    }}</span>
                </Button>
            </div>
        </Form>

        <!-- Danger Zone (Visually Separated) -->
        <div
            v-if="can.delete"
            class="overflow-hidden rounded-xl border border-rose-500/30 bg-rose-50/20 shadow-2xs dark:border-rose-950 dark:bg-rose-950/20"
        >
            <div
                class="border-b border-rose-500/20 px-5 py-3.5 dark:border-rose-950"
            >
                <h3
                    class="text-sm font-semibold text-rose-700 dark:text-rose-400"
                >
                    Danger Zone
                </h3>
            </div>
            <div
                class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <p class="text-sm font-medium text-foreground">
                        Move product to trash
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Remove this product from the active catalog. It can be
                        restored or permanently removed from the trash by an
                        administrator.
                    </p>
                </div>

                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button
                            type="button"
                            variant="destructive"
                            data-testid="delete-product-button"
                            class="h-10 min-h-[44px] shrink-0 gap-2 rounded-lg px-4 text-xs font-semibold sm:min-h-10"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>Move to trash</span>
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader class="space-y-2">
                            <DialogTitle
                                class="flex items-center gap-2 text-rose-600 dark:text-rose-400"
                            >
                                <Trash2 class="h-5 w-5" />
                                <span>Move product to trash?</span>
                            </DialogTitle>
                            <DialogDescription
                                class="text-sm text-muted-foreground"
                            >
                                This will move
                                <strong class="text-foreground">{{
                                    product.name
                                }}</strong>
                                <span class="font-mono text-xs">
                                    ({{ product.sku }})</span
                                >
                                to trash. You may optionally provide a reason
                                for records auditing.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-4 py-2">
                            <div class="grid gap-2">
                                <Label
                                    for="deletion_reason"
                                    class="text-xs font-semibold"
                                >
                                    Reason for deletion (optional)
                                </Label>
                                <textarea
                                    id="deletion_reason"
                                    v-model="reason"
                                    placeholder="e.g. Discontinued model, replaced by newer standard..."
                                    rows="3"
                                    class="flex min-h-[80px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                            </div>
                        </div>

                        <DialogFooter class="gap-2 sm:justify-end">
                            <DialogClose as-child>
                                <Button
                                    variant="outline"
                                    class="rounded-lg"
                                    @click="reason = ''"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                class="rounded-lg"
                                @click="confirmDelete"
                            >
                                Move to trash
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </div>
</template>
