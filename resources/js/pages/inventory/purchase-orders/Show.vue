<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPhilippinePeso } from '@/lib/utils';
import { show as productsShow } from '@/routes/inventory/products';
import {
    index as purchaseOrdersIndex,
    show as purchaseOrdersShow,
} from '@/routes/inventory/purchase-orders';

type PurchaseOrderLine = {
    id: number;
    product_id: number;
    product: {
        id: number;
        sku: string;
        name: string;
        type: 'asset' | 'consumable';
    } | null;
    qty_ordered: number;
    qty_received: number;
    qty_remaining: number;
    unit_price: number;
    subtotal: number;
    progress_pct: number;
};

type PurchaseOrder = {
    id: number;
    po_number: string;
    status: string;
    status_label: string;
    subtotal: number;
    tax: number;
    total_amount: number;
    expected_delivery_at: string | null;
    sent_at: string | null;
    received_at: string | null;
    notes: string | null;
    progress_pct: number;
    supplier: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    requester: {
        name: string;
    } | null;
    approver: {
        name: string;
    } | null;
    lines: PurchaseOrderLine[];
};

const props = defineProps<{
    purchaseOrder: PurchaseOrder;
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

const cancelForm = useForm({
    reason: '',
});

const receiveForm = useForm({
    lines: props.purchaseOrder.lines.map((line) => ({
        purchase_order_line_id: line.id,
        qty_received: line.product?.type === 'consumable' ? '' : '',
        reference_no: props.purchaseOrder.po_number,
        received_at: '',
        expires_at: '',
        tag_codes_text: '',
        notes: '',
    })),
});

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function sendPurchaseOrder(): void {
    router.put(PurchaseOrderController.send(props.purchaseOrder.id).url, {});
}

function cancelPurchaseOrder(): void {
    router.put(
        PurchaseOrderController.cancel(props.purchaseOrder.id).url,
        {
            reason: cancelForm.reason,
        },
        {
            onStart: () => cancelForm.clearErrors(),
            onError: (errors) => cancelForm.setError(errors),
        },
    );
}

function tagCodes(value: string): string[] {
    return value
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function submitReceipts(): void {
    router.post(
        PurchaseOrderController.receive(props.purchaseOrder.id).url,
        {
            lines: receiveForm.lines
                .map((line) => ({
                    purchase_order_line_id: line.purchase_order_line_id,
                    qty_received:
                        line.qty_received === ''
                            ? null
                            : Number(line.qty_received),
                    reference_no: line.reference_no || null,
                    received_at: line.received_at || null,
                    expires_at: line.expires_at || null,
                    tag_codes: tagCodes(line.tag_codes_text).length
                        ? tagCodes(line.tag_codes_text)
                        : null,
                    notes: line.notes || null,
                }))
                .filter(
                    (line) =>
                        line.qty_received !== null ||
                        (line.tag_codes !== null && line.tag_codes.length > 0),
                ),
        },
        {
            onStart: () => receiveForm.clearErrors(),
            onError: (errors) => receiveForm.setError(errors),
        },
    );
}
</script>

<template>
    <Head :title="purchaseOrder.po_number" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                :title="purchaseOrder.po_number"
                :description="purchaseOrder.supplier?.name ?? 'Purchase order'"
            />

            <div class="flex flex-wrap gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="purchaseOrdersIndex()">Back</Link>
                </Button>
                <Button
                    v-if="can.send && purchaseOrder.status === 'draft'"
                    @click="sendPurchaseOrder"
                >
                    Send to supplier
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Status</div>
                <div class="mt-1 font-medium">{{ purchaseOrder.status_label }}</div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Expected delivery</div>
                <div class="mt-1 font-medium">
                    {{ formatDateTime(purchaseOrder.expected_delivery_at) }}
                </div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Sent at</div>
                <div class="mt-1 font-medium">
                    {{ formatDateTime(purchaseOrder.sent_at) }}
                </div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Received at</div>
                <div class="mt-1 font-medium">
                    {{ formatDateTime(purchaseOrder.received_at) }}
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 text-sm font-semibold tracking-tight">Line items</div>

                <div class="grid gap-4">
                    <div
                        v-for="line in purchaseOrder.lines"
                        :key="line.id"
                        class="rounded-lg border border-border/50 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">
                                    {{ line.product?.name ?? 'Unknown product' }}
                                </div>
                                <div class="font-mono text-xs text-muted-foreground">
                                    {{ line.product?.sku ?? '—' }}
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" as-child>
                                <Link
                                    v-if="line.product"
                                    :href="productsShow(line.product.id)"
                                >
                                    View product
                                </Link>
                            </Button>
                        </div>

                        <div class="mt-3 grid gap-2 text-sm text-muted-foreground md:grid-cols-2">
                            <div>Ordered: {{ line.qty_ordered }}</div>
                            <div>Received: {{ line.qty_received }}</div>
                            <div>Remaining: {{ line.qty_remaining }}</div>
                            <div>Unit price: {{ formatPhilippinePeso(line.unit_price) }}</div>
                        </div>

                        <div class="mt-3 h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-primary"
                                :style="{ width: `${line.progress_pct}%` }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6">
                <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                    <div class="mb-4 text-sm font-semibold tracking-tight">
                        Order summary
                    </div>
                    <div class="grid gap-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-mono">{{ formatPhilippinePeso(purchaseOrder.subtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Tax / adjustments</span>
                            <span class="font-mono">{{ formatPhilippinePeso(purchaseOrder.tax) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-base font-semibold">
                            <span>Total</span>
                            <span class="font-mono">{{ formatPhilippinePeso(purchaseOrder.total_amount) }}</span>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg border border-border/50 p-4 text-sm text-muted-foreground">
                        <div>Requester: {{ purchaseOrder.requester?.name ?? '—' }}</div>
                        <div>Approver: {{ purchaseOrder.approver?.name ?? '—' }}</div>
                        <div>Supplier email: {{ purchaseOrder.supplier?.email ?? '—' }}</div>
                    </div>
                </div>

                <div
                    v-if="can.cancel && purchaseOrder.status !== 'cancelled' && purchaseOrder.status !== 'received'"
                    class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
                >
                    <div class="mb-3 text-sm font-semibold tracking-tight">
                        Cancel purchase order
                    </div>
                    <div class="grid gap-2">
                        <Label for="cancel-reason">Reason</Label>
                        <textarea
                            id="cancel-reason"
                            v-model="cancelForm.reason"
                            rows="3"
                            class="min-h-20 rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <Button
                        class="mt-4"
                        variant="destructive"
                        :disabled="!cancelForm.reason"
                        @click="cancelPurchaseOrder"
                    >
                        Cancel order
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="can.receive && ['sent', 'partial'].includes(purchaseOrder.status)"
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="mb-4 text-sm font-semibold tracking-tight">
                Receive against purchase order
            </div>

            <div class="grid gap-5">
                <div
                    v-for="(line, index) in purchaseOrder.lines"
                    :key="line.id"
                    class="grid gap-4 rounded-lg border border-border/50 p-4"
                >
                    <div>
                        <div class="font-medium">
                            {{ line.product?.name ?? 'Unknown product' }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Remaining: {{ line.qty_remaining }}
                        </div>
                    </div>

                    <div
                        v-if="line.product?.type === 'consumable'"
                        class="grid gap-4 md:grid-cols-4"
                    >
                        <div class="grid gap-2">
                            <Label :for="`receive-qty-${index}`">Qty received</Label>
                            <Input
                                :id="`receive-qty-${index}`"
                                v-model="receiveForm.lines[index].qty_received"
                                type="number"
                                min="1"
                            />
                            <InputError
                                :message="
                                    receiveForm.errors[`lines.${index}.qty_received`]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`receive-ref-${index}`">Reference</Label>
                            <Input
                                :id="`receive-ref-${index}`"
                                v-model="receiveForm.lines[index].reference_no"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`receive-at-${index}`">Received at</Label>
                            <Input
                                :id="`receive-at-${index}`"
                                v-model="receiveForm.lines[index].received_at"
                                type="datetime-local"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`receive-exp-${index}`">Expires at</Label>
                            <Input
                                :id="`receive-exp-${index}`"
                                v-model="receiveForm.lines[index].expires_at"
                                type="date"
                            />
                        </div>
                    </div>

                    <div v-else class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`receive-tags-${index}`">
                                Asset tag codes
                            </Label>
                            <textarea
                                :id="`receive-tags-${index}`"
                                v-model="receiveForm.lines[index].tag_codes_text"
                                rows="4"
                                class="min-h-24 rounded-lg border border-input bg-background px-3 py-2 text-sm"
                                placeholder="One tag per line"
                            />
                            <InputError
                                :message="
                                    receiveForm.errors[`lines.${index}.tag_codes`]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`receive-at-asset-${index}`">Received at</Label>
                            <Input
                                :id="`receive-at-asset-${index}`"
                                v-model="receiveForm.lines[index].received_at"
                                type="datetime-local"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`receive-notes-${index}`">Notes</Label>
                        <Input
                            :id="`receive-notes-${index}`"
                            v-model="receiveForm.lines[index].notes"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button :disabled="receiveForm.processing" @click="submitReceipts">
                        Submit receipt
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="purchaseOrder.notes"
            class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
        >
            <div class="mb-3 text-sm font-semibold tracking-tight">Notes</div>
            <div class="text-sm text-muted-foreground whitespace-pre-line">
                {{ purchaseOrder.notes }}
            </div>
        </div>
    </div>
</template>
