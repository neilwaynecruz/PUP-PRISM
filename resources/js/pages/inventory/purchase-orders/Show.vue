<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Building2,
    CheckCheck,
    CheckCircle2,
    ExternalLink,
    FileSpreadsheet,
    Minus,
    PackageCheck,
    Plus,
    RotateCcw,
    Search,
    Send,
    Truck,
    UserCheck,
    XCircle,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
import SupplierController from '@/actions/App/Http/Controllers/Inventory/SupplierController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';

const QrScannerDialog = defineAsyncComponent(
    () => import('@/components/inventory/QrScannerDialog.vue'),
);
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as purchaseOrdersIndex,
    show as purchaseOrdersShow,
} from '@/routes/inventory/purchase-orders';
import { index as receivingIndex } from '@/routes/inventory/receiving';

type PurchaseOrderLineItem = {
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

type PurchaseOrderPayload = {
    id: number;
    po_number: string;
    status: string;
    status_label?: string;
    subtotal: number;
    tax: number;
    total_amount: number;
    expected_delivery_at?: string | null;
    sent_at?: string | null;
    received_at?: string | null;
    notes?: string | null;
    progress_pct: number;
    supplier?: {
        id: number;
        name: string;
        contact_person?: string | null;
        email?: string | null;
        phone?: string | null;
        payment_terms?: string | null;
        lead_time_days?: number | null;
    } | null;
    requester?: {
        name: string;
        email: string;
    } | null;
    approver?: {
        name: string;
        email: string;
    } | null;
    lines: PurchaseOrderLineItem[];
};

const props = defineProps<{
    purchaseOrder: PurchaseOrderPayload;
    can: {
        send: boolean;
        receive: boolean;
        cancel: boolean;
    };
}>();

defineOptions({
    name: 'InventoryPurchaseOrderShowPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: purchaseOrdersIndex() },
            { title: 'Purchase Orders', href: purchaseOrdersIndex() },
            { title: 'View', href: purchaseOrdersShow(0) },
        ],
    },
});

type ReceiveLineFormState = {
    purchase_order_line_id: number;
    product_type: string;
    qty_remaining: number;
    qty_received: string;
    reference_no: string;
    received_at: string;
    expires_at: string;
    tag_codes_text: string;
    notes: string;
};

const receiveForm = useForm<{
    lines: ReceiveLineFormState[];
    idempotency_key: string;
}>({
    lines: props.purchaseOrder.lines.map((line) => ({
        purchase_order_line_id: line.id,
        product_type: line.product?.type ?? 'consumable',
        qty_remaining: line.qty_remaining,
        qty_received: '',
        reference_no: '',
        received_at: '',
        expires_at: '',
        tag_codes_text: '',
        notes: '',
    })),
    idempotency_key: '',
});

const receiveSearchQuery = ref('');
const sharedReferenceNo = ref('');
const sharedReceivedAt = ref(new Date().toISOString().slice(0, 10));
const receiveFeedback = ref<{
    type: 'info' | 'warning' | 'success';
    text: string;
} | null>(null);

function showReceiveFeedback(
    text: string,
    type: 'info' | 'warning' | 'success' = 'info',
): void {
    receiveFeedback.value = { type, text };
    setTimeout(() => {
        if (receiveFeedback.value?.text === text) {
            receiveFeedback.value = null;
        }
    }, 4500);
}

const receiveErrors = computed(
    () => receiveForm.errors as Record<string, string | undefined>,
);

const totalOrderedUnits = computed(() =>
    props.purchaseOrder.lines.reduce((sum, l) => sum + l.qty_ordered, 0),
);

const totalReceivedUnits = computed(() =>
    props.purchaseOrder.lines.reduce((sum, l) => sum + l.qty_received, 0),
);

const totalRemainingUnits = computed(() =>
    props.purchaseOrder.lines.reduce((sum, l) => sum + l.qty_remaining, 0),
);

const filteredReceiveIndices = computed<number[]>(() => {
    const q = receiveSearchQuery.value.trim().toLowerCase();

    return props.purchaseOrder.lines
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

const pendingReceiptSummary = computed(() => {
    let activeLines = 0;
    let totalReceivingNow = 0;

    receiveForm.lines.forEach((line) => {
        if (line.product_type === 'asset') {
            const tags = parseTagCodes(line.tag_codes_text);

            if (tags.length > 0) {
                activeLines += 1;
                totalReceivingNow += tags.length;
            }
        } else {
            const qty = Number(line.qty_received || 0);

            if (qty > 0) {
                activeLines += 1;
                totalReceivingNow += qty;
            }
        }
    });

    return { activeLines, totalReceivingNow };
});

function createIdempotencyKey(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `po-${props.purchaseOrder.id}-${Date.now()}`;
}

function parseTagCodes(value: string): string[] {
    return value
        .split(/\r?\n|,/)
        .map((entry) => entry.trim())
        .filter(Boolean);
}

function appendLineAssetTag(index: number, rawCode: string): void {
    const lineState = receiveForm.lines[index];

    if (!lineState) {
        return;
    }

    const cleaned = rawCode.trim();

    if (!cleaned) {
        return;
    }

    const currentTags = parseTagCodes(lineState.tag_codes_text);

    if (currentTags.includes(cleaned)) {
        showReceiveFeedback(
            `Asset tag "${cleaned}" is already listed for this item.`,
            'warning',
        );

        return;
    }

    if (currentTags.length >= lineState.qty_remaining) {
        showReceiveFeedback(
            `Cannot add more tags: all ${lineState.qty_remaining} remaining unit(s) for this line are already accounted for.`,
            'warning',
        );

        return;
    }

    lineState.tag_codes_text = [...currentTags, cleaned].join('\n');
    showReceiveFeedback(`Captured asset tag "${cleaned}".`, 'success');
}

function fillLineRemaining(index: number): void {
    const lineState = receiveForm.lines[index];

    if (
        !lineState ||
        lineState.product_type === 'asset' ||
        lineState.qty_remaining < 1
    ) {
        return;
    }

    lineState.qty_received = String(lineState.qty_remaining);
}

function adjustReceiveQty(index: number, delta: number): void {
    const lineState = receiveForm.lines[index];

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

function fillAllRemainingQuantities(): void {
    let updatedCount = 0;
    receiveForm.lines.forEach((line) => {
        if (line.product_type !== 'asset' && line.qty_remaining > 0) {
            line.qty_received = String(line.qty_remaining);
            updatedCount += 1;
        }
    });

    if (updatedCount > 0) {
        showReceiveFeedback(
            `Populated remaining quantities for ${updatedCount} consumable line(s).`,
            'success',
        );
    } else {
        showReceiveFeedback(
            'Asset lines require individual tag codes. Consumable lines have no remaining quantity.',
            'info',
        );
    }
}

function clearAllReceivingQuantities(): void {
    receiveForm.lines.forEach((line) => {
        line.qty_received = '';
        line.tag_codes_text = '';
    });
    showReceiveFeedback('Cleared all pending receipt quantities.', 'info');
}

function applySharedMetadataToAll(): void {
    receiveForm.lines.forEach((line) => {
        if (line.qty_remaining > 0) {
            if (sharedReferenceNo.value.trim() !== '') {
                line.reference_no = sharedReferenceNo.value.trim();
            }

            if (sharedReceivedAt.value !== '') {
                line.received_at = sharedReceivedAt.value;
            }
        }
    });
    showReceiveFeedback(
        'Applied delivery reference and received date to all open lines.',
        'success',
    );
}

function handleProductScanOrQuickMatch(rawCode: string): void {
    const cleaned = rawCode.trim();

    if (!cleaned) {
        return;
    }

    const matchedIndex = props.purchaseOrder.lines.findIndex(
        (line) =>
            line.product?.sku?.toLowerCase() === cleaned.toLowerCase() ||
            line.product?.name?.toLowerCase() === cleaned.toLowerCase(),
    );

    if (matchedIndex === -1) {
        showReceiveFeedback(
            `No product on this purchase order matched "${cleaned}".`,
            'warning',
        );

        return;
    }

    const poLine = props.purchaseOrder.lines[matchedIndex];
    const formLine = receiveForm.lines[matchedIndex];

    if (poLine.qty_remaining <= 0) {
        showReceiveFeedback(
            `${poLine.product?.sku} is already fully received (${poLine.qty_received}/${poLine.qty_ordered}).`,
            'warning',
        );

        return;
    }

    if (formLine.product_type === 'asset') {
        receiveSearchQuery.value = poLine.product?.sku ?? '';
        showReceiveFeedback(
            `Matched asset ${poLine.product?.sku}. Scan or enter its asset tag codes below.`,
            'info',
        );

        return;
    }

    const currentQty = Math.max(0, Number(formLine.qty_received) || 0);

    if (currentQty >= poLine.qty_remaining) {
        showReceiveFeedback(
            `${poLine.product?.sku} is already set to receive the maximum remaining quantity (${poLine.qty_remaining}).`,
            'warning',
        );

        return;
    }

    const nextQty = currentQty + 1;
    formLine.qty_received = String(nextQty);
    showReceiveFeedback(
        `Matched ${poLine.product?.sku} (${poLine.product?.name}) — receiving quantity set to ${nextQty} of ${poLine.qty_remaining}.`,
        'success',
    );
}

function submitReceipt(): void {
    receiveForm.clearErrors();

    const lines = receiveForm.lines
        .map((line) => {
            const tagCodes = parseTagCodes(line.tag_codes_text);
            const qtyReceived =
                line.product_type === 'asset'
                    ? tagCodes.length
                    : Number(line.qty_received || 0);

            if (line.qty_remaining <= 0) {
                return null;
            }

            if (line.product_type === 'asset' && tagCodes.length === 0) {
                return null;
            }

            if (line.product_type !== 'asset' && qtyReceived <= 0) {
                return null;
            }

            return {
                purchase_order_line_id: line.purchase_order_line_id,
                qty_received: qtyReceived > 0 ? qtyReceived : null,
                reference_no:
                    line.reference_no.trim() !== ''
                        ? line.reference_no.trim()
                        : sharedReferenceNo.value.trim() !== ''
                          ? sharedReferenceNo.value.trim()
                          : null,
                received_at: line.received_at || sharedReceivedAt.value || null,
                expires_at:
                    line.product_type === 'consumable'
                        ? line.expires_at || null
                        : null,
                tag_codes: line.product_type === 'asset' ? tagCodes : null,
                notes: line.notes.trim() !== '' ? line.notes.trim() : null,
            };
        })
        .filter((entry): entry is NonNullable<typeof entry> => entry !== null);

    if (lines.length === 0) {
        receiveForm.setError(
            'lines',
            'Enter at least one received quantity or asset tag before submitting.',
        );

        return;
    }

    const idempotencyKey = createIdempotencyKey();
    receiveForm.idempotency_key = idempotencyKey;

    receiveForm
        .transform(() => ({
            lines,
            idempotency_key: idempotencyKey,
        }))
        .post(PurchaseOrderController.receive(props.purchaseOrder.id).url, {
            preserveScroll: true,
        });
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'received') {
        return 'default';
    }

    if (status === 'sent' || status === 'partial') {
        return 'secondary';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    return 'outline';
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function formatMoney(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(value || 0);
}

const lifecycleSteps = computed(() => {
    const status = props.purchaseOrder.status;

    return [
        {
            key: 'draft',
            label: 'Draft Created',
            done: true,
            active: status === 'draft',
        },
        {
            key: 'sent',
            label: 'Sent to Supplier',
            done: ['sent', 'partial', 'received'].includes(status),
            active: status === 'sent',
        },
        {
            key: 'partial',
            label: 'Receiving Goods',
            done: ['partial', 'received'].includes(status),
            active: status === 'partial',
        },
        {
            key: 'received',
            label: 'Fully Received',
            done: status === 'received',
            active: status === 'received',
        },
    ];
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <Head :title="purchaseOrder.po_number" />

        <!-- Top Hero Card -->
        <div
            class="relative overflow-hidden rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-primary/5 blur-2xl"
            />

            <div
                class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-2xs"
                    >
                        <FileSpreadsheet class="size-5" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Heading
                                :title="purchaseOrder.po_number"
                                :description="
                                    purchaseOrder.supplier?.name ??
                                    'Purchase order details'
                                "
                            />
                            <Badge
                                :variant="statusVariant(purchaseOrder.status)"
                                class="text-xs capitalize"
                            >
                                {{
                                    purchaseOrder.status_label ??
                                    purchaseOrder.status
                                }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Form
                        v-if="can.send && purchaseOrder.status === 'draft'"
                        v-bind="
                            PurchaseOrderController.send.form(purchaseOrder.id)
                        "
                        v-slot="{ processing }"
                    >
                        <Button type="submit" :disabled="processing">
                            <Send class="mr-1.5 size-4" />
                            {{ processing ? 'Sending…' : 'Send to supplier' }}
                        </Button>
                    </Form>

                    <Button
                        v-if="
                            can.receive &&
                            ['sent', 'partial'].includes(purchaseOrder.status)
                        "
                        as-child
                        variant="default"
                    >
                        <a href="#po-receive-section">
                            <PackageCheck class="mr-1.5 size-4" />
                            Receive items ({{ totalRemainingUnits }} left)
                        </a>
                    </Button>

                    <Button
                        v-if="
                            can.receive &&
                            ['sent', 'partial'].includes(purchaseOrder.status)
                        "
                        as-child
                        variant="secondary"
                    >
                        <Link
                            :href="`${receivingIndex().url}?purchase_order_id=${purchaseOrder.id}`"
                        >
                            <Truck class="mr-1.5 size-4" />
                            Open in Receiving
                        </Link>
                    </Button>

                    <Button as-child variant="outline">
                        <Link :href="purchaseOrdersIndex()">
                            <ArrowLeft class="mr-1.5 size-4" />
                            Back to purchase orders
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Lifecycle Stepper & Fulfillment Progress Bar -->
            <div class="mt-5 border-t border-border/60 pt-4">
                <div
                    v-if="purchaseOrder.status === 'cancelled'"
                    class="flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-2.5 text-xs font-medium text-destructive"
                >
                    <XCircle class="size-4 shrink-0" />
                    <span>
                        This purchase order has been cancelled and can no longer
                        accept deliveries.
                    </span>
                </div>

                <div
                    v-else
                    class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-center"
                >
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div
                            v-for="(step, idx) in lifecycleSteps"
                            :key="step.key"
                            class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs transition"
                            :class="
                                step.active
                                    ? 'border-primary/40 bg-primary/10 font-semibold text-primary'
                                    : step.done
                                      ? 'border-emerald-500/30 bg-emerald-500/5 font-medium text-emerald-700 dark:text-emerald-300'
                                      : 'border-border/60 bg-muted/20 text-muted-foreground'
                            "
                        >
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                :class="
                                    step.active
                                        ? 'bg-primary text-primary-foreground'
                                        : step.done
                                          ? 'bg-emerald-600 text-white'
                                          : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ idx + 1 }}
                            </span>
                            <span class="truncate">{{ step.label }}</span>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-border/60 bg-muted/20 px-3.5 py-2.5"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-muted-foreground"
                                >Fulfillment progress</span
                            >
                            <span class="font-mono font-semibold">
                                {{ totalReceivedUnits }} /
                                {{ totalOrderedUnits }} units ({{
                                    purchaseOrder.progress_pct
                                }}%)
                            </span>
                        </div>
                        <div
                            class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-300"
                                :style="{
                                    width: `${Math.min(100, Math.max(0, purchaseOrder.progress_pct))}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overview Cards Grid (Supplier, Schedule/Audit, Financials) -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Card 1: Supplier Information -->
            <section
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div
                    class="flex items-center justify-between border-b border-border/60 pb-3"
                >
                    <div class="flex items-center gap-2">
                        <Building2 class="size-4 text-primary" />
                        <h2 class="text-sm font-semibold">Supplier details</h2>
                    </div>
                    <Link
                        v-if="purchaseOrder.supplier"
                        :href="
                            SupplierController.show(purchaseOrder.supplier.id)
                        "
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        <span>View vendor</span>
                        <ExternalLink class="size-3" />
                    </Link>
                </div>

                <dl class="mt-3.5 space-y-2 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Company</dt>
                        <dd class="truncate font-semibold text-foreground">
                            {{ purchaseOrder.supplier?.name ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Contact person</dt>
                        <dd class="font-medium text-foreground">
                            {{ purchaseOrder.supplier?.contact_person ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="truncate font-medium text-foreground">
                            {{ purchaseOrder.supplier?.email ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd class="font-medium text-foreground">
                            {{ purchaseOrder.supplier?.phone ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Card 2: Order Timeline & Personnel -->
            <section
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div
                    class="flex items-center gap-2 border-b border-border/60 pb-3"
                >
                    <UserCheck class="size-4 text-primary" />
                    <h2 class="text-sm font-semibold">
                        Authorization &amp; schedule
                    </h2>
                </div>

                <dl class="mt-3.5 space-y-2 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Requested by</dt>
                        <dd class="font-medium text-foreground">
                            {{ purchaseOrder.requester?.name ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Approved by</dt>
                        <dd class="font-medium text-foreground">
                            {{ purchaseOrder.approver?.name ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Expected delivery</dt>
                        <dd class="font-medium text-foreground">
                            {{ formatDate(purchaseOrder.expected_delivery_at) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Sent at</dt>
                        <dd class="font-medium text-foreground">
                            {{ formatDate(purchaseOrder.sent_at) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Received at</dt>
                        <dd class="font-medium text-foreground">
                            {{ formatDate(purchaseOrder.received_at) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Card 3: Financial Breakdown -->
            <section
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
            >
                <div
                    class="flex items-center justify-between border-b border-border/60 pb-3"
                >
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet class="size-4 text-primary" />
                        <h2 class="text-sm font-semibold">Financial summary</h2>
                    </div>
                    <Badge variant="outline" class="text-[11px]">
                        {{ purchaseOrder.lines.length }} line(s)
                    </Badge>
                </div>

                <dl class="mt-3.5 space-y-2 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Subtotal</dt>
                        <dd class="font-mono font-medium text-foreground">
                            {{ formatMoney(purchaseOrder.subtotal) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Tax / adjustments</dt>
                        <dd class="font-mono font-medium text-foreground">
                            {{ formatMoney(purchaseOrder.tax) }}
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-2 border-t border-border/60 pt-2.5"
                    >
                        <dt class="text-sm font-semibold text-foreground">
                            Total amount
                        </dt>
                        <dd class="font-mono text-base font-bold text-primary">
                            {{ formatMoney(purchaseOrder.total_amount) }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="purchaseOrder.notes"
                    class="mt-3 rounded-lg border border-border/60 bg-muted/25 p-2.5 text-xs text-muted-foreground"
                >
                    <span class="font-semibold text-foreground">Notes:</span>
                    <p class="mt-0.5 whitespace-pre-line">
                        {{ purchaseOrder.notes }}
                    </p>
                </div>
            </section>
        </div>

        <!-- Ordered Line Items Section -->
        <section
            class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs sm:p-6"
        >
            <div
                class="flex flex-col gap-2 border-b border-border/60 pb-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-base font-semibold">
                        Ordered products &amp; fulfillment status
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Breakdown of ordered quantities, received deliveries,
                        remaining balances, and unit pricing.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <Badge variant="outline">
                        Ordered: {{ totalOrderedUnits }}
                    </Badge>
                    <Badge variant="secondary">
                        Received: {{ totalReceivedUnits }}
                    </Badge>
                    <Badge
                        :variant="
                            totalRemainingUnits > 0 ? 'outline' : 'default'
                        "
                    >
                        Remaining: {{ totalRemainingUnits }}
                    </Badge>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-border/60 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="pr-4 pb-3">Product</th>
                            <th class="px-3 pb-3 text-center">Type</th>
                            <th class="px-3 pb-3 text-right">Ordered</th>
                            <th class="px-3 pb-3 text-right">Received</th>
                            <th class="px-3 pb-3 text-right">Remaining</th>
                            <th class="px-3 pb-3 text-right">Unit price</th>
                            <th class="px-3 pb-3 text-right">Subtotal</th>
                            <th class="pb-3 pl-3 text-right">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr
                            v-for="line in purchaseOrder.lines"
                            :key="line.id"
                            class="hover:bg-muted/20"
                        >
                            <td class="py-3.5 pr-4">
                                <div class="font-medium text-foreground">
                                    {{
                                        line.product?.name ??
                                        `Product #${line.product_id}`
                                    }}
                                </div>
                                <div
                                    class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span class="font-mono">{{
                                        line.product?.sku ?? 'N/A'
                                    }}</span>
                                    <span v-if="line.product?.variant">
                                        • {{ line.product.variant }}
                                    </span>
                                    <span v-if="line.product?.category">
                                        • {{ line.product.category }}
                                    </span>
                                    <span v-if="line.product?.unit">
                                        • per {{ line.product.unit }}
                                    </span>
                                    <span
                                        v-if="
                                            line.product?.on_hand_qty !==
                                                null &&
                                            line.product?.on_hand_qty !==
                                                undefined
                                        "
                                    >
                                        • Stock on hand:
                                        <strong class="text-foreground">{{
                                            line.product.on_hand_qty
                                        }}</strong>
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3.5 text-center">
                                <Badge
                                    variant="outline"
                                    class="text-[11px] capitalize"
                                >
                                    {{ line.product?.type ?? 'consumable' }}
                                </Badge>
                            </td>
                            <td
                                class="px-3 py-3.5 text-right font-mono font-medium"
                            >
                                {{ line.qty_ordered }}
                            </td>
                            <td
                                class="px-3 py-3.5 text-right font-mono text-emerald-600 dark:text-emerald-400"
                            >
                                {{ line.qty_received }}
                            </td>
                            <td class="px-3 py-3.5 text-right font-mono">
                                <Badge
                                    :variant="
                                        line.qty_remaining === 0
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ line.qty_remaining }}
                                </Badge>
                            </td>
                            <td
                                class="px-3 py-3.5 text-right font-mono text-xs"
                            >
                                {{ formatMoney(line.unit_price) }}
                            </td>
                            <td
                                class="px-3 py-3.5 text-right font-mono font-semibold"
                            >
                                {{ formatMoney(line.subtotal) }}
                            </td>
                            <td class="py-3.5 pl-3 text-right">
                                <div
                                    class="ml-auto flex w-28 flex-col items-end gap-1"
                                >
                                    <span class="font-mono text-xs font-medium"
                                        >{{ line.progress_pct }}%</span
                                    >
                                    <div
                                        class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary"
                                            :style="{
                                                width: `${Math.min(100, line.progress_pct)}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="mt-4 space-y-3 md:hidden">
                <div
                    v-for="line in purchaseOrder.lines"
                    :key="line.id"
                    class="rounded-xl border border-border/70 bg-background p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium text-foreground">
                                {{
                                    line.product?.name ??
                                    `Product #${line.product_id}`
                                }}
                            </div>
                            <div
                                class="mt-0.5 font-mono text-xs text-muted-foreground"
                            >
                                {{ line.product?.sku ?? 'N/A' }}
                            </div>
                            <div
                                v-if="
                                    line.product?.variant || line.product?.unit
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{ line.product?.variant ?? ''
                                }}{{
                                    line.product?.variant && line.product?.unit
                                        ? ' · '
                                        : ''
                                }}{{
                                    line.product?.unit
                                        ? `per ${line.product.unit}`
                                        : ''
                                }}
                            </div>
                        </div>
                        <Badge variant="outline" class="capitalize">
                            {{ line.product?.type ?? 'consumable' }}
                        </Badge>
                    </div>

                    <div
                        class="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-muted/30 p-2.5 text-center text-xs"
                    >
                        <div>
                            <div class="text-muted-foreground">Ordered</div>
                            <div class="font-mono font-semibold">
                                {{ line.qty_ordered }}
                            </div>
                        </div>
                        <div>
                            <div class="text-muted-foreground">Received</div>
                            <div
                                class="font-mono font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ line.qty_received }}
                            </div>
                        </div>
                        <div>
                            <div class="text-muted-foreground">Remaining</div>
                            <div class="font-mono font-semibold">
                                {{ line.qty_remaining }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-2.5 flex items-center justify-between text-xs text-muted-foreground"
                    >
                        <span>{{ formatMoney(line.unit_price) }} / unit</span>
                        <span class="font-mono font-semibold text-foreground"
                            >Subtotal: {{ formatMoney(line.subtotal) }}</span
                        >
                    </div>
                </div>
            </div>
        </section>

        <!-- Interactive Receiving Workspace on PO Details Page -->
        <section
            v-if="
                can.receive &&
                ['sent', 'partial'].includes(purchaseOrder.status)
            "
            id="po-receive-section"
            class="rounded-2xl border border-primary/25 bg-card p-5 shadow-xs sm:p-6"
        >
            <form class="grid gap-5" @submit.prevent="submitReceipt">
                <div
                    class="flex flex-col gap-3 border-b border-border/60 pb-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <PackageCheck class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold">
                                Receive delivered items
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Scan product QR codes, search by SKU/name, or
                                enter partial/full quantities delivered by the
                                supplier.
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <QrScannerDialog
                            button-label="Scan Product QR / SKU"
                            button-size="sm"
                            title="Scan delivered product"
                            description="Scan a product label to locate the matching PO line and increment its received quantity."
                            :continuous="true"
                            :close-on-scan="false"
                            @scanned="handleProductScanOrQuickMatch"
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            @click="fillAllRemainingQuantities"
                        >
                            <CheckCheck class="mr-1.5 size-4" />
                            Receive All Remaining
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="clearAllReceivingQuantities"
                        >
                            <RotateCcw class="mr-1.5 size-4" />
                            Clear
                        </Button>
                    </div>
                </div>

                <!-- Search / Quick SKU Match & Shared Delivery Reference Bar -->
                <div
                    class="grid gap-4 rounded-xl border border-border/60 bg-muted/20 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
                >
                    <div class="grid gap-1.5">
                        <Label
                            for="po-receive-search"
                            class="text-xs font-semibold"
                        >
                            Filter lines or type SKU + Enter to increment
                        </Label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="po-receive-search"
                                    v-model="receiveSearchQuery"
                                    type="search"
                                    placeholder="Search product name or scan/type SKU…"
                                    class="bg-background pl-9"
                                    @keydown.enter.prevent="
                                        handleProductScanOrQuickMatch(
                                            receiveSearchQuery,
                                        )
                                    "
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    handleProductScanOrQuickMatch(
                                        receiveSearchQuery,
                                    )
                                "
                            >
                                Match +1
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="shared-po-reference"
                            class="text-xs font-semibold"
                        >
                            Default delivery reference &amp; date (applied to
                            lines)
                        </Label>
                        <div
                            class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_150px_auto]"
                        >
                            <Input
                                id="shared-po-reference"
                                v-model="sharedReferenceNo"
                                placeholder="e.g. DR-2026-0041"
                                class="bg-background"
                            />
                            <Input
                                id="shared-po-date"
                                v-model="sharedReceivedAt"
                                type="date"
                                aria-label="Default received date"
                                class="bg-background"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                @click="applySharedMetadataToAll"
                            >
                                Apply to all
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Feedback Alert -->
                <div
                    v-if="receiveFeedback"
                    role="status"
                    aria-live="polite"
                    class="flex items-center gap-2 rounded-lg border px-3.5 py-2 text-xs font-medium"
                    :class="{
                        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300':
                            receiveFeedback.type === 'success',
                        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300':
                            receiveFeedback.type === 'warning',
                        'border-primary/30 bg-primary/10 text-primary':
                            receiveFeedback.type === 'info',
                    }"
                >
                    <AlertCircle
                        v-if="receiveFeedback.type === 'warning'"
                        class="size-4 shrink-0"
                    />
                    <CheckCircle2 v-else class="size-4 shrink-0" />
                    <span>{{ receiveFeedback.text }}</span>
                </div>

                <InputError :message="receiveErrors.lines" />

                <!-- Per-Line Receiving Cards -->
                <div class="space-y-3">
                    <div
                        v-for="index in filteredReceiveIndices"
                        :key="purchaseOrder.lines[index].id"
                        class="rounded-xl border p-4 transition"
                        :class="
                            purchaseOrder.lines[index].qty_remaining === 0
                                ? 'border-border/50 bg-muted/20 opacity-70'
                                : Number(
                                        receiveForm.lines[index].qty_received ||
                                            0,
                                    ) > 0 ||
                                    parseTagCodes(
                                        receiveForm.lines[index].tag_codes_text,
                                    ).length > 0
                                  ? 'border-primary/40 bg-primary/[0.02] shadow-2xs'
                                  : 'border-border/70 bg-background'
                        "
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{
                                        purchaseOrder.lines[index].product
                                            ?.name ??
                                        `Product #${purchaseOrder.lines[index].product_id}`
                                    }}</span>
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-xs"
                                    >
                                        {{
                                            purchaseOrder.lines[index].product
                                                ?.sku ?? 'N/A'
                                        }}
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        class="text-[11px] capitalize"
                                    >
                                        {{
                                            receiveForm.lines[index]
                                                .product_type
                                        }}
                                    </Badge>
                                </div>
                            </div>

                            <div
                                class="flex flex-wrap items-center gap-2 text-xs"
                            >
                                <span class="text-muted-foreground">
                                    Ordered:
                                    <strong class="text-foreground">{{
                                        purchaseOrder.lines[index].qty_ordered
                                    }}</strong>
                                </span>
                                <span class="text-muted-foreground">•</span>
                                <span class="text-muted-foreground">
                                    Prev. received:
                                    <strong
                                        class="text-emerald-600 dark:text-emerald-400"
                                        >{{
                                            purchaseOrder.lines[index]
                                                .qty_received
                                        }}</strong
                                    >
                                </span>
                                <span class="text-muted-foreground">•</span>
                                <Badge
                                    :variant="
                                        purchaseOrder.lines[index]
                                            .qty_remaining === 0
                                            ? 'default'
                                            : 'outline'
                                    "
                                >
                                    Remaining:
                                    {{
                                        purchaseOrder.lines[index].qty_remaining
                                    }}
                                </Badge>
                            </div>
                        </div>

                        <div
                            v-if="
                                purchaseOrder.lines[index].qty_remaining === 0
                            "
                            class="mt-2 flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-4" />
                            <span>
                                This line item has already been completely
                                received.
                            </span>
                        </div>

                        <template v-else>
                            <div class="mt-3.5 grid gap-3 md:grid-cols-4">
                                <!-- Consumable Quantity Input + Steppers -->
                                <div
                                    v-if="
                                        receiveForm.lines[index]
                                            .product_type !== 'asset'
                                    "
                                    class="grid content-start gap-1.5"
                                >
                                    <div
                                        class="flex items-center justify-between gap-1"
                                    >
                                        <Label
                                            :for="`receive-qty-${index}`"
                                            class="text-xs font-medium"
                                        >
                                            Quantity received
                                        </Label>
                                        <button
                                            type="button"
                                            class="text-[11px] font-semibold text-primary hover:underline"
                                            @click="fillLineRemaining(index)"
                                        >
                                            Max ({{
                                                purchaseOrder.lines[index]
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
                                            aria-label="Decrease received quantity"
                                            @click="adjustReceiveQty(index, -1)"
                                        >
                                            <Minus class="size-3.5" />
                                        </Button>
                                        <Input
                                            :id="`receive-qty-${index}`"
                                            v-model="
                                                receiveForm.lines[index]
                                                    .qty_received
                                            "
                                            type="number"
                                            min="0"
                                            :max="
                                                purchaseOrder.lines[index]
                                                    .qty_remaining
                                            "
                                            placeholder="0"
                                            class="px-2 text-center font-semibold"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="size-9 shrink-0"
                                            aria-label="Increase received quantity"
                                            @click="adjustReceiveQty(index, 1)"
                                        >
                                            <Plus class="size-3.5" />
                                        </Button>
                                    </div>
                                    <p
                                        v-if="
                                            Number(
                                                receiveForm.lines[index]
                                                    .qty_received || 0,
                                            ) >
                                            purchaseOrder.lines[index]
                                                .qty_remaining
                                        "
                                        class="text-[11px] font-medium text-destructive"
                                    >
                                        Exceeds remaining quantity ({{
                                            purchaseOrder.lines[index]
                                                .qty_remaining
                                        }}).
                                    </p>
                                    <InputError
                                        :message="
                                            receiveErrors[
                                                `lines.${index}.qty_received`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid content-start gap-1.5">
                                    <Label
                                        :for="`receive-ref-${index}`"
                                        class="text-xs font-medium"
                                    >
                                        Reference no.
                                    </Label>
                                    <Input
                                        :id="`receive-ref-${index}`"
                                        v-model="
                                            receiveForm.lines[index]
                                                .reference_no
                                        "
                                        :placeholder="
                                            sharedReferenceNo ||
                                            'DR / invoice no.'
                                        "
                                    />
                                    <InputError
                                        :message="
                                            receiveErrors[
                                                `lines.${index}.reference_no`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid content-start gap-1.5">
                                    <Label
                                        :for="`receive-date-${index}`"
                                        class="text-xs font-medium"
                                    >
                                        Received date
                                    </Label>
                                    <Input
                                        :id="`receive-date-${index}`"
                                        v-model="
                                            receiveForm.lines[index].received_at
                                        "
                                        type="date"
                                    />
                                    <InputError
                                        :message="
                                            receiveErrors[
                                                `lines.${index}.received_at`
                                            ]
                                        "
                                    />
                                </div>

                                <div
                                    v-if="
                                        receiveForm.lines[index]
                                            .product_type === 'consumable'
                                    "
                                    class="grid content-start gap-1.5"
                                >
                                    <Label
                                        :for="`receive-expiry-${index}`"
                                        class="text-xs font-medium"
                                    >
                                        Expiry date (optional)
                                    </Label>
                                    <Input
                                        :id="`receive-expiry-${index}`"
                                        v-model="
                                            receiveForm.lines[index].expires_at
                                        "
                                        type="date"
                                    />
                                    <InputError
                                        :message="
                                            receiveErrors[
                                                `lines.${index}.expires_at`
                                            ]
                                        "
                                    />
                                </div>
                            </div>

                            <!-- Asset Tag Codes Entry + Scanner -->
                            <div
                                v-if="
                                    receiveForm.lines[index].product_type ===
                                    'asset'
                                "
                                class="mt-3 grid gap-2 rounded-lg border border-border/60 bg-muted/20 p-3"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div>
                                        <Label
                                            :for="`receive-tags-${index}`"
                                            class="text-xs font-semibold"
                                        >
                                            Asset tag codes (one per line or
                                            comma-separated)
                                        </Label>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                parseTagCodes(
                                                    receiveForm.lines[index]
                                                        .tag_codes_text,
                                                ).length
                                            }}
                                            of
                                            {{
                                                purchaseOrder.lines[index]
                                                    .qty_remaining
                                            }}
                                            remaining asset tag(s) entered
                                        </p>
                                    </div>
                                    <QrScannerDialog
                                        button-label="Scan Asset Tags"
                                        title="Scan asset tag codes"
                                        description="Scan each serialized asset tag to append it to this purchase order line."
                                        :continuous="true"
                                        :close-on-scan="false"
                                        @scanned="
                                            appendLineAssetTag(index, $event)
                                        "
                                    />
                                </div>
                                <textarea
                                    :id="`receive-tags-${index}`"
                                    v-model="
                                        receiveForm.lines[index].tag_codes_text
                                    "
                                    rows="3"
                                    placeholder="AST-0001&#10;AST-0002"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                />
                                <InputError
                                    :message="
                                        receiveErrors[
                                            `lines.${index}.tag_codes`
                                        ]
                                    "
                                />
                            </div>

                            <div class="mt-3 grid gap-1.5">
                                <Label
                                    :for="`receive-notes-${index}`"
                                    class="text-xs font-medium"
                                >
                                    Line notes (optional)
                                </Label>
                                <Input
                                    :id="`receive-notes-${index}`"
                                    v-model="receiveForm.lines[index].notes"
                                    placeholder="Inspection remarks, lot batch code, or condition notes"
                                />
                                <InputError
                                    :message="
                                        receiveErrors[`lines.${index}.notes`]
                                    "
                                />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Submission Footer -->
                <div
                    class="flex flex-col gap-3 rounded-xl border border-border/70 bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="text-xs text-muted-foreground">
                        Ready to record:
                        <strong class="text-foreground"
                            >{{
                                pendingReceiptSummary.activeLines
                            }}
                            line(s)</strong
                        >
                        totalling
                        <strong class="text-primary"
                            >{{
                                pendingReceiptSummary.totalReceivingNow
                            }}
                            unit(s)</strong
                        >
                    </div>

                    <Button
                        type="submit"
                        :disabled="receiveForm.processing"
                        class="w-full sm:w-auto"
                    >
                        <PackageCheck class="mr-1.5 size-4" />
                        {{
                            receiveForm.processing
                                ? 'Recording receipt…'
                                : 'Record receipt'
                        }}
                    </Button>
                </div>
            </form>
        </section>

        <!-- Cancel Purchase Order Section -->
        <Form
            v-if="
                can.cancel &&
                purchaseOrder.status !== 'received' &&
                purchaseOrder.status !== 'cancelled'
            "
            v-bind="PurchaseOrderController.cancel.form(purchaseOrder.id)"
            v-slot="{ errors, processing }"
            class="rounded-2xl border border-destructive/30 bg-destructive/5 p-5"
        >
            <div class="flex items-center gap-2 text-destructive">
                <XCircle class="size-4" />
                <h2 class="text-sm font-semibold">Cancel purchase order</h2>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                Provide an audit reason before cancelling this purchase order.
                Once cancelled, no further items can be received.
            </p>

            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex-1">
                    <Label for="cancel_reason" class="sr-only">Reason</Label>
                    <Input
                        id="cancel_reason"
                        name="reason"
                        required
                        placeholder="State why this purchase order is being cancelled…"
                        class="bg-background"
                    />
                    <InputError :message="errors.reason" class="mt-1" />
                </div>
                <Button
                    type="submit"
                    variant="destructive"
                    :disabled="processing"
                    class="shrink-0"
                >
                    Cancel purchase order
                </Button>
            </div>
        </Form>
    </div>
</template>
