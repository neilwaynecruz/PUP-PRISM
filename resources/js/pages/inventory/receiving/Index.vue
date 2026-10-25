<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Boxes,
    Calendar,
    Check,
    Clock,
    FileText,
    Hash,
    Layers,
    Package,
    Plus,
    ScanLine,
    Tag,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ReceivingController from '@/actions/App/Http/Controllers/Inventory/ReceivingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    name: 'ReceivingIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Receiving', href: ReceivingController.index() },
        ],
    },
});

const mode = ref<'single' | 'batch'>('single');
const tagScanFeedback = ref('');
const batchTagFeedback = ref<Record<number, string>>({});
const isSubmittingBatch = ref(false);

const form = useForm({
    sku: '',
    qty: '' as string | number,
    reference_no: '',
    received_at: '',
    expires_at: '',
    tag_codes: [] as string[],
    tag_codes_text: '',
    notes: '',
});

type BatchLine = {
    sku: string;
    qty: string | number;
    reference_no: string;
    received_at: string;
    expires_at: string;
    tag_codes_text: string;
    notes: string;
};

const batchForm = useForm({
    lines: [
        {
            sku: '',
            qty: '',
            reference_no: '',
            received_at: '',
            expires_at: '',
            tag_codes_text: '',
            notes: '',
        },
    ] as BatchLine[],
});

const tagCodes = computed(() =>
    form.tag_codes_text
        .split(/\r?\n/)
        .map((s) => s.trim())
        .filter(Boolean),
);

function submitSingle() {
    router.post(
        ReceivingController.store().url,
        {
            sku: form.sku,
            qty: form.qty === '' ? null : Number(form.qty),
            reference_no: form.reference_no || null,
            received_at: form.received_at || null,
            expires_at: form.expires_at || null,
            tag_codes: tagCodes.value.length ? tagCodes.value : null,
            notes: form.notes || null,
        },
        {
            onStart: () => form.clearErrors(),
            onError: (errors) => form.setError(errors),
            preserveScroll: true,
        },
    );
}

function addBatchLine() {
    batchForm.lines.push({
        sku: '',
        qty: '',
        reference_no: '',
        received_at: '',
        expires_at: '',
        tag_codes_text: '',
        notes: '',
    });
}

function removeBatchLine(index: number) {
    if (batchForm.lines.length <= 1) {
        return;
    }

    batchForm.lines.splice(index, 1);
}

function lineTagCodes(text: string): string[] {
    return text
        .split(/\r?\n/)
        .map((s) => s.trim())
        .filter(Boolean);
}

function mergeTagCodeText(
    currentValue: string,
    nextValue: string,
): { value: string; added: boolean; count: number } {
    const normalized = nextValue.trim();

    if (!normalized) {
        return {
            value: currentValue,
            added: false,
            count: lineTagCodes(currentValue).length,
        };
    }

    const nextLines = lineTagCodes(currentValue);
    const existing = new Set(nextLines);
    const added = !existing.has(normalized);

    if (added) {
        existing.add(normalized);
    }

    return {
        value: Array.from(existing).join('\n'),
        added,
        count: existing.size,
    };
}

function submitBatch() {
    if (isSubmittingBatch.value) {
        return;
    }

    const lines = batchForm.lines.map((line) => ({
        sku: line.sku,
        qty: line.qty === '' ? null : Number(line.qty),
        reference_no: line.reference_no || null,
        received_at: line.received_at || null,
        expires_at: line.expires_at || null,
        tag_codes: lineTagCodes(line.tag_codes_text).length
            ? lineTagCodes(line.tag_codes_text)
            : null,
        notes: line.notes || null,
    }));

    router.post(
        ReceivingController.storeBatch().url,
        { lines },
        {
            onStart: () => {
                isSubmittingBatch.value = true;
                batchForm.clearErrors();
            },
            onError: (errors) => batchForm.setError(errors),
            onFinish: () => {
                isSubmittingBatch.value = false;
            },
            preserveScroll: true,
        },
    );
}

function appendTagCode(value: string): void {
    const merged = mergeTagCodeText(form.tag_codes_text, value);

    form.tag_codes_text = merged.value;
    tagScanFeedback.value = merged.added
        ? `Captured ${value.trim()}. ${merged.count} ${merged.count === 1 ? 'tag code is' : 'tag codes are'} ready for receiving.`
        : `${value.trim()} is already in the list.`;
}

function appendBatchLineTagCode(index: number, value: string): void {
    const line = batchForm.lines[index];

    if (!line) {
        return;
    }

    const merged = mergeTagCodeText(line.tag_codes_text, value);

    line.tag_codes_text = merged.value;
    batchTagFeedback.value[index] = merged.added
        ? `Captured ${value.trim()}. ${merged.count} ${merged.count === 1 ? 'tag code' : 'tag codes'} on this line.`
        : `${value.trim()} is already on this line.`;
}
</script>

<template>
    <Head title="Receiving" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="receiving-page"
    >
        <!-- Header & Mode Switcher -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <Heading
                    variant="small"
                    title="Receiving"
                    description="Record delivered consumables or register individual tagged assets into warehouse stock."
                />
            </div>

            <div
                role="group"
                aria-label="Receiving mode"
                class="flex items-center gap-1 rounded-xl border border-border/70 bg-muted/40 p-1"
            >
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :aria-pressed="mode === 'single'"
                    :class="
                        mode === 'single'
                            ? 'bg-card text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="mode = 'single'"
                >
                    <Package
                        class="h-3.5 w-3.5"
                        :class="mode === 'single' ? 'text-primary' : ''"
                    />
                    <span>Single item</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :aria-pressed="mode === 'batch'"
                    :class="
                        mode === 'batch'
                            ? 'bg-card text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="mode = 'batch'"
                >
                    <Boxes
                        class="h-3.5 w-3.5"
                        :class="mode === 'batch' ? 'text-primary' : ''"
                    />
                    <span>Batch delivery</span>
                </button>
            </div>
        </div>

        <form
            v-if="mode === 'single'"
            class="grid gap-6 lg:grid-cols-12 lg:items-start"
            @submit.prevent="submitSingle"
        >
            <!-- Left Column: Details -->
            <div class="grid gap-6 lg:col-span-7 xl:col-span-8">
                <!-- Product identification -->
                <div
                    class="rounded-xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div
                        class="mb-4 flex items-center gap-2 text-xs font-bold tracking-tight text-foreground uppercase"
                    >
                        <Package class="h-4 w-4 text-primary" />
                        Product identification
                    </div>
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Label
                                    for="sku"
                                    class="text-xs font-medium text-muted-foreground"
                                    >Product SKU or Barcode</Label
                                >
                                <QrScannerDialog
                                    button-label="Scan SKU"
                                    title="Scan product QR"
                                    description="Point the camera at a product label QR code to fill the SKU field."
                                    @scanned="form.sku = $event"
                                />
                            </div>
                            <div class="relative">
                                <ScanLine
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                                />
                                <Input
                                    id="sku"
                                    v-model="form.sku"
                                    data-testid="receiving-sku-input"
                                    required
                                    placeholder="Scan or enter SKU (e.g., CON-HSOAP-500)"
                                    class="h-10 rounded-lg pl-9 font-mono text-sm"
                                />
                            </div>
                            <InputError :message="form.errors.sku" />
                        </div>
                    </div>
                </div>

                <!-- Quantity & reference -->
                <div
                    class="rounded-xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div
                        class="mb-4 flex items-center gap-2 text-xs font-bold tracking-tight text-foreground uppercase"
                    >
                        <Layers class="h-4 w-4 text-primary" />
                        Quantity & Delivery Reference
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label
                                for="qty"
                                class="text-xs font-medium text-muted-foreground"
                                >Quantity received (consumables)</Label
                            >
                            <div class="relative">
                                <Hash
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                                />
                                <Input
                                    id="qty"
                                    v-model="form.qty"
                                    data-testid="receiving-qty-input"
                                    type="number"
                                    min="1"
                                    inputmode="numeric"
                                    placeholder="Example: 12"
                                    class="h-10 rounded-lg pl-9 text-sm"
                                />
                            </div>
                            <InputError :message="form.errors.qty" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="reference_no"
                                class="text-xs font-medium text-muted-foreground"
                                >Delivery reference / DR No.</Label
                            >
                            <div class="relative">
                                <FileText
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                                />
                                <Input
                                    id="reference_no"
                                    v-model="form.reference_no"
                                    data-testid="receiving-reference-input"
                                    placeholder="Example: DR-202610-001"
                                    class="h-10 rounded-lg pl-9 text-sm"
                                />
                            </div>
                            <InputError :message="form.errors.reference_no" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="received_at"
                                class="text-xs font-medium text-muted-foreground"
                                >Date & time received</Label
                            >
                            <div class="relative">
                                <Calendar
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                                />
                                <Input
                                    id="received_at"
                                    v-model="form.received_at"
                                    type="datetime-local"
                                    class="h-10 rounded-lg pl-9 text-sm"
                                />
                            </div>
                            <InputError :message="form.errors.received_at" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="expires_at"
                                class="text-xs font-medium text-muted-foreground"
                                >Lot expiration date (optional)</Label
                            >
                            <div class="relative">
                                <Clock
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                                />
                                <Input
                                    id="expires_at"
                                    v-model="form.expires_at"
                                    type="date"
                                    class="h-10 rounded-lg pl-9 text-sm"
                                />
                            </div>
                            <InputError :message="form.errors.expires_at" />
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div
                    class="rounded-xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div
                        class="mb-3 flex items-center gap-2 text-xs font-bold tracking-tight text-foreground uppercase"
                    >
                        <FileText class="h-4 w-4 text-primary" />
                        Additional notes / remarks
                    </div>
                    <div class="grid gap-2">
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="min-h-24 rounded-lg border border-input bg-background px-3 py-2 text-sm transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            placeholder="Optional notes or supplier delivery remarks…"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>
            </div>

            <!-- Right Column: Tags & Confirmation -->
            <div class="grid gap-6 lg:col-span-5 xl:col-span-4">
                <!-- Asset tags -->
                <div
                    class="rounded-xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-xs font-bold tracking-tight text-foreground uppercase"
                        >
                            <Tag class="h-4 w-4 text-primary" />
                            Asset tags
                        </div>
                        <span
                            v-if="tagCodes.length > 0"
                            class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                        >
                            {{ tagCodes.length }}
                            {{ tagCodes.length === 1 ? 'tag' : 'tags' }}
                        </span>
                    </div>
                    <div class="grid gap-3">
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <Label
                                for="tag_codes_text"
                                class="text-xs text-muted-foreground"
                                >One tag code per line</Label
                            >
                            <QrScannerDialog
                                button-label="Batch scan tags"
                                title="Scan asset tag QR"
                                description="Keep the camera open to capture multiple asset tags in one receiving session."
                                :continuous="true"
                                trigger-test-id="receiving-tag-scanner-button"
                                @scanned="appendTagCode"
                            />
                        </div>
                        <textarea
                            id="tag_codes_text"
                            v-model="form.tag_codes_text"
                            data-testid="receiving-tag-codes-input"
                            class="min-h-[160px] rounded-lg border border-input bg-background px-3 py-2 font-mono text-xs transition focus-visible:border-primary/50 focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                            placeholder="AST-00000001&#10;AST-00000002"
                        />
                        <div class="text-[0.7rem] text-muted-foreground">
                            Scanned barcodes/QRs are appended automatically. You
                            can also paste or manually type codes.
                        </div>
                        <div
                            v-if="tagScanFeedback"
                            class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-xs text-foreground"
                        >
                            {{ tagScanFeedback }}
                        </div>
                        <InputError :message="form.errors.tag_codes" />
                    </div>
                </div>

                <!-- Confirm Transaction Card -->
                <div
                    class="space-y-4 rounded-xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div>
                        <h4
                            class="text-sm font-bold tracking-tight text-foreground uppercase"
                        >
                            Confirm receipt
                        </h4>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Verify the quantity, delivery reference, and asset
                            tag codes before recording into system stock.
                        </p>
                    </div>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="receive-stock-button"
                        data-testid="receive-stock-button"
                        class="w-full rounded-lg shadow-sm"
                    >
                        <Check aria-hidden="true" class="mr-1.5 h-4 w-4" />{{
                            form.processing
                                ? 'Receiving Items…'
                                : 'Receive Items'
                        }}
                    </Button>
                </div>
            </div>
        </form>

        <form v-else class="flex flex-col gap-5" @submit.prevent="submitBatch">
            <div
                class="grid gap-4 lg:hidden"
                data-testid="receiving-batch-mobile-lines"
            >
                <div
                    v-for="(line, i) in batchForm.lines"
                    :key="`mobile-${i}`"
                    class="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold">
                                Batch line {{ i + 1 }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Capture item details and asset tags for this
                                entry.
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 rounded-lg text-xs text-muted-foreground hover:text-rose-600"
                            @click="removeBatchLine(i)"
                        >
                            Remove
                        </Button>
                    </div>

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label
                                class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >
                                SKU
                            </Label>
                            <Input
                                v-model="line.sku"
                                placeholder="SKU"
                                class="rounded-lg"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label
                                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >
                                    Quantity received
                                </Label>
                                <Input
                                    v-model="line.qty"
                                    type="number"
                                    min="1"
                                    inputmode="numeric"
                                    placeholder="Quantity"
                                    class="rounded-lg"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >
                                    Delivery reference
                                </Label>
                                <Input
                                    v-model="line.reference_no"
                                    placeholder="Ref"
                                    class="rounded-lg"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >
                                    Date received
                                </Label>
                                <Input
                                    v-model="line.received_at"
                                    type="datetime-local"
                                    class="rounded-lg"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >
                                    Expiration date
                                </Label>
                                <Input
                                    v-model="line.expires_at"
                                    type="date"
                                    class="rounded-lg"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Label
                                    class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                                >
                                    Asset tags
                                </Label>
                                <QrScannerDialog
                                    button-label="Batch scan tags"
                                    title="Scan batch line asset tags"
                                    description="Keep scanning to append multiple asset tags to this batch line."
                                    :continuous="true"
                                    @scanned="appendBatchLineTagCode(i, $event)"
                                />
                            </div>
                            <textarea
                                v-model="line.tag_codes_text"
                                class="min-h-24 rounded-lg border border-input bg-background px-3 py-2 text-sm transition-colors focus:border-ring focus:outline-none"
                                placeholder="One tag code per line"
                            />
                            <div
                                v-if="batchTagFeedback[i]"
                                class="rounded-lg border border-primary/15 bg-primary/5 px-3 py-2 text-xs text-muted-foreground"
                            >
                                {{ batchTagFeedback[i] }}
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label
                                class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase"
                            >
                                Notes
                            </Label>
                            <Input
                                v-model="line.notes"
                                placeholder="Notes"
                                class="rounded-lg"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm lg:block"
            >
                <table class="w-full min-w-[1250px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[14%]" />
                        <col class="w-[7%]" />
                        <col class="w-[11%]" />
                        <col class="w-[18%]" />
                        <col class="w-[14%]" />
                        <col class="w-[20%]" />
                        <col class="w-[12%]" />
                        <col class="w-[4%]" />
                    </colgroup>
                    <thead
                        class="border-b border-border/60 bg-muted/50 text-left"
                    >
                        <tr
                            class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3.5"
                        >
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Delivery reference</th>
                            <th>Date received</th>
                            <th>Expiration date</th>
                            <th>Tags</th>
                            <th>Notes</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="(line, i) in batchForm.lines"
                            :key="`desktop-${i}`"
                            class="[&>td]:px-4 [&>td]:py-3.5"
                        >
                            <td>
                                <Input
                                    v-model="line.sku"
                                    placeholder="SKU"
                                    class="h-8 rounded-lg text-xs"
                                />
                            </td>
                            <td>
                                <Input
                                    v-model="line.qty"
                                    type="number"
                                    min="1"
                                    placeholder="Qty"
                                    class="h-8 rounded-lg text-xs"
                                />
                            </td>
                            <td>
                                <Input
                                    v-model="line.reference_no"
                                    placeholder="Ref"
                                    class="h-8 rounded-lg text-xs"
                                />
                            </td>
                            <td>
                                <Input
                                    v-model="line.received_at"
                                    type="datetime-local"
                                    class="h-8 rounded-lg text-xs"
                                />
                            </td>
                            <td>
                                <Input
                                    v-model="line.expires_at"
                                    type="date"
                                    class="h-8 rounded-lg text-xs"
                                />
                            </td>
                            <td class="space-y-2">
                                <textarea
                                    v-model="line.tag_codes_text"
                                    class="min-h-12 w-full rounded-lg border border-input bg-background px-2.5 py-1.5 text-xs transition-colors focus:border-ring focus:outline-none"
                                    placeholder="One per line"
                                />
                                <QrScannerDialog
                                    button-label="Scan tags"
                                    title="Scan batch line asset tags"
                                    description="Keep scanning to append multiple asset tags to this batch line."
                                    :continuous="true"
                                    @scanned="appendBatchLineTagCode(i, $event)"
                                />
                                <div
                                    v-if="batchTagFeedback[i]"
                                    class="max-w-full text-[11px] leading-normal text-muted-foreground"
                                >
                                    {{ batchTagFeedback[i] }}
                                </div>
                            </td>
                            <td>
                                <Input
                                    v-model="line.notes"
                                    placeholder="Notes"
                                    class="h-8 rounded-lg text-xs"
                                />
                            </td>
                            <td class="text-center">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 rounded-lg text-muted-foreground hover:bg-rose-500/10 hover:text-rose-600"
                                    title="Remove line"
                                    @click="removeBatchLine(i)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="sticky bottom-4 z-10 flex flex-col gap-2 rounded-xl border border-border/60 bg-background/95 p-3 shadow-lg backdrop-blur supports-backdrop-filter:bg-background/80 sm:flex-row sm:items-center md:static md:border-0 md:bg-transparent md:p-0 md:shadow-none"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="rounded-lg"
                    @click="addBatchLine"
                >
                    <Plus aria-hidden="true" class="mr-1.5 h-4 w-4" />Add
                    Delivery Line
                </Button>
                <Button
                    type="submit"
                    :disabled="isSubmittingBatch"
                    class="rounded-lg shadow-sm"
                >
                    <Check aria-hidden="true" class="mr-1.5 h-4 w-4" />{{
                        isSubmittingBatch
                            ? 'Receiving Delivery…'
                            : 'Receive Batch Delivery'
                    }}
                </Button>
            </div>

            <div v-if="batchForm.errors.lines" class="text-sm text-destructive">
                <InputError :message="batchForm.errors.lines" />
            </div>
        </form>
    </div>
</template>
