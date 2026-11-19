<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    CheckCheck,
    CheckCircle2,
    ExternalLink,
    FileSpreadsheet,
    Layers,
    Minus,
    Package,
    PackageCheck,
    Plus,
    QrCode,
    RotateCcw,
    Search,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
import ReceivingController from '@/actions/App/Http/Controllers/Inventory/ReceivingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OcrAssistButton from '@/components/inventory/OcrAssistButton.vue';
import ProductSelectDialog from '@/components/inventory/ProductSelectDialog.vue';
import type { ProductLookupItem } from '@/components/inventory/ProductSelectDialog.vue';
import ReceivingBatchSummaryFooter from '@/components/inventory/receiving/ReceivingBatchSummaryFooter.vue';
import ReceivingVerificationSidebar from '@/components/inventory/receiving/ReceivingVerificationSidebar.vue';
import ReceivingSerialFields from '@/components/inventory/ReceivingSerialFields.vue';

const QrScannerDialog = defineAsyncComponent(
    () => import('@/components/inventory/QrScannerDialog.vue'),
);
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { manilaCalendarDate } from '@/lib/dateFormatting';

type CatalogProductOption = {
    id: number;
    sku: string;
    name: string;
    type?: 'consumable' | 'asset' | string | null;
    category?: string | null;
    supplier?: string | null;
    on_hand_qty?: number;
    reorder_threshold?: number;
    unit_price?: number | null;
};

type PurchaseOrderLineOption = {
    id: number;
    product_id: number;
    product?: {
        id: number;
        sku: string;
        name: string;
        type?: string | null;
        variant?: string | null;
        unit?: string | null;
        category?: string | null;
        on_hand_qty?: number | null;
    } | null;
    qty_ordered: number;
    qty_received: number;
    qty_remaining: number;
    unit_price: number;
    subtotal: number;
    progress_pct: number;
};

type ReceivablePurchaseOrder = {
    id: number;
    po_number: string;
    status: string;
    status_label?: string;
    progress_pct: number;
    expected_delivery_at?: string | null;
    supplier?: {
        id: number;
        name: string;
        contact_person?: string | null;
    } | null;
    lines: PurchaseOrderLineOption[];
};

const props = withDefaults(
    defineProps<{
        products?: CatalogProductOption[];
        purchaseOrders?: ReceivablePurchaseOrder[];
        initialPurchaseOrderId?: number | null;
    }>(),
    {
        products: () => [],
        purchaseOrders: () => [],
        initialPurchaseOrderId: null,
    },
);

defineOptions({
    name: 'ReceivingIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Receiving', href: ReceivingController.index() },
        ],
    },
});

type ReceivingMode = 'single' | 'batch' | 'po';

const mode = ref<ReceivingMode>(
    props.initialPurchaseOrderId &&
        props.purchaseOrders.some(
            (po) => po.id === props.initialPurchaseOrderId,
        )
        ? 'po'
        : 'single',
);

const isCatalogOpen = ref(false);
const catalogTarget = ref<'single' | 'batch'>('single');

type QrScannerConfig = {
    title: string;
    description: string;
    continuous?: boolean;
    closeOnScan?: boolean;
    onScanned: (value: string) => void;
};

const isQrScannerOpen = ref(false);
const activeQrScanner = ref<QrScannerConfig | null>(null);

function openQrScanner(config: QrScannerConfig): void {
    activeQrScanner.value = config;
    isQrScannerOpen.value = true;
}

function handleSharedQrScanned(value: string): void {
    activeQrScanner.value?.onScanned(value);
}
const feedbackBanner = ref<{
    type: 'info' | 'warning' | 'success';
    text: string;
} | null>(null);
let feedbackTimer: number | undefined;
let singleSuggestionsTimer: number | undefined;
let batchSuggestionsTimer: number | undefined;

onBeforeUnmount(() => {
    window.clearTimeout(feedbackTimer);
    window.clearTimeout(singleSuggestionsTimer);
    window.clearTimeout(batchSuggestionsTimer);
});

function showFeedback(
    text: string,
    type: 'info' | 'warning' | 'success' = 'info',
): void {
    feedbackBanner.value = { type, text };
    window.clearTimeout(feedbackTimer);
    feedbackTimer = window.setTimeout(() => {
        if (feedbackBanner.value?.text === text) {
            feedbackBanner.value = null;
        }
    }, 4500);
}

// ============================================================================
// Product Lookup Helpers
// ============================================================================
function findCatalogProductBySku(rawSku: string): CatalogProductOption | null {
    const cleaned = rawSku.trim().toLowerCase();

    if (!cleaned) {
        return null;
    }

    return (
        props.products.find((item) => item.sku.toLowerCase() === cleaned) ??
        null
    );
}

function parseTagCodes(value: string): string[] {
    return value
        .split(/\r?\n|,/)
        .map((s) => s.trim())
        .filter(Boolean);
}

// ============================================================================
// Mode 1: Single Item Receiving
// ============================================================================
type SingleReceivingForm = {
    sku: string;
    reference_no: string;
    received_at: string;
    expires_at: string;
    qty: string;
    tag_codes_text: string;
    tag_codes: string[] | null;
    serials: Record<string, string>;
    serial_numbers: (string | null)[];
    notes: string;
};

const form = useForm<SingleReceivingForm>({
    sku: '',
    reference_no: '',
    received_at: '',
    expires_at: '',
    qty: '',
    tag_codes_text: '',
    tag_codes: null,
    serials: {},
    serial_numbers: [],
    notes: '',
});

const singleSearchQuery = ref('');
const showSingleSuggestions = ref(false);

const singleSuggestions = computed(() => {
    const q = singleSearchQuery.value.trim().toLowerCase();

    if (!q) {
        return props.products.slice(0, 6);
    }

    return props.products
        .filter(
            (item) =>
                item.sku.toLowerCase().includes(q) ||
                item.name.toLowerCase().includes(q) ||
                (item.category ?? '').toLowerCase().includes(q),
        )
        .slice(0, 8);
});

const matchedSingleProduct = computed(() => findCatalogProductBySku(form.sku));

const singleTagList = computed(() => parseTagCodes(form.tag_codes_text));

function selectSingleProduct(
    product: CatalogProductOption,
    defaultQty?: number,
): void {
    form.sku = product.sku;
    singleSearchQuery.value = '';
    showSingleSuggestions.value = false;

    if (product.type !== 'asset') {
        if (defaultQty && defaultQty > 0) {
            form.qty = String(defaultQty);
        } else if (!form.qty) {
            form.qty = '1';
        }
    }

    showFeedback(
        `Selected ${product.sku} — ${product.name} (${product.type === 'asset' ? 'Serialized Asset' : 'Consumable'}).`,
        'success',
    );
}

function handleSingleSearchEnter(): void {
    const q = singleSearchQuery.value.trim();

    if (!q) {
        return;
    }

    const exact =
        findCatalogProductBySku(q) ?? singleSuggestions.value[0] ?? null;

    if (exact) {
        selectSingleProduct(exact);
    } else {
        form.sku = q;
        singleSearchQuery.value = '';
        showSingleSuggestions.value = false;
    }
}

function handleSingleQrScanned(rawSku: string): void {
    const cleaned = rawSku.trim();

    if (!cleaned) {
        return;
    }

    const match = findCatalogProductBySku(cleaned);

    if (match) {
        selectSingleProduct(match);

        return;
    }

    form.sku = cleaned;
    showFeedback(
        `Scanned SKU "${cleaned}". Fill in product details below.`,
        'info',
    );
}

function hideSingleSuggestionsDelayed(): void {
    window.clearTimeout(singleSuggestionsTimer);
    singleSuggestionsTimer = window.setTimeout(() => {
        showSingleSuggestions.value = false;
    }, 180);
}

function adjustSingleQty(delta: number): void {
    const current = Math.max(0, Number(form.qty) || 0);
    const next = Math.max(1, current + delta);
    form.qty = String(next);
}

function appendTagCode(value: string): void {
    const cleaned = value.trim();

    if (!cleaned) {
        return;
    }

    const current = parseTagCodes(form.tag_codes_text);

    if (current.includes(cleaned)) {
        return;
    }

    form.tag_codes_text = [...current, cleaned].join('\n');
}

function submitSingle(): void {
    const tagCodes = parseTagCodes(form.tag_codes_text);

    form.tag_codes = tagCodes.length ? tagCodes : null;

    form.transform((data) => ({
        sku: data.sku.trim(),
        reference_no: data.reference_no.trim() || null,
        received_at: data.received_at || null,
        expires_at: data.expires_at || null,
        qty: data.qty ? Number(data.qty) : null,
        tag_codes: data.tag_codes,
        serial_numbers: tagCodes.map(
            (tag) => data.serials[tag]?.trim() || null,
        ),
        notes: data.notes.trim() || null,
    })).post(ReceivingController.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset(
                'qty',
                'tag_codes_text',
                'tag_codes',
                'serials',
                'serial_numbers',
                'notes',
            );
        },
    });
}

// ============================================================================
// Mode 2: Batch Delivery Receiving
// ============================================================================
type BatchLineForm = {
    localKey: string;
    sku: string;
    reference_no: string;
    received_at: string;
    expires_at: string;
    qty: string;
    tag_codes_text: string;
    serials: Record<string, string>;
    notes: string;
};

let nextBatchLineKey = 1;

function createBatchLineKey(): string {
    return `batch-line-${nextBatchLineKey++}`;
}

function createEmptyBatchLine(
    defaultRef = '',
    defaultDate = '',
): BatchLineForm {
    return {
        localKey: createBatchLineKey(),
        sku: '',
        reference_no: defaultRef,
        received_at: defaultDate,
        expires_at: '',
        qty: '',
        tag_codes_text: '',
        serials: {},
        notes: '',
    };
}

const batchForm = useForm<{
    lines: BatchLineForm[];
}>({
    lines: [createEmptyBatchLine()],
});

const batchQuickQuery = ref('');
const batchQuickQty = ref(1);
const showBatchSuggestions = ref(false);
const batchSharedRef = ref('');
const batchSharedDate = ref('');

const batchSuggestions = computed(() => {
    const q = batchQuickQuery.value.trim().toLowerCase();

    if (!q) {
        return props.products.slice(0, 6);
    }

    return props.products
        .filter(
            (item) =>
                item.sku.toLowerCase().includes(q) ||
                item.name.toLowerCase().includes(q) ||
                (item.category ?? '').toLowerCase().includes(q),
        )
        .slice(0, 8);
});

const batchSelectedSkus = computed(() =>
    batchForm.lines.map((l) => l.sku.trim()).filter(Boolean),
);

const batchSummary = computed(() => {
    let populatedLines = 0;
    let totalConsumableQty = 0;
    let totalAssetTags = 0;

    batchForm.lines.forEach((line) => {
        if (line.sku.trim()) {
            populatedLines += 1;
        }

        totalConsumableQty += Math.max(0, Number(line.qty) || 0);
        totalAssetTags += parseTagCodes(line.tag_codes_text).length;
    });

    return { populatedLines, totalConsumableQty, totalAssetTags };
});

function addBatchLine(): void {
    if (batchForm.lines.length >= 50) {
        return;
    }

    batchForm.lines.push(
        createEmptyBatchLine(batchSharedRef.value, batchSharedDate.value),
    );
}

function removeBatchLine(index: number): void {
    if (batchForm.lines.length <= 1) {
        batchForm.lines[0] = createEmptyBatchLine(
            batchSharedRef.value,
            batchSharedDate.value,
        );

        return;
    }

    batchForm.lines.splice(index, 1);
}

function addOrMergeProductIntoBatch(
    product: CatalogProductOption,
    qtyToAdd = 1,
): void {
    const qty = Math.max(1, Math.trunc(Number(qtyToAdd) || 1));
    const existingIndex = batchForm.lines.findIndex(
        (line) => line.sku.trim().toLowerCase() === product.sku.toLowerCase(),
    );

    if (existingIndex !== -1 && product.type !== 'asset') {
        const existingLine = batchForm.lines[existingIndex];
        const nextQty = (Number(existingLine.qty) || 0) + qty;
        existingLine.qty = String(nextQty);
        showFeedback(
            `Updated batch line #${existingIndex + 1} (${product.sku}) — quantity increased to ${nextQty}.`,
            'success',
        );

        return;
    }

    const emptyIndex = batchForm.lines.findIndex((line) => !line.sku.trim());
    const targetLine: BatchLineForm = {
        localKey: createBatchLineKey(),
        sku: product.sku,
        reference_no: batchSharedRef.value,
        received_at: batchSharedDate.value,
        expires_at: '',
        qty: product.type === 'asset' ? '' : String(qty),
        tag_codes_text: '',
        serials: {},
        notes: '',
    };

    if (emptyIndex !== -1) {
        batchForm.lines[emptyIndex] = targetLine;
    } else if (batchForm.lines.length < 50) {
        batchForm.lines.push(targetLine);
    }

    showFeedback(
        `Added ${product.sku} (${product.name}) to batch delivery.`,
        'success',
    );
}

function handleBatchQuickAdd(selectedItem?: CatalogProductOption): void {
    const item =
        selectedItem ??
        findCatalogProductBySku(batchQuickQuery.value) ??
        batchSuggestions.value[0] ??
        null;

    if (!item) {
        showFeedback(
            `No product matched "${batchQuickQuery.value.trim()}".`,
            'warning',
        );

        return;
    }

    addOrMergeProductIntoBatch(item, batchQuickQty.value);
    batchQuickQuery.value = '';
    batchQuickQty.value = 1;
    showBatchSuggestions.value = false;
}

function hideBatchSuggestionsDelayed(): void {
    window.clearTimeout(batchSuggestionsTimer);
    batchSuggestionsTimer = window.setTimeout(() => {
        showBatchSuggestions.value = false;
    }, 180);
}

function applyBatchLineQrScanned(index: number, rawSku: string): void {
    const line = batchForm.lines[index];
    const cleaned = rawSku.trim();

    if (!line || !cleaned) {
        return;
    }

    const match = findCatalogProductBySku(cleaned);

    if (match) {
        line.sku = match.sku;

        if (match.type !== 'asset' && !line.qty) {
            line.qty = '1';
        }
    } else {
        line.sku = cleaned;
    }
}

function applyBatchSharedMetadata(): void {
    batchForm.lines.forEach((line) => {
        if (batchSharedRef.value.trim() !== '') {
            line.reference_no = batchSharedRef.value.trim();
        }

        if (batchSharedDate.value !== '') {
            line.received_at = batchSharedDate.value;
        }
    });
    showFeedback(
        'Applied shared delivery reference and date to all batch lines.',
        'success',
    );
}

function appendBatchTagCode(index: number, value: string): void {
    const line = batchForm.lines[index];
    const cleaned = value.trim();

    if (!line || !cleaned) {
        return;
    }

    const current = parseTagCodes(line.tag_codes_text);

    if (current.includes(cleaned)) {
        return;
    }

    line.tag_codes_text = [...current, cleaned].join('\n');
}

function submitBatch(): void {
    batchForm
        .transform((data) => ({
            lines: data.lines.map((line) => {
                const tagCodes = parseTagCodes(line.tag_codes_text);

                return {
                    sku: line.sku.trim(),
                    reference_no: line.reference_no.trim() || null,
                    received_at: line.received_at || null,
                    expires_at: line.expires_at || null,
                    qty: line.qty ? Number(line.qty) : null,
                    tag_codes: tagCodes.length ? tagCodes : null,
                    serial_numbers: tagCodes.map(
                        (tag) => line.serials[tag]?.trim() || null,
                    ),
                    notes: line.notes.trim() || null,
                };
            }),
        }))
        .post(ReceivingController.storeBatch().url, {
            preserveScroll: true,
        });
}

function batchLineError(index: number, field: string): string | undefined {
    const errors = batchForm.errors as Record<string, string | undefined>;

    return errors[`lines.${index}.${field}`];
}

// ============================================================================
// Catalog Modal Handler (Shared by Single & Batch)
// ============================================================================
function openCatalog(target: 'single' | 'batch'): void {
    catalogTarget.value = target;
    isCatalogOpen.value = true;
}

function handleCatalogSelection(
    lookupItem: ProductLookupItem,
    quantity: number,
): void {
    const catalogProduct: CatalogProductOption = findCatalogProductBySku(
        lookupItem.sku,
    ) ?? {
        id: lookupItem.id,
        sku: lookupItem.sku,
        name: lookupItem.name,
        type: lookupItem.type,
        category: lookupItem.category,
        on_hand_qty: lookupItem.on_hand_qty,
        reorder_threshold: lookupItem.reorder_threshold,
        unit_price: lookupItem.unit_price,
    };

    if (catalogTarget.value === 'single') {
        selectSingleProduct(catalogProduct, quantity);
    } else {
        addOrMergeProductIntoBatch(catalogProduct, quantity);
    }
}

// ============================================================================
// Mode 3: Purchase Order Receiving
// ============================================================================
const selectedPoId = ref<string>(
    props.initialPurchaseOrderId
        ? String(props.initialPurchaseOrderId)
        : props.purchaseOrders[0]
          ? String(props.purchaseOrders[0].id)
          : '',
);

const selectedPo = computed<ReceivablePurchaseOrder | null>(() => {
    const id = Number(selectedPoId.value || 0);

    if (!id) {
        return null;
    }

    return props.purchaseOrders.find((po) => po.id === id) ?? null;
});

type PoReceiveLineState = {
    purchase_order_line_id: number;
    product_type: string;
    qty_remaining: number;
    qty_received: string;
    reference_no: string;
    received_at: string;
    expires_at: string;
    tag_codes_text: string;
    serials: Record<string, string>;
    notes: string;
};

const poReceiveForm = useForm<{
    lines: PoReceiveLineState[];
    idempotency_key: string;
}>({
    lines: [],
    idempotency_key: '',
});

const poLineSearchQuery = ref('');
const poSharedRef = ref('');
const poSharedDate = ref(manilaCalendarDate());

function syncPoReceiveLines(po: ReceivablePurchaseOrder | null): void {
    if (!po) {
        poReceiveForm.lines = [];

        return;
    }

    poReceiveForm.clearErrors();
    poReceiveForm.lines = po.lines.map((line) => ({
        purchase_order_line_id: line.id,
        product_type: line.product?.type ?? 'consumable',
        qty_remaining: line.qty_remaining,
        qty_received: '',
        reference_no: '',
        received_at: '',
        expires_at: '',
        tag_codes_text: '',
        serials: {},
        notes: '',
    }));
}

watch(
    selectedPo,
    (nextPo) => {
        syncPoReceiveLines(nextPo);
    },
    { immediate: true },
);

const filteredPoLineIndices = computed<number[]>(() => {
    if (!selectedPo.value) {
        return [];
    }

    const q = poLineSearchQuery.value.trim().toLowerCase();

    return selectedPo.value.lines
        .map((line, index) => ({ line, index }))
        .filter(({ line }) => {
            if (!q) {
                return true;
            }

            const sku = line.product?.sku?.toLowerCase() ?? '';
            const name = line.product?.name?.toLowerCase() ?? '';

            return sku.includes(q) || name.includes(q);
        })
        .map(({ index }) => index);
});

const poReceiptStats = computed(() => {
    let activeLines = 0;
    let totalUnitsNow = 0;

    poReceiveForm.lines.forEach((line) => {
        if (line.product_type === 'asset') {
            const tags = parseTagCodes(line.tag_codes_text);

            if (tags.length > 0) {
                activeLines += 1;
                totalUnitsNow += tags.length;
            }
        } else {
            const qty = Number(line.qty_received || 0);

            if (qty > 0) {
                activeLines += 1;
                totalUnitsNow += qty;
            }
        }
    });

    return { activeLines, totalUnitsNow };
});

const poReceiveErrors = computed(
    () => poReceiveForm.errors as Record<string, string | undefined>,
);

function adjustPoLineQty(index: number, delta: number): void {
    const lineState = poReceiveForm.lines[index];

    if (
        !lineState ||
        lineState.product_type === 'asset' ||
        lineState.qty_remaining < 1
    ) {
        return;
    }

    const current = Math.max(0, Number(lineState.qty_received) || 0);
    const next = Math.min(
        lineState.qty_remaining,
        Math.max(0, current + delta),
    );
    lineState.qty_received = next > 0 ? String(next) : '';
}

function fillPoLineRemaining(index: number): void {
    const lineState = poReceiveForm.lines[index];

    if (
        !lineState ||
        lineState.product_type === 'asset' ||
        lineState.qty_remaining < 1
    ) {
        return;
    }

    lineState.qty_received = String(lineState.qty_remaining);
}

function fillAllPoRemaining(): void {
    let count = 0;
    poReceiveForm.lines.forEach((line) => {
        if (line.product_type !== 'asset' && line.qty_remaining > 0) {
            line.qty_received = String(line.qty_remaining);
            count += 1;
        }
    });

    if (count > 0) {
        showFeedback(
            `Filled remaining quantities for ${count} consumable PO line(s).`,
            'success',
        );
    }
}

function clearAllPoQuantities(): void {
    poReceiveForm.lines.forEach((line) => {
        line.qty_received = '';
        line.tag_codes_text = '';
        line.serials = {};
    });
}

function appendPoAssetTag(index: number, rawCode: string): void {
    const lineState = poReceiveForm.lines[index];
    const cleaned = rawCode.trim();

    if (!lineState || !cleaned) {
        return;
    }

    const current = parseTagCodes(lineState.tag_codes_text);

    if (current.includes(cleaned)) {
        return;
    }

    if (current.length >= lineState.qty_remaining) {
        showFeedback(
            `All ${lineState.qty_remaining} remaining asset tag(s) for this PO line are already entered.`,
            'warning',
        );

        return;
    }

    lineState.tag_codes_text = [...current, cleaned].join('\n');
}

function handlePoProductMatch(rawQuery: string): void {
    if (!selectedPo.value) {
        return;
    }

    const cleaned = rawQuery.trim();

    if (!cleaned) {
        return;
    }

    const idx = selectedPo.value.lines.findIndex(
        (line) =>
            line.product?.sku?.toLowerCase() === cleaned.toLowerCase() ||
            line.product?.name?.toLowerCase() === cleaned.toLowerCase(),
    );

    if (idx === -1) {
        showFeedback(
            `"${cleaned}" does not match any item on ${selectedPo.value.po_number}.`,
            'warning',
        );

        return;
    }

    const poLine = selectedPo.value.lines[idx];
    const state = poReceiveForm.lines[idx];

    if (poLine.qty_remaining <= 0) {
        showFeedback(
            `${poLine.product?.sku} is already fully received on this PO.`,
            'warning',
        );

        return;
    }

    if (state.product_type === 'asset') {
        poLineSearchQuery.value = poLine.product?.sku ?? '';
        showFeedback(
            `Matched asset ${poLine.product?.sku}. Scan or enter its asset tags below.`,
            'info',
        );

        return;
    }

    const current = Math.max(0, Number(state.qty_received) || 0);

    if (current >= poLine.qty_remaining) {
        showFeedback(
            `${poLine.product?.sku} is already at maximum remaining quantity (${poLine.qty_remaining}).`,
            'warning',
        );

        return;
    }

    state.qty_received = String(current + 1);
    showFeedback(
        `Matched ${poLine.product?.sku} — receiving quantity now ${current + 1} of ${poLine.qty_remaining}.`,
        'success',
    );
}

function submitPoReceipt(): void {
    if (!selectedPo.value) {
        return;
    }

    poReceiveForm.clearErrors();

    const lines = poReceiveForm.lines
        .map((line) => {
            const tags = parseTagCodes(line.tag_codes_text);
            const qtyReceived =
                line.product_type === 'asset'
                    ? tags.length
                    : Number(line.qty_received || 0);

            if (line.qty_remaining <= 0) {
                return null;
            }

            if (line.product_type === 'asset' && tags.length === 0) {
                return null;
            }

            if (line.product_type !== 'asset' && qtyReceived <= 0) {
                return null;
            }

            return {
                purchase_order_line_id: line.purchase_order_line_id,
                qty_received: qtyReceived > 0 ? qtyReceived : null,
                reference_no:
                    line.reference_no.trim() ||
                    poSharedRef.value.trim() ||
                    null,
                received_at: line.received_at || poSharedDate.value || null,
                expires_at:
                    line.product_type === 'consumable'
                        ? line.expires_at || null
                        : null,
                tag_codes: line.product_type === 'asset' ? tags : null,
                serial_numbers:
                    line.product_type === 'asset'
                        ? tags.map((tag) => line.serials[tag]?.trim() || null)
                        : [],
                notes: line.notes.trim() || null,
            };
        })
        .filter((entry): entry is NonNullable<typeof entry> => entry !== null);

    if (lines.length === 0) {
        poReceiveForm.setError(
            'lines',
            'Enter at least one received quantity or asset tag before recording the PO receipt.',
        );

        return;
    }

    const idempotencyKey =
        typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
            ? crypto.randomUUID()
            : `po-rcv-${selectedPo.value.id}-${Date.now()}`;

    poReceiveForm.idempotency_key = idempotencyKey;

    poReceiveForm
        .transform(() => ({
            lines,
            idempotency_key: idempotencyKey,
        }))
        .post(PurchaseOrderController.receive(selectedPo.value.id).url, {
            preserveScroll: true,
        });
}
</script>

<template>
    <div
        data-testid="receiving-page"
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6"
    >
        <Head title="Receiving" />

        <!-- Top Header & Mode Switcher -->
        <div
            class="relative overflow-hidden rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-52 rounded-full bg-primary/5 blur-2xl"
            />
            <div
                class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-2xs"
                    >
                        <Truck class="size-5" />
                    </div>
                    <Heading
                        title="Receiving"
                        description="Record incoming consumable stock lots, serialized assets, or purchase order deliveries via scan, SKU, name search, or catalog browser."
                    />
                </div>

                <!-- 3-Way Workflow Mode Switcher -->
                <div
                    class="inline-flex flex-wrap items-center gap-1.5 rounded-xl border border-border/70 bg-muted/40 p-1.5"
                >
                    <Button
                        type="button"
                        size="sm"
                        :variant="mode === 'single' ? 'default' : 'ghost'"
                        class="rounded-lg"
                        @click="mode = 'single'"
                    >
                        Single item
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="mode === 'batch' ? 'default' : 'ghost'"
                        class="rounded-lg"
                        @click="mode = 'batch'"
                    >
                        Batch delivery
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="mode === 'po' ? 'default' : 'ghost'"
                        class="gap-1.5 rounded-lg"
                        @click="mode = 'po'"
                    >
                        <FileSpreadsheet class="size-3.5" />
                        <span>Purchase order</span>
                        <Badge
                            v-if="purchaseOrders.length > 0"
                            variant="secondary"
                            class="ml-0.5 h-4 px-1.5 text-[10px]"
                        >
                            {{ purchaseOrders.length }}
                        </Badge>
                    </Button>
                </div>
            </div>

            <!-- Feedback Toast Banner -->
            <div
                v-if="feedbackBanner"
                role="status"
                aria-live="polite"
                class="mt-4 flex items-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs font-medium"
                :class="{
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300':
                        feedbackBanner.type === 'success',
                    'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300':
                        feedbackBanner.type === 'warning',
                    'border-primary/30 bg-primary/10 text-primary':
                        feedbackBanner.type === 'info',
                }"
            >
                <AlertCircle
                    v-if="feedbackBanner.type === 'warning'"
                    class="size-4 shrink-0"
                />
                <CheckCircle2 v-else class="size-4 shrink-0" />
                <span>{{ feedbackBanner.text }}</span>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- MODE 1: SINGLE ITEM RECEIVING                              -->
        <!-- ========================================================== -->
        <form
            v-if="mode === 'single'"
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
            @submit.prevent="submitSingle"
        >
            <div class="flex flex-col gap-6">
                <!-- Step 1: 4-Way Product Identification Card -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-border/60 pb-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <Package class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">
                                    1. Identify product &amp; delivery reference
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Scan QR code, enter SKU manually, search by
                                    product name, or browse the catalog.
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                class="gap-1.5"
                                @click="openCatalog('single')"
                            >
                                <BookOpen class="size-4" />
                                Browse Catalog
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="min-h-11 w-full sm:w-auto"
                                @click="
                                    openQrScanner({
                                        title: 'Scan product SKU',
                                        description:
                                            'Scan the product label QR code to populate the SKU field.',
                                        onScanned: handleSingleQrScanned,
                                    })
                                "
                            >
                                <QrCode
                                    aria-hidden="true"
                                    class="mr-2 size-4"
                                />
                                Scan SKU
                            </Button>
                        </div>
                    </div>

                    <!-- Quick Name / SKU Search Autocomplete -->
                    <div
                        class="mt-4 rounded-xl border border-primary/20 bg-primary/[0.03] p-3.5"
                    >
                        <Label
                            for="single-product-search"
                            class="text-xs font-semibold text-foreground"
                        >
                            Search product by name or category
                        </Label>
                        <div class="relative mt-1.5">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                id="single-product-search"
                                v-model="singleSearchQuery"
                                type="search"
                                placeholder="Type product name (e.g. Copy Paper, Laptop, Stapler) to find and select its SKU…"
                                class="bg-background pl-9"
                                autocomplete="off"
                                @focus="showSingleSuggestions = true"
                                @input="showSingleSuggestions = true"
                                @blur="hideSingleSuggestionsDelayed"
                                @keydown.enter.prevent="handleSingleSearchEnter"
                            />

                            <div
                                v-if="
                                    showSingleSuggestions &&
                                    singleSuggestions.length > 0
                                "
                                class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-border bg-popover p-1.5 shadow-lg"
                            >
                                <div
                                    class="px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Click a product to populate SKU
                                </div>
                                <button
                                    v-for="item in singleSuggestions"
                                    :key="item.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left text-xs transition hover:bg-muted"
                                    @mousedown.prevent="
                                        selectSingleProduct(item)
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
                                                :variant="
                                                    item.type === 'asset'
                                                        ? 'secondary'
                                                        : 'outline'
                                                "
                                                class="h-4 px-1.5 text-[10px] capitalize"
                                            >
                                                {{ item.type }}
                                            </Badge>
                                        </div>
                                        <div
                                            class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground"
                                        >
                                            <span v-if="item.category">{{
                                                item.category
                                            }}</span>
                                            <span>
                                                On hand:
                                                <strong
                                                    class="text-foreground"
                                                    >{{
                                                        item.on_hand_qty ?? 0
                                                    }}</strong
                                                >
                                            </span>
                                        </div>
                                    </div>
                                    <span
                                        class="shrink-0 rounded-md bg-primary/10 px-2 py-1 text-[11px] font-semibold text-primary"
                                    >
                                        Select
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Core SKU, Reference, and Dates Grid -->
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="sku" class="font-medium">
                                Product SKU
                                <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="sku"
                                v-model="form.sku"
                                required
                                placeholder="e.g. CON-001 or AST-001"
                                data-testid="receiving-sku-input"
                                class="font-mono"
                            />
                            <InputError :message="form.errors.sku" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="reference_no" class="font-medium">
                                Reference no. (DR / PO / Invoice)
                            </Label>
                            <Input
                                id="reference_no"
                                v-model="form.reference_no"
                                placeholder="e.g. DR-2026-0001"
                                data-testid="receiving-reference-input"
                            />
                            <InputError :message="form.errors.reference_no" />
                            <OcrAssistButton
                                label="Read delivery reference"
                                title="Read delivery reference"
                                description="Read and correct the delivery reference. This does not confirm delivery or change received quantities."
                                field-label="Delivery reference (editable)"
                                submit-label="Apply reviewed reference"
                                help="Only the reference field changes. Check the document and complete the receipt yourself."
                                @reviewed="
                                    form.reference_no = $event.slice(0, 64)
                                "
                            />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="received_at" class="font-medium">
                                Date received
                            </Label>
                            <Input
                                id="received_at"
                                v-model="form.received_at"
                                type="date"
                            />
                            <InputError :message="form.errors.received_at" />
                        </div>

                        <div
                            v-if="
                                !matchedSingleProduct ||
                                matchedSingleProduct.type !== 'asset'
                            "
                            class="grid content-start gap-2"
                        >
                            <Label for="expires_at" class="font-medium">
                                Expiry date (consumables only)
                            </Label>
                            <Input
                                id="expires_at"
                                v-model="form.expires_at"
                                type="date"
                            />
                            <InputError :message="form.errors.expires_at" />
                        </div>
                    </div>

                    <!-- Live Matched Product Preview Card -->
                    <div
                        v-if="matchedSingleProduct"
                        class="mt-4 flex flex-col justify-between gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/[0.04] p-3.5 sm:flex-row sm:items-center"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant="default"
                                    class="bg-emerald-600 text-[10px] text-white"
                                >
                                    Matched Product
                                </Badge>
                                <span
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ matchedSingleProduct.name }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="font-mono text-xs"
                                >
                                    {{ matchedSingleProduct.sku }}
                                </Badge>
                            </div>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                            >
                                <span>
                                    Classification:
                                    <strong
                                        class="text-foreground capitalize"
                                        >{{ matchedSingleProduct.type }}</strong
                                    >
                                </span>
                                <span v-if="matchedSingleProduct.category">
                                    • Category:
                                    <strong class="text-foreground">{{
                                        matchedSingleProduct.category
                                    }}</strong>
                                </span>
                                <span>
                                    • Current stock:
                                    <strong class="text-foreground">{{
                                        matchedSingleProduct.on_hand_qty ?? 0
                                    }}</strong>
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 text-xs font-medium">
                            <span
                                v-if="matchedSingleProduct.type === 'asset'"
                                class="inline-flex items-center gap-1 rounded-lg border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-sky-700 dark:text-sky-300"
                            >
                                Enter / scan asset tags below
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-lg border border-primary/30 bg-primary/10 px-2.5 py-1 text-primary"
                            >
                                Enter consumable quantity below
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Step 2: Quantity (Consumables) & Tag Codes (Assets) -->
                <div
                    class="grid gap-6"
                    :class="matchedSingleProduct ? '' : 'md:grid-cols-2'"
                >
                    <!-- Consumable Quantity Card -->
                    <section
                        v-if="
                            !matchedSingleProduct ||
                            matchedSingleProduct.type !== 'asset'
                        "
                        class="rounded-2xl border p-5 shadow-xs transition"
                        :class="
                            matchedSingleProduct?.type === 'consumable'
                                ? 'border-primary/40 bg-card ring-1 ring-primary/15'
                                : matchedSingleProduct?.type === 'asset'
                                  ? 'border-border/50 bg-card/60 opacity-75'
                                  : 'border-border/70 bg-card'
                        "
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold">Consumables</h3>
                            <Badge variant="outline" class="text-[10px]">
                                Stock lot quantity
                            </Badge>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Enter quantity received to create a new stock lot
                            and increment on-hand inventory.
                        </p>

                        <div class="mt-4 grid gap-2">
                            <Label for="qty" class="font-medium"
                                >Quantity</Label
                            >
                            <div class="flex items-center gap-1.5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-9 shrink-0"
                                    aria-label="Decrease quantity"
                                    @click="adjustSingleQty(-1)"
                                >
                                    <Minus class="size-3.5" />
                                </Button>
                                <Input
                                    id="qty"
                                    v-model="form.qty"
                                    type="number"
                                    min="1"
                                    placeholder="e.g. 25"
                                    data-testid="receiving-qty-input"
                                    class="text-center font-semibold"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-9 shrink-0"
                                    aria-label="Increase quantity"
                                    @click="adjustSingleQty(1)"
                                >
                                    <Plus class="size-3.5" />
                                </Button>
                            </div>

                            <!-- Quick Quantity Presets -->
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <button
                                    v-for="preset in [1, 5, 10, 25, 50, 100]"
                                    :key="preset"
                                    type="button"
                                    class="rounded-md border border-border/70 bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    @click="form.qty = String(preset)"
                                >
                                    Set {{ preset }}
                                </button>
                            </div>
                            <InputError :message="form.errors.qty" />
                        </div>
                    </section>

                    <!-- Serialized Asset Tag Codes Card -->
                    <section
                        v-if="
                            !matchedSingleProduct ||
                            matchedSingleProduct.type === 'asset'
                        "
                        class="rounded-2xl border p-5 shadow-xs transition"
                        :class="
                            matchedSingleProduct?.type === 'asset'
                                ? 'border-primary/40 bg-card ring-1 ring-primary/15'
                                : matchedSingleProduct?.type === 'consumable'
                                  ? 'border-border/50 bg-card/60 opacity-75'
                                  : 'border-border/70 bg-card'
                        "
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Serialized assets
                                </h3>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Provide one tag code per line (or comma
                                    separated).
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                data-testid="receiving-tag-scanner-button"
                                class="min-h-11 w-full sm:w-auto"
                                @click="
                                    openQrScanner({
                                        title: 'Batch scan asset tag codes',
                                        description:
                                            'Keep the scanner open and scan each asset tag to append it to the receipt list.',
                                        continuous: true,
                                        closeOnScan: false,
                                        onScanned: appendTagCode,
                                    })
                                "
                            >
                                <QrCode
                                    aria-hidden="true"
                                    class="mr-2 size-4"
                                />
                                Batch Scan Tags
                            </Button>
                            <OcrAssistButton
                                v-if="matchedSingleProduct?.type === 'asset'"
                                label="Read printed asset tags"
                                title="Read receiving asset tags"
                                description="Review institutional asset tags, one per line. Manufacturer serial numbers are not asset tags and must not be substituted."
                                field-label="Institutional asset tags, one per line (editable)"
                                submit-label="Add reviewed tags"
                                help="Tags are added to the draft only. Verify the physical items; do not infer accepted quantities from a document."
                                multiline
                                @reviewed="
                                    (value) =>
                                        parseTagCodes(value).forEach(
                                            appendTagCode,
                                        )
                                "
                            />
                        </div>

                        <div class="mt-3 grid gap-2">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <Label for="tag_codes_text" class="font-medium">
                                    Asset tag codes
                                </Label>
                                <Badge
                                    v-if="singleTagList.length > 0"
                                    variant="secondary"
                                    class="text-[10px]"
                                >
                                    {{ singleTagList.length }} tag(s) ready
                                </Badge>
                            </div>
                            <textarea
                                id="tag_codes_text"
                                v-model="form.tag_codes_text"
                                rows="4"
                                placeholder="TAG-0001&#10;TAG-0002"
                                data-testid="receiving-tag-codes-input"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                            />
                            <InputError :message="form.errors.tag_codes" />
                            <ReceivingSerialFields
                                v-if="matchedSingleProduct?.type === 'asset'"
                                v-model="form.serials"
                                :tags="singleTagList"
                            />
                            <InputError
                                :message="
                                    form.errors.serial_numbers ||
                                    Object.entries(form.errors).find(([key]) =>
                                        key.startsWith('serial_numbers.'),
                                    )?.[1]
                                "
                            />
                        </div>
                    </section>
                </div>

                <!-- Step 3: Notes -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div class="grid gap-2">
                        <Label for="notes" class="font-medium">
                            Receiving notes (optional)
                        </Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="2"
                            placeholder="Carrier name, box count, condition inspection notes…"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </section>
            </div>

            <!-- Right Sidebar: Receipt Verification Summary -->
            <ReceivingVerificationSidebar
                :sku="form.sku"
                :reference-no="form.reference_no"
                :qty="form.qty"
                :processing="form.processing"
                :matched-product="matchedSingleProduct"
            />
        </form>

        <!-- ========================================================== -->
        <!-- MODE 2: BATCH DELIVERY RECEIVING                           -->
        <!-- ========================================================== -->
        <form
            v-else-if="mode === 'batch'"
            class="grid gap-6"
            @submit.prevent="submitBatch"
        >
            <!-- Batch Toolbar & Shared Metadata Card -->
            <div
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
            >
                <div
                    class="flex flex-col gap-3 border-b border-border/60 pb-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <Layers class="size-4" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold">
                                Batch delivery lines
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Receive multiple products in a single atomic
                                transaction using search, catalog browse, or QR
                                scanning.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="gap-1.5"
                            @click="openCatalog('batch')"
                        >
                            <BookOpen class="size-4" />
                            Browse Catalog
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="batchForm.lines.length >= 50"
                            @click="addBatchLine"
                        >
                            <Plus class="mr-1 size-4" />
                            Add Delivery Line
                        </Button>
                    </div>
                </div>

                <!-- Quick Add by Name/SKU + Shared Delivery Reference -->
                <div
                    class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]"
                >
                    <div
                        class="rounded-xl border border-primary/20 bg-primary/[0.03] p-3.5"
                    >
                        <Label
                            for="batch-quick-search"
                            class="text-xs font-semibold"
                        >
                            Quick add product by name or SKU
                        </Label>
                        <div
                            class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <div class="relative flex-1">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="batch-quick-search"
                                    v-model="batchQuickQuery"
                                    type="search"
                                    placeholder="Search product name or SKU and press Enter…"
                                    class="bg-background pl-9"
                                    autocomplete="off"
                                    @focus="showBatchSuggestions = true"
                                    @input="showBatchSuggestions = true"
                                    @blur="hideBatchSuggestionsDelayed"
                                    @keydown.enter.prevent="
                                        handleBatchQuickAdd()
                                    "
                                />

                                <div
                                    v-if="
                                        showBatchSuggestions &&
                                        batchSuggestions.length > 0
                                    "
                                    class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-border bg-popover p-1.5 shadow-lg"
                                >
                                    <button
                                        v-for="item in batchSuggestions"
                                        :key="item.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs hover:bg-muted"
                                        @mousedown.prevent="
                                            handleBatchQuickAdd(item)
                                        "
                                    >
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="truncate font-semibold"
                                                    >{{ item.name }}</span
                                                >
                                                <Badge
                                                    variant="outline"
                                                    class="h-4 px-1.5 font-mono text-[10px]"
                                                >
                                                    {{ item.sku }}
                                                </Badge>
                                            </div>
                                        </div>
                                        <span
                                            class="text-[11px] font-semibold text-primary"
                                            >+ Add line</span
                                        >
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model.number="batchQuickQty"
                                    type="number"
                                    min="1"
                                    aria-label="Quick add quantity"
                                    class="w-20 bg-background text-center"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    @click="handleBatchQuickAdd()"
                                >
                                    Add
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-border/60 bg-muted/20 p-3.5"
                    >
                        <Label
                            for="batch-shared-ref"
                            class="text-xs font-semibold"
                        >
                            Shared delivery reference &amp; date
                        </Label>
                        <div
                            class="mt-1.5 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_150px_auto]"
                        >
                            <Input
                                id="batch-shared-ref"
                                v-model="batchSharedRef"
                                placeholder="DR / Invoice no."
                                class="bg-background"
                            />
                            <OcrAssistButton
                                label="Read delivery reference"
                                title="Read batch delivery reference"
                                description="Review the delivery reference before applying it to batch lines. Receipt dates and accepted quantities remain manual."
                                field-label="Delivery reference (editable)"
                                submit-label="Apply reviewed reference"
                                help="Only the shared draft reference changes. Use Apply to copy it to the intended lines, then confirm the receipt normally."
                                @reviewed="batchSharedRef = $event.slice(0, 64)"
                            />
                            <Input
                                id="batch-shared-date"
                                v-model="batchSharedDate"
                                type="date"
                                aria-label="Shared received date"
                                class="bg-background"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9"
                                @click="applyBatchSharedMetadata"
                            >
                                Apply all
                            </Button>
                        </div>
                    </div>
                </div>

                <InputError :message="batchForm.errors.lines" class="mt-2" />
                <InputError
                    v-for="[key, message] in Object.entries(
                        batchForm.errors,
                    ).filter(([key]) => key.includes('serial_numbers'))"
                    :key="key"
                    :message="message"
                />

                <!-- Mobile Batch Lines (Preserves data-testid="receiving-batch-mobile-lines" & "Batch line X") -->
                <div
                    data-testid="receiving-batch-mobile-lines"
                    class="mt-4 space-y-4 md:hidden"
                >
                    <div
                        v-for="(line, index) in batchForm.lines"
                        :key="`mobile-${line.localKey}`"
                        class="rounded-xl border border-border/70 bg-background p-4 shadow-2xs"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold">
                                    Batch line {{ index + 1 }}
                                </div>
                                <div
                                    v-if="findCatalogProductBySku(line.sku)"
                                    class="text-xs font-medium text-primary"
                                >
                                    {{
                                        findCatalogProductBySku(line.sku)?.name
                                    }}
                                </div>
                            </div>
                            <Button
                                v-if="batchForm.lines.length > 1"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="removeBatchLine(index)"
                            >
                                Remove
                            </Button>
                        </div>

                        <div class="mt-4 grid gap-4">
                            <div class="grid gap-2">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <Label :for="`batch-mobile-sku-${index}`"
                                        >SKU</Label
                                    >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="min-h-11 w-full sm:w-auto"
                                        @click="
                                            openQrScanner({
                                                title: 'Scan batch line SKU',
                                                description:
                                                    'Scan a product QR code to fill this batch line SKU.',
                                                onScanned: (value) =>
                                                    applyBatchLineQrScanned(
                                                        index,
                                                        value,
                                                    ),
                                            })
                                        "
                                    >
                                        <QrCode
                                            aria-hidden="true"
                                            class="mr-2 size-4"
                                        />
                                        Scan SKU
                                    </Button>
                                </div>
                                <Input
                                    :id="`batch-mobile-sku-${index}`"
                                    v-model="line.sku"
                                    required
                                    placeholder="CON-001"
                                />
                                <InputError
                                    :message="batchLineError(index, 'sku')"
                                />
                            </div>

                            <div
                                v-if="
                                    findCatalogProductBySku(line.sku)?.type !==
                                    'asset'
                                "
                                class="grid gap-2"
                            >
                                <Label :for="`batch-mobile-qty-${index}`">
                                    Quantity (consumables)
                                </Label>
                                <Input
                                    :id="`batch-mobile-qty-${index}`"
                                    v-model="line.qty"
                                    type="number"
                                    min="1"
                                    placeholder="10"
                                />
                                <InputError
                                    :message="batchLineError(index, 'qty')"
                                />
                            </div>

                            <div
                                v-if="
                                    !findCatalogProductBySku(line.sku) ||
                                    findCatalogProductBySku(line.sku)?.type ===
                                        'asset'
                                "
                                class="grid gap-2"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <Label :for="`batch-mobile-tags-${index}`">
                                        Asset tag codes
                                    </Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="min-h-11 w-full sm:w-auto"
                                        @click="
                                            openQrScanner({
                                                title: 'Batch scan line asset tags',
                                                description:
                                                    'Scan one or more asset tags to append them to this delivery line.',
                                                continuous: true,
                                                closeOnScan: false,
                                                onScanned: (value) =>
                                                    appendBatchTagCode(
                                                        index,
                                                        value,
                                                    ),
                                            })
                                        "
                                    >
                                        <QrCode
                                            aria-hidden="true"
                                            class="mr-2 size-4"
                                        />
                                        Batch scan tags
                                    </Button>
                                    <OcrAssistButton
                                        v-if="
                                            findCatalogProductBySku(line.sku)
                                                ?.type === 'asset'
                                        "
                                        label="Read printed tags"
                                        title="Read batch receiving tags"
                                        description="Review institutional tags for this line, not manufacturer serial numbers. Keep physical acceptance explicit."
                                        field-label="Institutional asset tags, one per line"
                                        submit-label="Add reviewed tags"
                                        multiline
                                        @reviewed="
                                            (value) =>
                                                parseTagCodes(value).forEach(
                                                    (tag) =>
                                                        appendBatchTagCode(
                                                            index,
                                                            tag,
                                                        ),
                                                )
                                        "
                                    />
                                </div>
                                <textarea
                                    :id="`batch-mobile-tags-${index}`"
                                    v-model="line.tag_codes_text"
                                    rows="3"
                                    placeholder="TAG-0001&#10;TAG-0002"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                />
                                <ReceivingSerialFields
                                    v-if="
                                        findCatalogProductBySku(line.sku)
                                            ?.type === 'asset'
                                    "
                                    v-model="line.serials"
                                    :tags="parseTagCodes(line.tag_codes_text)"
                                />
                                <InputError
                                    :message="
                                        batchLineError(index, 'tag_codes')
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`batch-mobile-reference-${index}`">
                                    Reference no.
                                </Label>
                                <Input
                                    :id="`batch-mobile-reference-${index}`"
                                    v-model="line.reference_no"
                                    placeholder="DR / PO / Invoice"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-2">
                                    <Label
                                        :for="`batch-mobile-received-${index}`"
                                    >
                                        Date received
                                    </Label>
                                    <Input
                                        :id="`batch-mobile-received-${index}`"
                                        v-model="line.received_at"
                                        type="date"
                                    />
                                </div>
                                <div
                                    v-if="
                                        findCatalogProductBySku(line.sku)
                                            ?.type !== 'asset'
                                    "
                                    class="grid gap-2"
                                >
                                    <Label
                                        :for="`batch-mobile-expires-${index}`"
                                    >
                                        Expiry date
                                    </Label>
                                    <Input
                                        :id="`batch-mobile-expires-${index}`"
                                        v-model="line.expires_at"
                                        type="date"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Batch Lines -->
                <div class="mt-4 hidden space-y-3 md:block">
                    <div
                        v-for="(line, index) in batchForm.lines"
                        :key="line.localKey"
                        class="rounded-xl border border-border/70 bg-background p-4 shadow-2xs"
                    >
                        <div
                            class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-border/50 pb-2.5"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="outline" class="font-mono">
                                    Line #{{ index + 1 }}
                                </Badge>
                                <template
                                    v-if="findCatalogProductBySku(line.sku)"
                                >
                                    <span
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            findCatalogProductBySku(line.sku)
                                                ?.name
                                        }}
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        class="text-[10px] capitalize"
                                    >
                                        {{
                                            findCatalogProductBySku(line.sku)
                                                ?.type
                                        }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        (On hand:
                                        {{
                                            findCatalogProductBySku(line.sku)
                                                ?.on_hand_qty ?? 0
                                        }})
                                    </span>
                                </template>
                            </div>

                            <Button
                                v-if="batchForm.lines.length > 1"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs text-destructive hover:bg-destructive/10"
                                @click="removeBatchLine(index)"
                            >
                                <Trash2 class="mr-1 size-3.5" />
                                Remove line
                            </Button>
                        </div>

                        <div class="grid gap-3 md:grid-cols-5">
                            <div class="grid content-start gap-1.5">
                                <div
                                    class="flex items-center justify-between gap-1"
                                >
                                    <Label
                                        :for="`batch-sku-${index}`"
                                        class="text-xs font-medium"
                                        >SKU</Label
                                    >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="min-h-11 w-full sm:w-auto"
                                        @click="
                                            openQrScanner({
                                                title: 'Scan batch line SKU',
                                                description:
                                                    'Scan a product QR code to fill this batch line SKU.',
                                                onScanned: (value) =>
                                                    applyBatchLineQrScanned(
                                                        index,
                                                        value,
                                                    ),
                                            })
                                        "
                                    >
                                        <QrCode
                                            aria-hidden="true"
                                            class="mr-2 size-4"
                                        />
                                        Scan SKU
                                    </Button>
                                </div>
                                <Input
                                    :id="`batch-sku-${index}`"
                                    v-model="line.sku"
                                    required
                                    placeholder="CON-001"
                                    class="font-mono"
                                />
                                <InputError
                                    :message="batchLineError(index, 'sku')"
                                />
                            </div>

                            <div
                                v-if="
                                    findCatalogProductBySku(line.sku)?.type !==
                                    'asset'
                                "
                                class="grid content-start gap-1.5"
                            >
                                <Label
                                    :for="`batch-qty-${index}`"
                                    class="text-xs font-medium"
                                >
                                    Quantity (consumables)
                                </Label>
                                <Input
                                    :id="`batch-qty-${index}`"
                                    v-model="line.qty"
                                    type="number"
                                    min="1"
                                    placeholder="10"
                                />
                                <InputError
                                    :message="batchLineError(index, 'qty')"
                                />
                            </div>

                            <div class="grid content-start gap-1.5">
                                <Label
                                    :for="`batch-reference-${index}`"
                                    class="text-xs font-medium"
                                >
                                    Reference no.
                                </Label>
                                <Input
                                    :id="`batch-reference-${index}`"
                                    v-model="line.reference_no"
                                    placeholder="DR / PO / Invoice"
                                />
                                <InputError
                                    :message="
                                        batchLineError(index, 'reference_no')
                                    "
                                />
                            </div>

                            <div class="grid content-start gap-1.5">
                                <Label
                                    :for="`batch-received-${index}`"
                                    class="text-xs font-medium"
                                >
                                    Date received
                                </Label>
                                <Input
                                    :id="`batch-received-${index}`"
                                    v-model="line.received_at"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        batchLineError(index, 'received_at')
                                    "
                                />
                            </div>

                            <div
                                v-if="
                                    findCatalogProductBySku(line.sku)?.type !==
                                    'asset'
                                "
                                class="grid content-start gap-1.5"
                            >
                                <Label
                                    :for="`batch-expires-${index}`"
                                    class="text-xs font-medium"
                                >
                                    Expiry date
                                </Label>
                                <Input
                                    :id="`batch-expires-${index}`"
                                    v-model="line.expires_at"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        batchLineError(index, 'expires_at')
                                    "
                                />
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div
                                v-if="
                                    !findCatalogProductBySku(line.sku) ||
                                    findCatalogProductBySku(line.sku)?.type ===
                                        'asset'
                                "
                                class="grid gap-1.5"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <Label
                                        :for="`batch-tags-${index}`"
                                        class="text-xs font-medium"
                                    >
                                        Asset tag codes (for asset SKUs)
                                    </Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="min-h-11 w-full sm:w-auto"
                                        @click="
                                            openQrScanner({
                                                title: 'Batch scan line asset tags',
                                                description:
                                                    'Scan one or more asset tags to append them to this delivery line.',
                                                continuous: true,
                                                closeOnScan: false,
                                                onScanned: (value) =>
                                                    appendBatchTagCode(
                                                        index,
                                                        value,
                                                    ),
                                            })
                                        "
                                    >
                                        <QrCode
                                            aria-hidden="true"
                                            class="mr-2 size-4"
                                        />
                                        Batch scan tags
                                    </Button>
                                </div>
                                <textarea
                                    :id="`batch-tags-${index}`"
                                    v-model="line.tag_codes_text"
                                    rows="2"
                                    placeholder="TAG-0001&#10;TAG-0002"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                />
                                <OcrAssistButton
                                    v-if="
                                        findCatalogProductBySku(line.sku)
                                            ?.type === 'asset'
                                    "
                                    label="Read printed tags"
                                    title="Read batch receiving tags"
                                    description="Review institutional tags for this line, not manufacturer serial numbers."
                                    field-label="Institutional asset tags, one per line"
                                    submit-label="Add reviewed tags"
                                    multiline
                                    @reviewed="
                                        (value) =>
                                            parseTagCodes(value).forEach(
                                                (tag) =>
                                                    appendBatchTagCode(
                                                        index,
                                                        tag,
                                                    ),
                                            )
                                    "
                                />
                                <ReceivingSerialFields
                                    v-if="
                                        findCatalogProductBySku(line.sku)
                                            ?.type === 'asset'
                                    "
                                    v-model="line.serials"
                                    :tags="parseTagCodes(line.tag_codes_text)"
                                />
                                <InputError
                                    :message="
                                        batchLineError(index, 'tag_codes')
                                    "
                                />
                            </div>

                            <div class="grid gap-1.5">
                                <Label
                                    :for="`batch-notes-${index}`"
                                    class="text-xs font-medium"
                                    >Notes</Label
                                >
                                <textarea
                                    :id="`batch-notes-${index}`"
                                    v-model="line.notes"
                                    rows="2"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                                <InputError
                                    :message="batchLineError(index, 'notes')"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batch Submission Footer -->
                <ReceivingBatchSummaryFooter
                    :populated-lines="batchSummary.populatedLines"
                    :total-consumable-qty="batchSummary.totalConsumableQty"
                    :total-asset-tags="batchSummary.totalAssetTags"
                    :processing="batchForm.processing"
                />
            </div>
        </form>

        <!-- ========================================================== -->
        <!-- MODE 3: PURCHASE ORDER RECEIVING WORKFLOW                  -->
        <!-- ========================================================== -->
        <div v-else-if="mode === 'po'" class="grid gap-6">
            <div
                v-if="purchaseOrders.length === 0"
                class="rounded-2xl border border-border/70 bg-card p-8 text-center shadow-xs"
            >
                <div
                    class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                >
                    <FileSpreadsheet class="size-6" />
                </div>
                <h2 class="mt-3 text-base font-semibold">
                    No open purchase orders awaiting delivery
                </h2>
                <p class="mx-auto mt-1 max-w-md text-xs text-muted-foreground">
                    Only purchase orders with status
                    <strong>Sent</strong> or
                    <strong>Partially Received</strong> can accept goods. You
                    can still receive stock directly using Single Item or Batch
                    Delivery modes.
                </p>
                <div class="mt-4 flex justify-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="mode = 'single'"
                    >
                        Switch to Single Item
                    </Button>
                </div>
            </div>

            <form v-else class="grid gap-6" @submit.prevent="submitPoReceipt">
                <!-- PO Selection & Progress Header Card -->
                <section
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
                >
                    <!-- Card Header & Action Toolbar -->
                    <div
                        class="flex flex-col gap-3 border-b border-border/60 pb-4 xl:flex-row xl:items-center xl:justify-between"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                            >
                                <FileSpreadsheet class="size-4" />
                            </div>
                            <div>
                                <h2
                                    class="text-base font-semibold tracking-tight text-foreground"
                                >
                                    Purchase order receiving
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Select an open purchase order and record
                                    delivered quantities or scan incoming items.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="selectedPo"
                            class="flex shrink-0 flex-wrap items-center gap-2"
                        >
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        PurchaseOrderController.show(
                                            selectedPo.id,
                                        )
                                    "
                                >
                                    <span>View full PO</span>
                                    <ExternalLink class="ml-1.5 size-3.5" />
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="min-h-11 w-full sm:w-auto"
                                @click="
                                    openQrScanner({
                                        title: 'Scan delivered PO product',
                                        description:
                                            'Scan a product QR code to match it against this purchase order and increment its received quantity.',
                                        continuous: true,
                                        closeOnScan: false,
                                        onScanned: handlePoProductMatch,
                                    })
                                "
                            >
                                <QrCode
                                    aria-hidden="true"
                                    class="mr-2 size-4"
                                />
                                Scan Product QR / SKU
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="fillAllPoRemaining"
                            >
                                <CheckCheck class="mr-1.5 size-4" />
                                Receive All Remaining
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="clearAllPoQuantities"
                            >
                                <RotateCcw class="mr-1.5 size-4" />
                                Clear
                            </Button>
                        </div>
                    </div>

                    <!-- Purchase Order Selector + Selected PO Progress Summary -->
                    <div class="mt-4 grid gap-4 lg:grid-cols-12 lg:items-end">
                        <div class="grid min-w-0 gap-2 lg:col-span-7">
                            <Label
                                for="receiving-po-select"
                                class="font-semibold"
                            >
                                Select open purchase order to receive against
                            </Label>
                            <select
                                id="receiving-po-select"
                                v-model="selectedPoId"
                                class="h-10 w-full max-w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm font-medium"
                            >
                                <option
                                    v-for="po in purchaseOrders"
                                    :key="po.id"
                                    :value="String(po.id)"
                                >
                                    {{ po.po_number }} —
                                    {{
                                        po.supplier?.name ?? 'Unknown Supplier'
                                    }}
                                    ({{ po.progress_pct }}% received)
                                </option>
                            </select>
                        </div>

                        <div
                            v-if="selectedPo"
                            class="flex min-w-0 flex-col justify-between gap-2 rounded-xl border border-border/60 bg-muted/25 px-4 py-2.5 text-xs lg:col-span-5"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="min-w-0 truncate">
                                    <span class="text-muted-foreground"
                                        >Supplier:</span
                                    >
                                    <strong class="ml-1 text-foreground">
                                        {{
                                            selectedPo.supplier?.name ??
                                            'Unknown Supplier'
                                        }}
                                    </strong>
                                </div>
                                <Badge
                                    variant="outline"
                                    class="shrink-0 font-mono text-[11px]"
                                >
                                    {{ selectedPo.progress_pct }}% received
                                </Badge>
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all"
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, selectedPo.progress_pct ?? 0))}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Selected PO Summary & Search Bar -->
                    <div
                        v-if="selectedPo"
                        class="mt-4 grid gap-4 rounded-xl border border-border/60 bg-muted/20 p-4 lg:grid-cols-2"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="po-mode-search"
                                class="text-xs font-semibold"
                            >
                                Search PO items by name or enter SKU + Enter to
                                match
                            </Label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        id="po-mode-search"
                                        v-model="poLineSearchQuery"
                                        type="search"
                                        placeholder="Filter PO items by product name or SKU…"
                                        class="bg-background pl-9"
                                        @keydown.enter.prevent="
                                            handlePoProductMatch(
                                                poLineSearchQuery,
                                            )
                                        "
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="
                                        handlePoProductMatch(poLineSearchQuery)
                                    "
                                >
                                    Match +1
                                </Button>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="po-mode-shared-ref"
                                class="text-xs font-semibold"
                            >
                                Delivery receipt reference &amp; date
                            </Label>
                            <div
                                class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_150px]"
                            >
                                <Input
                                    id="po-mode-shared-ref"
                                    v-model="poSharedRef"
                                    placeholder="DR / Invoice no."
                                    class="bg-background"
                                />
                                <OcrAssistButton
                                    label="Read delivery reference"
                                    title="Read PO delivery reference"
                                    description="Review a delivery-document reference for this purchase order. Quantities and product details still come from the selected order."
                                    field-label="Delivery reference (editable)"
                                    submit-label="Apply reviewed reference"
                                    @reviewed="
                                        poSharedRef = $event.slice(0, 64)
                                    "
                                />
                                <Input
                                    id="po-mode-shared-date"
                                    v-model="poSharedDate"
                                    type="date"
                                    aria-label="Received date"
                                    class="bg-background"
                                />
                            </div>
                        </div>
                    </div>

                    <InputError :message="poReceiveErrors.lines" class="mt-3" />
                    <InputError
                        v-for="[key, message] in Object.entries(
                            poReceiveErrors,
                        ).filter(([key]) => key.includes('serial_numbers'))"
                        :key="key"
                        :message="message"
                    />

                    <!-- PO Lines List -->
                    <div v-if="selectedPo" class="mt-4 space-y-3">
                        <div
                            v-for="index in filteredPoLineIndices"
                            :key="selectedPo.lines[index].id"
                            class="rounded-xl border p-4 transition"
                            :class="
                                selectedPo.lines[index].qty_remaining === 0
                                    ? 'border-border/50 bg-muted/20 opacity-65'
                                    : 'border-border/70 bg-background'
                            "
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{
                                        selectedPo.lines[index].product?.name
                                    }}</span>
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-xs"
                                    >
                                        {{
                                            selectedPo.lines[index].product?.sku
                                        }}
                                    </Badge>
                                    <span
                                        v-if="
                                            selectedPo.lines[index].product
                                                ?.variant
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            selectedPo.lines[index].product
                                                ?.variant
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            selectedPo.lines[index].product
                                                ?.unit
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        per
                                        {{
                                            selectedPo.lines[index].product
                                                ?.unit
                                        }}
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        class="text-[11px] capitalize"
                                    >
                                        {{
                                            poReceiveForm.lines[index]
                                                ?.product_type
                                        }}
                                    </Badge>
                                </div>

                                <div
                                    class="flex flex-wrap items-center gap-2 text-xs"
                                >
                                    <span>
                                        Ordered:
                                        <strong>{{
                                            selectedPo.lines[index].qty_ordered
                                        }}</strong>
                                    </span>
                                    <span>•</span>
                                    <span>
                                        Received:
                                        <strong
                                            class="text-emerald-600 dark:text-emerald-400"
                                            >{{
                                                selectedPo.lines[index]
                                                    .qty_received
                                            }}</strong
                                        >
                                    </span>
                                    <span>•</span>
                                    <Badge variant="outline">
                                        Remaining:
                                        {{
                                            selectedPo.lines[index]
                                                .qty_remaining
                                        }}
                                    </Badge>
                                </div>
                            </div>

                            <div
                                v-if="
                                    selectedPo.lines[index].qty_remaining > 0 &&
                                    poReceiveForm.lines[index]
                                "
                                class="mt-3 grid gap-3 md:grid-cols-4"
                            >
                                <div
                                    v-if="
                                        poReceiveForm.lines[index]
                                            .product_type !== 'asset'
                                    "
                                    class="grid gap-1.5"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <Label
                                            :for="`po-mode-qty-${index}`"
                                            class="text-xs font-medium"
                                        >
                                            Receiving now
                                        </Label>
                                        <button
                                            type="button"
                                            class="text-[11px] font-semibold text-primary hover:underline"
                                            @click="fillPoLineRemaining(index)"
                                        >
                                            Max ({{
                                                selectedPo.lines[index]
                                                    .qty_remaining
                                            }})
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="size-9 shrink-0"
                                            @click="adjustPoLineQty(index, -1)"
                                        >
                                            <Minus class="size-3.5" />
                                        </Button>
                                        <Input
                                            :id="`po-mode-qty-${index}`"
                                            v-model="
                                                poReceiveForm.lines[index]
                                                    .qty_received
                                            "
                                            type="number"
                                            min="0"
                                            :max="
                                                selectedPo.lines[index]
                                                    .qty_remaining
                                            "
                                            placeholder="0"
                                            class="text-center font-semibold"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="size-9 shrink-0"
                                            @click="adjustPoLineQty(index, 1)"
                                        >
                                            <Plus class="size-3.5" />
                                        </Button>
                                    </div>
                                    <InputError
                                        :message="
                                            poReceiveErrors[
                                                `lines.${index}.qty_received`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label
                                        :for="`po-mode-ref-${index}`"
                                        class="text-xs font-medium"
                                    >
                                        Reference no.
                                    </Label>
                                    <Input
                                        :id="`po-mode-ref-${index}`"
                                        v-model="
                                            poReceiveForm.lines[index]
                                                .reference_no
                                        "
                                        :placeholder="
                                            poSharedRef || 'DR / Invoice'
                                        "
                                    />
                                </div>

                                <div
                                    v-if="
                                        poReceiveForm.lines[index]
                                            .product_type === 'consumable'
                                    "
                                    class="grid gap-1.5"
                                >
                                    <Label
                                        :for="`po-mode-exp-${index}`"
                                        class="text-xs font-medium"
                                    >
                                        Expiry date
                                    </Label>
                                    <Input
                                        :id="`po-mode-exp-${index}`"
                                        v-model="
                                            poReceiveForm.lines[index]
                                                .expires_at
                                        "
                                        type="date"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label
                                        :for="`po-mode-notes-${index}`"
                                        class="text-xs font-medium"
                                    >
                                        Notes
                                    </Label>
                                    <Input
                                        :id="`po-mode-notes-${index}`"
                                        v-model="
                                            poReceiveForm.lines[index].notes
                                        "
                                        placeholder="Condition / lot remarks"
                                    />
                                </div>
                            </div>

                            <!-- Asset Tags for Asset PO Lines -->
                            <div
                                v-if="
                                    selectedPo.lines[index].qty_remaining > 0 &&
                                    poReceiveForm.lines[index]?.product_type ===
                                        'asset'
                                "
                                class="mt-3 grid gap-2 rounded-lg border border-border/60 bg-muted/20 p-3"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <Label
                                        :for="`po-mode-tags-${index}`"
                                        class="text-xs font-semibold"
                                    >
                                        Asset tag codes ({{
                                            parseTagCodes(
                                                poReceiveForm.lines[index]
                                                    .tag_codes_text,
                                            ).length
                                        }}
                                        /
                                        {{
                                            selectedPo.lines[index]
                                                .qty_remaining
                                        }}
                                        remaining)
                                    </Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="min-h-11 w-full sm:w-auto"
                                        @click="
                                            openQrScanner({
                                                title: 'Scan PO asset tags',
                                                description:
                                                    'Scan serialized asset tags for this purchase order line.',
                                                continuous: true,
                                                closeOnScan: false,
                                                onScanned: (value) =>
                                                    appendPoAssetTag(
                                                        index,
                                                        value,
                                                    ),
                                            })
                                        "
                                    >
                                        <QrCode
                                            aria-hidden="true"
                                            class="mr-2 size-4"
                                        />
                                        Scan Asset Tags
                                    </Button>
                                    <OcrAssistButton
                                        label="Read printed tags"
                                        title="Read PO receiving tags"
                                        description="Review institutional asset tags for this PO line. Manufacturer serial numbers are separate and must not replace tags."
                                        field-label="Institutional asset tags, one per line"
                                        submit-label="Add reviewed tags"
                                        multiline
                                        @reviewed="
                                            (value) =>
                                                parseTagCodes(value).forEach(
                                                    (tag) =>
                                                        appendPoAssetTag(
                                                            index,
                                                            tag,
                                                        ),
                                                )
                                        "
                                    />
                                </div>
                                <textarea
                                    :id="`po-mode-tags-${index}`"
                                    v-model="
                                        poReceiveForm.lines[index]
                                            .tag_codes_text
                                    "
                                    rows="2"
                                    placeholder="AST-0001&#10;AST-0002"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                />
                                <ReceivingSerialFields
                                    v-model="poReceiveForm.lines[index].serials"
                                    :tags="
                                        parseTagCodes(
                                            poReceiveForm.lines[index]
                                                .tag_codes_text,
                                        )
                                    "
                                />
                                <InputError
                                    :message="
                                        poReceiveErrors[
                                            `lines.${index}.tag_codes`
                                        ]
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <!-- PO Receipt Submit Bar -->
                    <div
                        class="mt-5 flex flex-col gap-3 rounded-xl border border-border/70 bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="text-xs text-muted-foreground">
                            Ready to receive on
                            <strong>{{ selectedPo?.po_number }}</strong
                            >:
                            <strong class="text-foreground"
                                >{{
                                    poReceiptStats.activeLines
                                }}
                                line(s)</strong
                            >
                            ({{ poReceiptStats.totalUnitsNow }} unit(s))
                        </div>

                        <Button
                            type="submit"
                            :disabled="poReceiveForm.processing"
                            class="w-full sm:w-auto"
                        >
                            <PackageCheck class="mr-1.5 size-4" />
                            {{
                                poReceiveForm.processing
                                    ? 'Recording PO receipt…'
                                    : 'Record Purchase Order Receipt'
                            }}
                        </Button>
                    </div>
                </section>
            </form>
        </div>

        <!-- Catalog Browser Modal -->
        <ProductSelectDialog
            v-model:open="isCatalogOpen"
            :title="
                catalogTarget === 'single'
                    ? 'Select product to receive'
                    : 'Browse products for batch receiving'
            "
            description="Search by product name or SKU, filter by category or type, and select items to receive."
            type-filter="all"
            :only-in-stock-default="false"
            :close-on-select="catalogTarget === 'single'"
            :exclude-skus="catalogTarget === 'batch' ? batchSelectedSkus : []"
            @select="handleCatalogSelection"
        />

        <!-- Shared Camera QR Scanner Dialog -->
        <QrScannerDialog
            v-model:open="isQrScannerOpen"
            hide-trigger
            :title="activeQrScanner?.title ?? 'Scan QR code'"
            :description="
                activeQrScanner?.description ??
                'Point the camera at a QR code or enter it manually.'
            "
            :continuous="activeQrScanner?.continuous ?? false"
            :close-on-scan="activeQrScanner?.closeOnScan"
            @scanned="handleSharedQrScanned"
        />
    </div>
</template>
