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
        from_position: {
            title: string;
            code: string;
            department: string | null;
        } | null;
        to_position: {
            title: string;
            code: string;
            department: string | null;
        } | null;
        verified_at: string | null;
    };
    email_verified: boolean;
}>();

const signaturePad = ref<SignaturePadInstance | null>(null);
const signaturePng = ref<string>('');
const signatureSizeError = ref<string>('');
const signaturePngInput = ref<HTMLInputElement | null>(null);
const maxSignatureBytes = 300000;

function captureSignature(event?: Event): void {
    const pad = signaturePad.value;

    if (!pad) {
        return;
    }

    const { isEmpty, data } = pad.saveSignature();
    signaturePng.value = isEmpty ? '' : data;
    signatureSizeError.value = '';

    // Sync to DOM immediately to avoid Vue reactivity flush race with Inertia Form serialization
    if (signaturePngInput.value) {
        signaturePngInput.value.value = signaturePng.value;
    }

    if (signaturePng.value.length > maxSignatureBytes) {
        event?.preventDefault();
        signaturePng.value = '';

        if (signaturePngInput.value) {
            signaturePngInput.value.value = '';
        }

        signatureSizeError.value =
            'Signature is too large. Please clear it and sign again with fewer strokes.';
    }
}

function clearSignature(): void {
    const pad = signaturePad.value;
    pad?.clearSignature();
    signaturePng.value = '';
    signatureSizeError.value = '';

    if (signaturePngInput.value) {
        signaturePngInput.value.value = '';
    }
}
</script>

<template>
    <Head title="Verify handover" />

    <div class="flex flex-col gap-6 p-4 sm:p-6" data-testid="handover-verify-page">
        <Heading
            variant="small"
            title="Verify handover"
            description="Review the transfer details, sign to acknowledge receipt, and confirm accountability."
        />

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_480px]">
            <!-- Left: Handover details -->
            <div class="self-start rounded-xl border border-border/50 bg-card shadow-sm">
                <div class="border-b border-border/40 px-5 py-4">
                    <div class="text-sm font-semibold tracking-tight">Handover details</div>
                    <div class="text-xs text-muted-foreground/80">Asset transfer information and position records.</div>
                </div>
                <div class="px-5 py-4">
                    <div class="grid gap-4">
                        <!-- Status badge -->
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold tracking-wider uppercase',
                                    handover.verified_at
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                                ]"
                            >
                                <span :class="['h-1.5 w-1.5 rounded-full', handover.verified_at ? 'bg-emerald-500' : 'bg-amber-500']" />
                                {{ handover.verified_at ? 'Verified' : 'Pending verification' }}
                            </span>
                        </div>

                        <!-- Asset info -->
                        <div class="grid gap-3 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase">Asset</span>
                                <span class="font-medium">{{ handover.asset_name ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase">Tag code</span>
                                <span class="font-mono text-xs text-muted-foreground">{{ handover.tag_code ?? '—' }}</span>
                            </div>
                        </div>

                        <div class="border-t border-border/30" />

                        <!-- From -->
                        <div class="grid gap-1.5">
                            <div class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase">From</div>
                            <div class="text-sm font-medium">{{ handover.from_user?.name ?? handover.from_user?.email ?? '—' }}</div>
                            <div v-if="handover.from_position" class="text-xs text-muted-foreground">
                                {{ handover.from_position.title }}{{ handover.from_position.department ? `, ${handover.from_position.department}` : '' }}
                            </div>
                        </div>

                        <!-- To -->
                        <div class="grid gap-1.5">
                            <div class="text-xs font-medium tracking-wider text-muted-foreground/70 uppercase">To</div>
                            <div class="text-sm font-medium">{{ handover.to_user?.name ?? handover.to_user?.email ?? '—' }}</div>
                            <div v-if="handover.to_position" class="text-xs text-muted-foreground">
                                {{ handover.to_position.title }}{{ handover.to_position.department ? `, ${handover.to_position.department}` : '' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Actions -->
            <div class="self-start space-y-4">
                <!-- Email not verified warning -->
                <div
                    v-if="!email_verified"
                    class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-5 text-sm"
                >
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-full bg-amber-500/10 p-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-500"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                        </div>
                        <div>
                            <div class="font-medium">Email verification required</div>
                            <div class="mt-1 text-muted-foreground">Your email must be verified before you can finalize this handover.</div>
                        </div>
                    </div>
                </div>

                <!-- Verified state -->
                <div
                    v-else-if="handover.verified_at"
                    class="rounded-xl border border-border/50 bg-card p-5 shadow-sm"
                >
                    <div class="mb-3 text-sm font-semibold tracking-tight">Transfer complete</div>
                    <div class="mb-4 text-sm text-muted-foreground">
                        This handover has been verified and recorded for internal accountability.
                    </div>
                    <Button
                        as-child
                        data-test="download-receipt-button"
                        data-testid="download-receipt-button"
                        class="w-full rounded-lg"
                    >
                        <a :href="handoverReceipt(handover.id).url">Download receipt (PDF)</a>
                    </Button>
                </div>

                <!-- Signature + Verify form -->
                <div
                    v-else
                    class="rounded-xl border border-border/50 bg-card shadow-sm"
                >
                    <div class="border-b border-border/40 px-5 py-4">
                        <div class="text-sm font-semibold tracking-tight">Recipient signature</div>
                        <div class="text-xs text-muted-foreground/80">Sign below to acknowledge receipt of this asset.</div>
                    </div>
                    <div class="px-5 py-4">
                        <div
                            class="rounded-lg border border-input bg-background"
                            data-testid="handover-signature-pad"
                            @mouseup="captureSignature()"
                        >
                            <VueSignaturePad
                                ref="signaturePad"
                                width="100%"
                                height="180px"
                            />
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <Button type="button" variant="ghost" size="sm" class="h-8 text-xs" @click="clearSignature()">
                                Clear signature
                            </Button>
                            <div class="text-xs text-muted-foreground">Sign with your finger or mouse</div>
                        </div>

                        <Form
                            v-bind="HandoverController.verify.form(handover.id)"
                            v-slot="{ errors, processing }"
                            class="mt-4"
                            @submit="captureSignature()"
                        >
                            <input type="hidden" name="token" :value="handover.token" />
                            <input
                                ref="signaturePngInput"
                                type="hidden"
                                name="signature_png"
                                :value="signaturePng"
                            />
                            <div class="space-y-2">
                                <InputError :message="errors.token" />
                                <InputError :message="signatureSizeError" />
                                <InputError :message="errors.signature_png" />
                            </div>
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="verify-handover-button"
                                data-testid="verify-handover-button"
                                class="mt-3 w-full rounded-lg font-semibold shadow-sm"
                            >
                                Confirm handover
                            </Button>
                        </Form>
                    </div>
                </div>

                <Button variant="ghost" size="sm" as-child class="w-full rounded-lg">
                    <Link :href="handoverIndex()">Back to handovers</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
