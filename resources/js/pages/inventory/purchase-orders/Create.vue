<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    BookOpen,
    Building2,
    Calendar,
    CheckCircle2,
    FileSpreadsheet,
    Minus,
    Package,
    Plus,
    Search,
    Sparkles,
    Trash2,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OcrAssistButton from '@/components/inventory/OcrAssistButton.vue';
import type { ProductLookupItem } from '@/components/inventory/ProductSelectDialog.vue';
import ProductSelectDialog from '@/components/inventory/ProductSelectDialog.vue';
import { Badge } from '@/components/ui/badge';

const QrScannerDialog = defineAsyncComponent(
    () => import('@/components/inventory/QrScannerDialog.vue'),
);
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProcurementOcrDraft } from '@/lib/ocrReview';
import {
    create as purchaseOrdersCreate,
    index as purchaseOrdersIndex,
} from '@/routes/inventory/purchase-orders';

type SupplierOption = {
    id: number;
    name: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    payment_terms?: string | null;
    lead_time_days?: number | null;
};

type ProductOption = {
    id: number;
    sku: string;
    name: string;
    type?: string | null;
    category?: string | null;
    supplier_id?: number | null;
    supplier?: string | null;
    unit_price?: number | null;
    on_hand_qty?: number | null;
    reorder_threshold?: number | null;
};

type InitialLine = {
    product_id: string;
    qty_ordered: string;
    unit_price: string;
};

const props = defineProps<{
    suppliers: SupplierOption[];
    products: ProductOption[];
    initialSupplierId?: number | null;
    initialLines?: InitialLine[] | null;
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
    supplier_id: props.initialSupplierId ? String(props.initialSupplierId) : '',
    expected_delivery_at: '',
    tax: '0',
    notes: '',
    lines: props.initialLines?.length
        ? props.initialLines.map((line) => ({ ...line }))
        : [
              {
                  product_id: '',
                  qty_ordered: '1',
                  unit_price: '',
              },
          ],
});

const quickEntryQuery = ref('');
const procurementDraft = ref<ProcurementOcrDraft | null>(null);
const documentNotice = ref('');
const documentSuppliers = computed(() => {
    const query = procurementDraft.value?.supplier.trim().toLowerCase();

    return query
        ? props.suppliers
              .filter((supplier) => supplier.name.toLowerCase().includes(query))
              .slice(0, 10)
        : [];
});

function addDocumentNote(): void {
    const draft = procurementDraft.value;

    if (!draft) {
        return;
    }

    const note = `${draft.documentType.replaceAll('_', ' ')}${draft.reference ? ` reference: ${draft.reference}` : ''}${draft.documentDate ? `; document date: ${draft.documentDate}` : ''}`;

    if (!form.notes.split('\n').includes(note)) {
        form.notes = [form.notes, note].filter(Boolean).join('\n');
    }

    documentNotice.value =
        'Reviewed source reference added to draft notes. Delivery date, quantities, prices, and tax were not changed.';
}
const quickEntryQty = ref(1);
const showQuickSuggestions = ref(false);
const isCatalogOpen = ref(false);
const actionNotice = ref<{
    type: 'info' | 'warning' | 'success';
    text: string;
} | null>(null);

function showNotice(
    text: string,
    type: 'info' | 'warning' | 'success' = 'info',
): void {
    actionNotice.value = { type, text };
    setTimeout(() => {
        if (actionNotice.value?.text === text) {
            actionNotice.value = null;
        }
    }, 4500);
}

const selectedSupplier = computed<SupplierOption | null>(() => {
    const id = Number(form.supplier_id || 0);

    if (!id) {
        return null;
    }

    return props.suppliers.find((s) => s.id === id) ?? null;
});

const filteredProducts = computed(() => {
    const selectedSupplierId = Number(form.supplier_id || 0);

    if (!selectedSupplierId) {
        return props.products;
    }

    return props.products.filter(
        (product) =>
            !product.supplier_id || product.supplier_id === selectedSupplierId,
    );
});

const quickSuggestions = computed(() => {
    const q = quickEntryQuery.value.trim().toLowerCase();

    if (!q) {
        return filteredProducts.value.slice(0, 6);
    }

    return filteredProducts.value
        .filter(
            (product) =>
                product.sku.toLowerCase().includes(q) ||
                product.name.toLowerCase().includes(q) ||
                (product.category ?? '').toLowerCase().includes(q),
        )
        .slice(0, 8);
});

const selectedSkus = computed<string[]>(() => {
    return form.lines
        .map((line) => findProductById(line.product_id)?.sku ?? '')
        .filter(Boolean);
});

function findProductById(productId: string | number): ProductOption | null {
    const numericId = Number(productId || 0);

    if (!numericId) {
        return null;
    }

    return props.products.find((item) => item.id === numericId) ?? null;
}

function findProductByCodeOrQuery(rawValue: string): ProductOption | null {
    const normalized = rawValue.trim().toLowerCase();

    if (!normalized) {
        return null;
    }

    // 1. Exact SKU match
    const exactSku = props.products.find(
        (item) => item.sku.toLowerCase() === normalized,
    );

    if (exactSku) {
        return exactSku;
    }

    // 2. Exact name match
    const exactName = filteredProducts.value.find(
        (item) => item.name.toLowerCase() === normalized,
    );

    if (exactName) {
        return exactName;
    }

    // 3. First suggestion in filtered products
    return quickSuggestions.value[0] ?? null;
}

function applyLeadTimeDate(): void {
    const days = selectedSupplier.value?.lead_time_days;
    const offset = days !== null && days !== undefined && days > 0 ? days : 7;
    const target = new Date();
    target.setDate(target.getDate() + offset);
    form.expected_delivery_at = target.toISOString().slice(0, 10);
}

function addOrIncrementProduct(
    product: ProductOption,
    quantityToAdd = 1,
): void {
    const qty = Math.max(1, Math.trunc(Number(quantityToAdd) || 1));

    // Check supplier compatibility
    const currentSupplierId = Number(form.supplier_id || 0);

    if (!currentSupplierId && product.supplier_id) {
        form.supplier_id = String(product.supplier_id);
    } else if (
        currentSupplierId &&
        product.supplier_id &&
        product.supplier_id !== currentSupplierId
    ) {
        showNotice(
            `${product.sku} (${product.name}) is assigned to ${product.supplier ?? 'another supplier'} and cannot be mixed into this supplier's PO.`,
            'warning',
        );

        return;
    }

    const productIdStr = String(product.id);
    const existingLineIndex = form.lines.findIndex(
        (line) => line.product_id === productIdStr,
    );

    if (existingLineIndex !== -1) {
        const existingLine = form.lines[existingLineIndex];
        const nextQty =
            Math.max(0, Number(existingLine.qty_ordered) || 0) + qty;
        existingLine.qty_ordered = String(nextQty);
        showNotice(
            `Updated ${product.sku} — quantity increased to ${nextQty}.`,
            'success',
        );

        return;
    }

    const defaultUnitPrice =
        product.unit_price !== null && product.unit_price !== undefined
            ? String(Number(product.unit_price).toFixed(2))
            : '';

    // Reuse an empty initial row if present
    const emptyLineIndex = form.lines.findIndex((line) => !line.product_id);

    if (emptyLineIndex !== -1) {
        form.lines[emptyLineIndex] = {
            product_id: productIdStr,
            qty_ordered: String(qty),
            unit_price: defaultUnitPrice,
        };
    } else {
        form.lines.push({
            product_id: productIdStr,
            qty_ordered: String(qty),
            unit_price: defaultUnitPrice,
        });
    }

    showNotice(`Added ${product.sku} (${product.name}) × ${qty}.`, 'success');
}

function handleQuickAdd(): void {
    const match = findProductByCodeOrQuery(quickEntryQuery.value);

    if (!match) {
        showNotice(
            `No active product matched "${quickEntryQuery.value.trim()}". Try browsing the catalog.`,
            'warning',
        );

        return;
    }

    addOrIncrementProduct(match, quickEntryQty.value);
    quickEntryQuery.value = '';
    quickEntryQty.value = 1;
    showQuickSuggestions.value = false;
}

function selectQuickSuggestion(product: ProductOption): void {
    addOrIncrementProduct(product, quickEntryQty.value);
    quickEntryQuery.value = '';
    quickEntryQty.value = 1;
    showQuickSuggestions.value = false;
}

function handleScannedCode(rawCode: string): void {
    const cleaned = rawCode.trim();
    const product = props.products.find(
        (item) => item.sku.toLowerCase() === cleaned.toLowerCase(),
    );

    if (!product) {
        showNotice(
            `Scanned code "${cleaned}" did not match any active product SKU.`,
            'warning',
        );

        return;
    }

    addOrIncrementProduct(product, 1);
}

function handleCatalogSelect(
    lookupItem: ProductLookupItem,
    quantity: number,
): void {
    const product = props.products.find(
        (item) => item.id === lookupItem.id,
    ) ?? {
        id: lookupItem.id,
        sku: lookupItem.sku,
        name: lookupItem.name,
        type: lookupItem.type,
        category: lookupItem.category,
        unit_price: lookupItem.unit_price,
        on_hand_qty: lookupItem.on_hand_qty,
        reorder_threshold: lookupItem.reorder_threshold,
    };

    addOrIncrementProduct(product, quantity);
}

function addLine(): void {
    form.lines.push({
        product_id: '',
        qty_ordered: '1',
        unit_price: '',
    });
}

function removeLine(index: number): void {
    if (form.lines.length <= 1) {
        form.lines[0] = {
            product_id: '',
            qty_ordered: '1',
            unit_price: '',
        };

        return;
    }

    form.lines.splice(index, 1);
}

function adjustLineQty(index: number, delta: number): void {
    const line = form.lines[index];

    if (!line) {
        return;
    }

    const current = Math.max(1, Number(line.qty_ordered) || 1);
    line.qty_ordered = String(Math.max(1, current + delta));
}

function onProductChange(index: number): void {
    const line = form.lines[index];

    if (!line || !line.product_id) {
        return;
    }

    // Prevent duplicate product selection across rows
    const duplicateIndex = form.lines.findIndex(
        (otherLine, otherIdx) =>
            otherIdx !== index && otherLine.product_id === line.product_id,
    );

    const product = findProductById(line.product_id);

    if (duplicateIndex !== -1) {
        const duplicateLine = form.lines[duplicateIndex];
        const mergedQty =
            (Number(duplicateLine.qty_ordered) || 1) +
            (Number(line.qty_ordered) || 1);
        duplicateLine.qty_ordered = String(mergedQty);

        if (form.lines.length > 1) {
            form.lines.splice(index, 1);
        } else {
            line.product_id = '';
        }

        if (product) {
            showNotice(
                `${product.sku} was already on line ${duplicateIndex + 1}. Merged quantity to ${mergedQty}.`,
                'info',
            );
        }

        return;
    }

    if (!product) {
        return;
    }

    if (!form.supplier_id && product.supplier_id) {
        form.supplier_id = String(product.supplier_id);
    }

    if (
        (!line.unit_price || line.unit_price === '') &&
        product.unit_price !== null &&
        product.unit_price !== undefined
    ) {
        line.unit_price = String(Number(product.unit_price).toFixed(2));
    }
}

function lineSubtotal(line: {
    qty_ordered: string;
    unit_price: string;
    product_id: string;
}): number {
    const qty = Math.max(0, Number(line.qty_ordered) || 0);
    const fallbackPrice = findProductById(line.product_id)?.unit_price ?? 0;
    const price =
        line.unit_price !== ''
            ? Math.max(0, Number(line.unit_price) || 0)
            : Number(fallbackPrice || 0);

    return qty * price;
}

const populatedLineCount = computed(
    () => form.lines.filter((line) => Boolean(line.product_id)).length,
);

const totalUnitsOrdered = computed(() =>
    form.lines.reduce((sum, line) => {
        if (!line.product_id) {
            return sum;
        }

        return sum + Math.max(0, Number(line.qty_ordered) || 0);
    }, 0),
);

const orderSubtotal = computed(() =>
    form.lines.reduce((sum, line) => {
        if (!line.product_id) {
            return sum;
        }

        return sum + lineSubtotal(line);
    }, 0),
);

const parsedTax = computed(() => Math.max(0, Number(form.tax) || 0));

const orderGrandTotal = computed(() => orderSubtotal.value + parsedTax.value);

function formatMoney(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(value || 0);
}

function hideSuggestionsDelayed(): void {
    setTimeout(() => {
        showQuickSuggestions.value = false;
    }, 180);
}

function submitPurchaseOrder(): void {
    form.transform((data) => ({
        supplier_id: Number(data.supplier_id),
        expected_delivery_at: data.expected_delivery_at || null,
        tax: data.tax !== '' ? Number(data.tax) : 0,
        notes: data.notes.trim() !== '' ? data.notes.trim() : null,
        lines: data.lines.map((line) => ({
            product_id: Number(line.product_id),
            qty_ordered: Number(line.qty_ordered),
            unit_price: line.unit_price !== '' ? Number(line.unit_price) : null,
        })),
    })).post(PurchaseOrderController.store().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <Head title="New Purchase Order" />

        <!-- Header Banner -->
        <div
            class="relative overflow-hidden rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-52 rounded-full bg-primary/5 blur-2xl"
            />
            <div
                class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-2xs"
                    >
                        <FileSpreadsheet class="size-5" />
                    </div>
                    <Heading
                        title="New purchase order"
                        description="Create a draft supplier purchase order by searching, scanning, or browsing products."
                    />
                </div>
                <Button
                    as-child
                    variant="outline"
                    class="w-full shrink-0 sm:w-auto"
                >
                    <Link :href="purchaseOrdersIndex()">
                        <ArrowLeft class="mr-1.5 size-4" />
                        Back to purchase orders
                    </Link>
                </Button>
            </div>
        </div>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
            @submit.prevent="submitPurchaseOrder"
        >
            <!-- Left Main Column -->
            <div class="flex flex-col gap-6">
                <section
                    class="grid min-w-0 gap-3 rounded-2xl border border-border bg-card p-5"
                    aria-labelledby="procurement-ocr-title"
                >
                    <h2 id="procurement-ocr-title" class="font-semibold">
                        Procurement document assistance
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Read a quotation, invoice, or delivery-receipt image.
                        Review fields, then explicitly match existing records.
                        Selected PDF pages and images are supported. No table,
                        price, or quantity extraction.
                    </p>
                    <OcrAssistButton
                        label="Read procurement document"
                        title="Review procurement document"
                        description="Review source text and correct suggested fields. Ambiguous dates must be corrected; nothing creates or submits an order."
                        submit-label="Review draft matches"
                        help="Only draft suggestions are returned. Match existing suppliers and products, then complete the normal purchase-order workflow."
                        document
                        @document="
                            procurementDraft = $event;
                            documentNotice = '';
                        "
                    />
                    <div
                        v-if="procurementDraft"
                        class="grid min-w-0 gap-3"
                        data-testid="procurement-ocr-draft"
                    >
                        <p class="text-sm break-words">
                            Reviewed
                            {{
                                procurementDraft.documentType.replaceAll(
                                    '_',
                                    ' ',
                                )
                            }}
                            · Reference:
                            {{ procurementDraft.reference || 'unresolved' }} ·
                            Document date:
                            {{ procurementDraft.documentDate || 'unresolved' }}
                        </p>
                        <Button
                            v-if="
                                procurementDraft.reference ||
                                procurementDraft.documentDate
                            "
                            type="button"
                            variant="outline"
                            class="min-h-11 whitespace-normal"
                            @click="addDocumentNote"
                            >Add reviewed reference to order notes</Button
                        >
                        <p class="text-sm">
                            Supplier text:
                            {{ procurementDraft.supplier || 'unresolved' }}.
                            Choose a matching supplier explicitly:
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="supplier in documentSuppliers"
                                :key="supplier.id"
                                type="button"
                                variant="outline"
                                class="min-h-11 max-w-full whitespace-normal"
                                @click="
                                    form.supplier_id = String(supplier.id);
                                    documentNotice =
                                        'Supplier selected. Review the order lines before saving.';
                                "
                                >{{ supplier.name }}</Button
                            >
                        </div>
                        <p
                            v-if="!documentSuppliers.length"
                            class="text-sm text-muted-foreground"
                        >
                            No supplier match. Use the supplier selector below;
                            no supplier has been created.
                        </p>
                        <Button
                            v-if="procurementDraft.productCode"
                            type="button"
                            variant="outline"
                            class="min-h-11 whitespace-normal"
                            @click="
                                quickEntryQuery = procurementDraft.productCode;
                                showQuickSuggestions = true;
                                documentNotice =
                                    'Product search filled. Select the correct catalog item; no line was added.';
                            "
                            >Search reviewed code:
                            {{ procurementDraft.productCode }}</Button
                        >
                        <Button
                            type="button"
                            variant="ghost"
                            class="min-h-11"
                            @click="procurementDraft = null"
                            >Discard document suggestions</Button
                        >
                    </div>
                    <p
                        v-if="documentNotice"
                        role="status"
                        class="text-sm text-muted-foreground"
                    >
                        {{ documentNotice }}
                    </p>
                </section>
                <!-- Section 1: Supplier & Order Parameters -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <div
                        class="mb-5 flex items-center gap-2.5 border-b border-border/60 pb-3.5"
                    >
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Building2 class="size-4" />
                        </div>
                        <div>
                            <h2
                                class="text-base font-semibold tracking-tight text-foreground"
                            >
                                Supplier &amp; delivery schedule
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Select an active vendor and configure target
                                delivery and tax adjustments.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="supplier_id" class="font-medium">
                                Supplier
                                <span class="text-destructive">*</span>
                            </Label>
                            <select
                                id="supplier_id"
                                v-model="form.supplier_id"
                                required
                                class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
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

                        <div class="grid min-w-0 content-start gap-2">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <Label
                                    for="expected_delivery_at"
                                    class="font-medium"
                                >
                                    Expected delivery date
                                </Label>
                                <button
                                    v-if="selectedSupplier"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline"
                                    @click="applyLeadTimeDate"
                                >
                                    <Sparkles class="size-3" />
                                    <span>
                                        +{{
                                            selectedSupplier.lead_time_days ??
                                            7
                                        }}d lead time
                                    </span>
                                </button>
                            </div>
                            <Input
                                id="expected_delivery_at"
                                v-model="form.expected_delivery_at"
                                type="date"
                            />
                            <InputError
                                :message="form.errors.expected_delivery_at"
                            />
                        </div>

                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="tax" class="font-medium">
                                Tax / shipping adjustment (PHP)
                            </Label>
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

                    <!-- Selected Supplier Metadata Strip -->
                    <div
                        v-if="selectedSupplier"
                        class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-border/60 bg-muted/25 px-4 py-2.5 text-xs"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="text-muted-foreground">Vendor:</span>
                            <span class="font-semibold text-foreground">{{
                                selectedSupplier.name
                            }}</span>
                        </div>
                        <div
                            v-if="selectedSupplier.contact_person"
                            class="flex items-center gap-1.5"
                        >
                            <span class="text-muted-foreground">Contact:</span>
                            <span class="font-medium text-foreground">{{
                                selectedSupplier.contact_person
                            }}</span>
                        </div>
                        <div
                            v-if="selectedSupplier.payment_terms"
                            class="flex items-center gap-1.5"
                        >
                            <span class="text-muted-foreground">Terms:</span>
                            <Badge variant="outline" class="h-5 text-[11px]">
                                {{ selectedSupplier.payment_terms }}
                            </Badge>
                        </div>
                        <div
                            v-if="
                                selectedSupplier.lead_time_days !== null &&
                                selectedSupplier.lead_time_days !== undefined
                            "
                            class="flex items-center gap-1.5"
                        >
                            <span class="text-muted-foreground"
                                >Lead time:</span
                            >
                            <span class="font-medium text-foreground"
                                >{{
                                    selectedSupplier.lead_time_days
                                }}
                                day(s)</span
                            >
                        </div>
                    </div>
                </section>

                <!-- Section 2: Multi-Method Product Selection & Line Items -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-border/60 pb-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300"
                            >
                                <Package class="size-4" />
                            </div>
                            <div>
                                <h2
                                    class="text-base font-semibold tracking-tight text-foreground"
                                >
                                    Order line items
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Add products by name search, manual SKU, QR
                                    scan, or catalog browser.
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                class="gap-1.5"
                                @click="isCatalogOpen = true"
                            >
                                <BookOpen class="size-4" />
                                Browse Catalog
                            </Button>
                            <QrScannerDialog
                                button-label="Scan QR Code"
                                button-size="sm"
                                title="Scan product QR code"
                                description="Scan a product SKU label to add or increment it on this purchase order."
                                @scanned="handleScannedCode"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addLine"
                            >
                                <Plus class="mr-1 size-4" />
                                Add blank row
                            </Button>
                        </div>
                    </div>

                    <!-- Quick Search / Manual SKU Entry Bar -->
                    <div
                        class="mt-4 rounded-xl border border-primary/20 bg-primary/[0.03] p-3.5"
                    >
                        <Label
                            for="po-quick-product-entry"
                            class="text-xs font-semibold text-foreground"
                        >
                            Quick product lookup (Search by name or enter SKU)
                        </Label>
                        <div
                            class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <div class="relative flex-1">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="po-quick-product-entry"
                                    v-model="quickEntryQuery"
                                    type="search"
                                    placeholder="Type product name or SKU (e.g. Copy Paper or CON-001) and press Enter…"
                                    class="bg-background pl-9"
                                    autocomplete="off"
                                    @focus="showQuickSuggestions = true"
                                    @input="showQuickSuggestions = true"
                                    @blur="hideSuggestionsDelayed"
                                    @keydown.enter.prevent="handleQuickAdd"
                                />

                                <!-- Instant Autocomplete Dropdown -->
                                <div
                                    v-if="
                                        showQuickSuggestions &&
                                        quickSuggestions.length > 0
                                    "
                                    class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-border bg-popover p-1.5 shadow-lg"
                                >
                                    <div
                                        class="px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Matching active products
                                    </div>
                                    <button
                                        v-for="item in quickSuggestions"
                                        :key="item.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left text-xs transition hover:bg-muted"
                                        @mousedown.prevent="
                                            selectQuickSuggestion(item)
                                        "
                                    >
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                            >
                                                <span
                                                    class="truncate font-semibold text-foreground"
                                                    >{{ item.name }}</span
                                                >
                                                <Badge
                                                    variant="outline"
                                                    class="h-4 px-1.5 font-mono text-[10px]"
                                                >
                                                    {{ item.sku }}
                                                </Badge>
                                                <Badge
                                                    v-if="item.type === 'asset'"
                                                    variant="secondary"
                                                    class="h-4 px-1.5 text-[10px]"
                                                >
                                                    Asset
                                                </Badge>
                                            </div>
                                            <div
                                                class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground"
                                            >
                                                <span v-if="item.category">
                                                    {{ item.category }}
                                                </span>
                                                <span>
                                                    Stock:
                                                    <strong
                                                        class="text-foreground"
                                                        >{{
                                                            item.on_hand_qty ??
                                                            0
                                                        }}</strong
                                                    >
                                                </span>
                                                <span
                                                    v-if="
                                                        item.unit_price !==
                                                            null &&
                                                        item.unit_price !==
                                                            undefined
                                                    "
                                                >
                                                    •
                                                    {{
                                                        formatMoney(
                                                            Number(
                                                                item.unit_price,
                                                            ),
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-md bg-primary/10 px-2 py-1 text-[11px] font-semibold text-primary"
                                        >
                                            + Add
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="w-24 shrink-0">
                                    <Label for="po-quick-qty" class="sr-only">
                                        Quick add quantity
                                    </Label>
                                    <Input
                                        id="po-quick-qty"
                                        v-model.number="quickEntryQty"
                                        type="number"
                                        min="1"
                                        max="1000000"
                                        class="bg-background text-center"
                                        placeholder="Qty"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="default"
                                    class="shrink-0"
                                    @click="handleQuickAdd"
                                >
                                    <Plus class="mr-1.5 size-4" />
                                    Add to PO
                                </Button>
                            </div>
                        </div>

                        <!-- Action Notice Banner -->
                        <div
                            v-if="actionNotice"
                            role="status"
                            aria-live="polite"
                            class="mt-2.5 flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-medium"
                            :class="{
                                'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300':
                                    actionNotice.type === 'success',
                                'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300':
                                    actionNotice.type === 'warning',
                                'border-primary/30 bg-primary/10 text-primary':
                                    actionNotice.type === 'info',
                            }"
                        >
                            <AlertCircle
                                v-if="actionNotice.type === 'warning'"
                                class="size-4 shrink-0"
                            />
                            <CheckCircle2 v-else class="size-4 shrink-0" />
                            <span>{{ actionNotice.text }}</span>
                        </div>
                    </div>

                    <InputError :message="form.errors.lines" class="mt-2" />

                    <!-- Line Items List -->
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(line, index) in form.lines"
                            :key="index"
                            class="rounded-xl border border-border/70 bg-background p-4 shadow-2xs transition hover:border-border"
                        >
                            <!-- Line Card Header Bar -->
                            <div
                                class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-border/50 pb-2.5"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        class="h-5 px-2 font-mono text-[11px] font-semibold"
                                    >
                                        Line #{{ index + 1 }}
                                    </Badge>
                                    <template
                                        v-if="findProductById(line.product_id)"
                                    >
                                        <Badge
                                            variant="outline"
                                            class="h-5 px-1.5 font-mono text-[10px]"
                                        >
                                            {{
                                                findProductById(line.product_id)
                                                    ?.sku
                                            }}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            class="h-5 px-1.5 text-[10px] capitalize"
                                        >
                                            {{
                                                findProductById(line.product_id)
                                                    ?.type ?? 'item'
                                            }}
                                        </Badge>
                                        <span
                                            v-if="
                                                findProductById(line.product_id)
                                                    ?.category
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            •
                                            {{
                                                findProductById(line.product_id)
                                                    ?.category
                                            }}
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            • On hand:
                                            <strong
                                                :class="
                                                    (findProductById(
                                                        line.product_id,
                                                    )?.on_hand_qty ?? 0) <=
                                                    (findProductById(
                                                        line.product_id,
                                                    )?.reorder_threshold ?? 0)
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : 'text-foreground'
                                                "
                                            >
                                                {{
                                                    findProductById(
                                                        line.product_id,
                                                    )?.on_hand_qty ?? 0
                                                }}
                                            </strong>
                                        </span>
                                    </template>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    @click="removeLine(index)"
                                >
                                    <Trash2 class="mr-1 size-3.5" />
                                    Remove
                                </Button>
                            </div>

                            <!-- 4-Column Line Fields Grid -->
                            <div class="grid gap-3 md:grid-cols-12">
                                <!-- Product Select -->
                                <div
                                    class="grid min-w-0 content-start gap-1.5 md:col-span-5"
                                >
                                    <Label
                                        :for="`po-line-product-${index}`"
                                        class="text-xs font-semibold"
                                    >
                                        Product
                                    </Label>

                                    <select
                                        :id="`po-line-product-${index}`"
                                        v-model="line.product_id"
                                        required
                                        class="h-9 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm"
                                        @change="onProductChange(index)"
                                    >
                                        <option value="">Select product</option>
                                        <option
                                            v-for="product in filteredProducts"
                                            :key="product.id"
                                            :value="String(product.id)"
                                        >
                                            {{ product.sku }} -
                                            {{ product.name }}
                                        </option>
                                    </select>

                                    <InputError
                                        :message="
                                            form.errors[
                                                `lines.${index}.product_id`
                                            ]
                                        "
                                    />
                                </div>

                                <!-- Quantity Ordered with Steppers -->
                                <div
                                    class="grid min-w-0 content-start gap-1.5 md:col-span-3"
                                >
                                    <Label
                                        :for="`po-line-qty-${index}`"
                                        class="text-xs font-semibold"
                                    >
                                        Qty ordered
                                    </Label>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="size-9 shrink-0"
                                            aria-label="Decrease quantity"
                                            @click="adjustLineQty(index, -1)"
                                        >
                                            <Minus class="size-3.5" />
                                        </Button>
                                        <Input
                                            :id="`po-line-qty-${index}`"
                                            v-model="line.qty_ordered"
                                            type="number"
                                            min="1"
                                            max="1000000"
                                            required
                                            class="min-w-0 px-2 text-center font-medium"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="size-9 shrink-0"
                                            aria-label="Increase quantity"
                                            @click="adjustLineQty(index, 1)"
                                        >
                                            <Plus class="size-3.5" />
                                        </Button>
                                    </div>
                                    <InputError
                                        :message="
                                            form.errors[
                                                `lines.${index}.qty_ordered`
                                            ]
                                        "
                                    />
                                </div>

                                <!-- Unit Price -->
                                <div
                                    class="grid min-w-0 content-start gap-1.5 md:col-span-2"
                                >
                                    <Label
                                        :for="`po-line-price-${index}`"
                                        class="text-xs font-semibold"
                                    >
                                        Unit price (PHP)
                                    </Label>
                                    <Input
                                        :id="`po-line-price-${index}`"
                                        v-model="line.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `lines.${index}.unit_price`
                                            ]
                                        "
                                    />
                                </div>

                                <!-- Calculated Line Subtotal -->
                                <div
                                    class="grid min-w-0 content-start gap-1.5 md:col-span-2"
                                >
                                    <span
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        Line subtotal
                                    </span>
                                    <div
                                        class="flex h-9 items-center truncate rounded-md border border-border/60 bg-muted/30 px-3 font-mono text-xs font-semibold text-foreground"
                                    >
                                        {{ formatMoney(lineSubtotal(line)) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section 3: Notes -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <div class="grid gap-2">
                        <Label for="notes" class="font-medium">
                            Purchase order notes &amp; delivery instructions
                        </Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            placeholder="Include delivery gate instructions, budget reference code, or special packaging notes…"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </section>
            </div>

            <!-- Right Sidebar: Sticky Order Summary -->
            <aside class="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
                <div
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            class="text-sm font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Order summary
                        </h2>
                        <Badge variant="outline">Draft PO</Badge>
                    </div>

                    <dl
                        class="mt-4 space-y-2.5 border-b border-border/60 pb-4 text-xs"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">Supplier</dt>
                            <dd class="truncate font-semibold text-foreground">
                                {{ selectedSupplier?.name || 'Not selected' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">
                                Expected delivery
                            </dt>
                            <dd
                                class="inline-flex items-center gap-1 font-medium text-foreground"
                            >
                                <Calendar
                                    v-if="form.expected_delivery_at"
                                    class="size-3 text-muted-foreground"
                                />
                                {{
                                    form.expected_delivery_at || 'Not scheduled'
                                }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">
                                Distinct products
                            </dt>
                            <dd class="font-semibold text-foreground">
                                {{ populatedLineCount }} line(s)
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted-foreground">
                                Total units ordered
                            </dt>
                            <dd class="font-semibold text-foreground">
                                {{ totalUnitsOrdered }} unit(s)
                            </dd>
                        </div>
                    </dl>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-xs text-muted-foreground">
                                Items subtotal
                            </dt>
                            <dd class="font-mono text-xs font-medium">
                                {{ formatMoney(orderSubtotal) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-xs text-muted-foreground">
                                Tax / adjustments
                            </dt>
                            <dd class="font-mono text-xs font-medium">
                                {{ formatMoney(parsedTax) }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-border/60 pt-3"
                        >
                            <dt class="font-semibold text-foreground">
                                Estimated total
                            </dt>
                            <dd
                                class="font-mono text-base font-bold text-primary"
                            >
                                {{ formatMoney(orderGrandTotal) }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-col gap-2.5">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full"
                        >
                            <CheckCircle2 class="mr-1.5 size-4" />
                            {{
                                form.processing
                                    ? 'Creating purchase order…'
                                    : 'Create draft purchase order'
                            }}
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            type="button"
                            class="w-full"
                        >
                            <Link :href="purchaseOrdersIndex()">Cancel</Link>
                        </Button>
                    </div>
                </div>
            </aside>
        </form>

        <!-- Reusable Catalog Browser Modal -->
        <ProductSelectDialog
            v-model:open="isCatalogOpen"
            title="Browse products for purchase order"
            description="Search by product name or SKU, filter by category or type, and add items directly to your purchase order."
            type-filter="all"
            :only-in-stock-default="false"
            :exclude-skus="selectedSkus"
            @select="handleCatalogSelect"
        />
    </div>
</template>
