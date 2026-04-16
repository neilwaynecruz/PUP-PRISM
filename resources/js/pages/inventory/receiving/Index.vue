<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const tagCodes = computed(() =>
    form.tag_codes_text
        .split(/\r?\n/)
        .map((s) => s.trim())
        .filter(Boolean),
);

function submit() {
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

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Receiving"
            description="Log inbound deliveries for consumables and tagged assets. Camera scanning is optional, and manual entry always stays available."
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Label for="sku">SKU</Label>
                    <QrScannerDialog
                        button-label="Scan SKU"
                        title="Scan product QR"
                        description="Point the camera at a product label QR code to fill the SKU field."
                        @scanned="form.sku = $event"
                    />
                </div>
                <Input id="sku" v-model="form.sku" required placeholder="Scan or type SKU" />
                <InputError :message="form.errors.sku" />
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="qty">Quantity (consumables)</Label>
                    <Input
                        id="qty"
                        v-model="form.qty"
                        type="number"
                        min="1"
                        placeholder="e.g. 12"
                    />
                    <InputError :message="form.errors.qty" />
                </div>

                <div class="grid gap-2">
                    <Label for="reference_no">Reference no.</Label>
                    <Input id="reference_no" v-model="form.reference_no" placeholder="e.g. DR-000123" />
                    <InputError :message="form.errors.reference_no" />
                </div>
            </div>

            <div class="grid gap-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="received_at">Received at</Label>
                    <Input id="received_at" v-model="form.received_at" type="datetime-local" />
                    <InputError :message="form.errors.received_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="expires_at">Expires at (optional)</Label>
                    <Input id="expires_at" v-model="form.expires_at" type="date" />
                    <InputError :message="form.errors.expires_at" />
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Label for="tag_codes_text">Asset tag codes (one per line)</Label>
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
                    class="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    placeholder="AST-00000001&#10;AST-00000002"
                />
                <div class="text-sm text-muted-foreground">
                    Asset scans are appended automatically. You can still paste or type multiple tag codes here.
                </div>
                <InputError :message="form.errors.tag_codes" />
            </div>

            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    class="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    placeholder="Optional notes…"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button type="submit" :disabled="form.processing">Receive</Button>
            </div>
        </form>
    </div>
</template>

