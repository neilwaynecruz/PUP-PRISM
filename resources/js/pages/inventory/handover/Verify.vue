<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { SignaturePadInstance } from 'vue-signature-pad';
import { VueSignaturePad } from 'vue-signature-pad';
import HandoverController from '@/actions/App/Http/Controllers/Inventory/HandoverController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { index as handoverIndex } from '@/routes/inventory/handover';
import { receipt as handoverReceipt } from '@/routes/inventory/handover';

defineProps<{
    handover: {
        id: number;
        token: string;
        tag_code: string | null;
        asset_name: string | null;
        from_user: { id: number; name: string; email: string } | null;
        to_user: { id: number; name: string; email: string } | null;
        from_position: { title: string; code: string; department: string | null } | null;
        to_position: { title: string; code: string; department: string | null } | null;
        verified_at: string | null;
    };
    email_verified: boolean;
}>();

const signaturePad = ref<SignaturePadInstance | null>(null);
const signaturePng = ref<string>('');
const signatureSizeError = ref<string>('');
const maxSignatureBytes = 300000;

function captureSignature(event?: Event): void {
    const pad = signaturePad.value;

    if (!pad) {
        return;
    }

    const { isEmpty, data } = pad.saveSignature();
    signaturePng.value = isEmpty ? '' : data;
    signatureSizeError.value = '';

    if (signaturePng.value.length > maxSignatureBytes) {
        event?.preventDefault();
        signaturePng.value = '';
        signatureSizeError.value = 'Signature is too large. Please clear it and sign again with fewer strokes.';
    }
}

function clearSignature(): void {
    const pad = signaturePad.value;
    pad?.clearSignature();
    signaturePng.value = '';
    signatureSizeError.value = '';
}
</script>

<template>
    <Head title="Verify handover" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Verify handover"
            description="Confirm this transfer for internal accountability. This acknowledgement does not replace external legal contracting requirements."
        />

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="grid gap-2 text-sm">
                <div><span class="text-muted-foreground">Asset:</span> {{ handover.asset_name ?? '—' }}</div>
                <div><span class="text-muted-foreground">Tag:</span> {{ handover.tag_code ?? '—' }}</div>
                <div>
                    <span class="text-muted-foreground">From:</span>
                    {{ handover.from_user?.name ?? handover.from_user?.email ?? '—' }}
                </div>
                <div v-if="handover.from_position">
                    <span class="text-muted-foreground">From position:</span>
                    {{ handover.from_position.title }}{{ handover.from_position.department ? `, ${handover.from_position.department}` : '' }}
                </div>
                <div>
                    <span class="text-muted-foreground">To:</span>
                    {{ handover.to_user?.name ?? handover.to_user?.email ?? '—' }}
                </div>
                <div v-if="handover.to_position">
                    <span class="text-muted-foreground">To position:</span>
                    {{ handover.to_position.title }}{{ handover.to_position.department ? `, ${handover.to_position.department}` : '' }}
                </div>
                <div><span class="text-muted-foreground">Status:</span> {{ handover.verified_at ? 'Verified' : 'Pending' }}</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Button variant="ghost" as-child>
                <Link :href="handoverIndex()">Back</Link>
            </Button>

            <Form
                v-if="email_verified && !handover.verified_at"
                v-bind="HandoverController.verify.form(handover.id)"
                v-slot="{ errors, processing }"
                @submit="captureSignature()"
            >
                <input type="hidden" name="token" :value="handover.token" />
                <input type="hidden" name="signature_png" :value="signaturePng" />
                <InputError :message="errors.token" />
                <InputError :message="signatureSizeError" />
                <InputError :message="errors.signature_png" />
                <Button type="submit" :disabled="processing">Verify</Button>
            </Form>

            <Button
                v-if="email_verified && handover.verified_at"
                as-child
            >
                <a :href="handoverReceipt(handover.id).url">Download receipt (PDF)</a>
            </Button>
        </div>

        <div v-if="email_verified && !handover.verified_at" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="mb-2 font-medium">Recipient signature</div>
            <div class="mb-3 text-sm text-muted-foreground">
                Your signature is captured only as an internal proof of accountability for this handover.
            </div>
            <div class="rounded-md border border-input bg-background">
                <VueSignaturePad
                    ref="signaturePad"
                    width="100%"
                    height="180px"
                />
            </div>
            <div class="mt-3 flex items-center gap-2">
                <Button type="button" variant="ghost" @click="clearSignature()">Clear</Button>
                <div class="text-sm text-muted-foreground">Sign before verifying.</div>
            </div>
        </div>

        <div v-if="!email_verified" class="text-sm text-muted-foreground">
            Your email must be verified before you can finalize this handover.
        </div>
    </div>
</template>
