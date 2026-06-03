<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ReceivingController from '@/actions/App/Http/Controllers/Inventory/ReceivingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Receiving', href: ReceivingController.index() }],
    },
});

const mode = ref<'single' | 'batch'>('single');

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
    lines: [{ sku: '', qty: '', reference_no: '', received_at: '', expires_at: '', tag_codes_text: '', notes: '' }] as BatchLine[],
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
    batchForm.lines.push({ sku: '', qty: '', reference_no: '', received_at: '', expires_at: '', tag_codes_text: '', notes: '' });
}

function removeBatchLine(index: number) {
    if (batchForm.lines.length <= 1) {
        return;
    }
    batchForm.lines.splice(index, 1);
}

function lineTagCodes(text: string): string[] {
    return text.split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
}

function submitBatch() {
    const lines = batchForm.lines.map((line) => ({
        sku: line.sku,
        qty: line.qty === '' ? null : Number(line.qty),
        reference_no: line.reference_no || null,
        received_at: line.received_at || null,
        expires_at: line.expires_at || null,
        tag_codes: lineTagCodes(line.tag_codes_text).length ? lineTagCodes(line.tag_codes_text) : null,
        notes: line.notes || null,
    }));

    router.post(
        ReceivingController.storeBatch().url,
        { lines },
        {
            onStart: () => batchForm.clearErrors(),
            onError: (errors) => batchForm.setError(errors),
            preserveScroll: true,
        },
    );
}

function appendTagCode(value: string): void {
    const nextValue = value.trim();

    if (!nextValue) {
        return;
    }

    const existing = new Set(tagCodes.value);
    existing.add(nextValue);
    form.tag_codes_text = Array.from(existing).join('\n');
}
</script>

<template>
    <Head title="Receiving" />

    <div class="flex flex-col gap-6 p-4 sm:p-6" data-testid="receiving-page">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                title="Receiving"
                description="Log inbound deliveries for consumables and tagged assets."
            />

            <div class="flex gap-1 rounded-lg border border-border/60 bg-card p-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 rounded-md text-xs"
                    :class="mode === 'single' ? 'bg-primary/10 text-primary font-medium' : ''"
                    @click="mode = 'single'"
                >
                    Single item
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 rounded-md text-xs"
                    :class="mode === 'batch' ? 'bg-primary/10 text-primary font-medium' : ''"
                    @click="mode = 'batch'"
                >
                    Batch
                </Button>
            </div>
        </div>

        <form v-if="mode === 'single'" class="grid max-w-3xl gap-5" @submit.prevent="submitSingle">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary/60" />
                    Product identification
                </div>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <Label for="sku" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">SKU</Label>
                            <QrScannerDialog
                                button-label="Scan SKU"
                                title="Scan product QR"
                                description="Point the camera at a product label QR code to fill the SKU field."
                                @scanned="form.sku = $event"
                            />
                        </div>
                        <Input id="sku" v-model="form.sku" data-testid="receiving-sku-input" required placeholder="Scan or type SKU" class="rounded-lg" />
                        <InputError :message="form.errors.sku" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500/60" />
                    Quantity & reference
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="qty" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Quantity (consumables)</Label>
                        <Input
                            id="qty"
                            v-model="form.qty"
                            data-testid="receiving-qty-input"
                            type="number"
                            min="1"
                            placeholder="e.g. 12"
                            class="rounded-lg"
                        />
                        <InputError :message="form.errors.qty" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="reference_no" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Reference no.</Label>
                        <Input id="reference_no" v-model="form.reference_no" data-testid="receiving-reference-input" placeholder="e.g. DR-000123" class="rounded-lg" />
                        <InputError :message="form.errors.reference_no" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="received_at" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Received at</Label>
                        <Input id="received_at" v-model="form.received_at" type="datetime-local" class="rounded-lg" />
                        <InputError :message="form.errors.received_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="expires_at" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Expires at (optional)</Label>
                        <Input id="expires_at" v-model="form.expires_at" type="date" class="rounded-lg" />
                        <InputError :message="form.errors.expires_at" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-500/60" />
                    Asset tags
                </div>
                <div class="grid gap-2">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Label for="tag_codes_text" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Asset tag codes (one per line)</Label>
                        <QrScannerDialog
                            button-label="Scan asset tag"
                            title="Scan asset tag QR"
                            description="Each successful scan appends one asset tag code to the list below."
                            @scanned="appendTagCode"
                        />
                    </div>
                    <textarea
                        id="tag_codes_text"
                        v-model="form.tag_codes_text"
                        data-testid="receiving-tag-codes-input"
                        class="min-h-28 rounded-lg border border-input bg-background px-3 py-2 text-sm transition-colors focus:border-ring focus:outline-none"
                        placeholder="AST-00000001&#10;AST-00000002"
                    />
                    <div class="text-xs text-muted-foreground">
                        Asset scans are appended automatically. You can still paste or type multiple tag codes here.
                    </div>
                    <InputError :message="form.errors.tag_codes" />
                </div>
            </div>

            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500/60" />
                    Notes
                </div>
                <div class="grid gap-2">
                    <Label for="notes" class="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">Additional notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        class="min-h-20 rounded-lg border border-input bg-background px-3 py-2 text-sm transition-colors focus:border-ring focus:outline-none"
                        placeholder="Optional notes…"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button type="submit" :disabled="form.processing" data-test="receive-stock-button" data-testid="receive-stock-button" class="rounded-lg shadow-sm">
                    <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary-foreground/60" />Receive stock
                </Button>
            </div>
        </form>

        <form v-else class="flex flex-col gap-5" @submit.prevent="submitBatch">
            <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">
                            <th>SKU</th>
                            <th>Qty</th>
                            <th>Ref no.</th>
                            <th>Received</th>
                            <th>Expires</th>
                            <th>Tags</th>
                            <th>Notes</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="(line, i) in batchForm.lines"
                            :key="i"
                            class="[&>td]:px-4 [&>td]:py-2"
                        >
                            <td><Input v-model="line.sku" placeholder="SKU" class="h-8 rounded-lg text-xs" /></td>
                            <td><Input v-model="line.qty" type="number" min="1" placeholder="Qty" class="h-8 rounded-lg text-xs" /></td>
                            <td><Input v-model="line.reference_no" placeholder="Ref" class="h-8 rounded-lg text-xs" /></td>
                            <td><Input v-model="line.received_at" type="datetime-local" class="h-8 rounded-lg text-xs" /></td>
                            <td><Input v-model="line.expires_at" type="date" class="h-8 rounded-lg text-xs" /></td>
                            <td>
                                <textarea
                                    v-model="line.tag_codes_text"
                                    class="min-h-16 w-32 rounded-lg border border-input bg-background px-2 py-1 text-xs transition-colors focus:border-ring focus:outline-none"
                                    placeholder="One per line"
                                />
                            </td>
                            <td>
                                <Input v-model="line.notes" placeholder="Notes" class="h-8 rounded-lg text-xs" />
                            </td>
                            <td>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 rounded-lg text-xs text-muted-foreground hover:text-rose-600"
                                    @click="removeBatchLine(i)"
                                >
                                    Remove
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-2">
                <Button type="button" variant="outline" size="sm" class="rounded-lg" @click="addBatchLine">
                    + Add line
                </Button>
                <Button type="submit" :disabled="batchForm.processing" class="rounded-lg shadow-sm">
                    <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary-foreground/60" />Receive batch
                </Button>
            </div>

            <div v-if="batchForm.errors.lines" class="text-sm text-destructive">
                <InputError :message="batchForm.errors.lines" />
            </div>
        </form>
    </div>
</template>

