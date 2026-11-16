<script setup lang="ts">
import { PackageCheck } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type ReceivingProductSummary = {
    id: number;
    sku: string;
    name: string;
    type?: 'consumable' | 'asset' | string | null;
    on_hand_qty?: number;
};

defineProps<{
    sku: string;
    referenceNo: string;
    qty: string;
    processing: boolean;
    matchedProduct: ReceivingProductSummary | null;
}>();
</script>

<template>
    <aside class="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
        <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
            <div class="flex items-center justify-between gap-2">
                <span
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Receipt summary
                </span>
                <Badge variant="outline">Single Item</Badge>
            </div>

            <dl class="mt-4 space-y-2.5 border-b border-border/60 pb-4 text-xs">
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-muted-foreground">SKU</dt>
                    <dd class="font-mono font-semibold">
                        {{ sku.trim() || '—' }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-muted-foreground">Product</dt>
                    <dd class="truncate font-medium text-foreground">
                        {{
                            matchedProduct?.name ||
                            (sku.trim()
                                ? 'Custom / Unmatched SKU'
                                : 'Not selected')
                        }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-muted-foreground">Reference no.</dt>
                    <dd class="font-medium text-foreground">
                        {{ referenceNo.trim() || '—' }}
                    </dd>
                </div>
                <div
                    v-if="matchedProduct"
                    class="flex items-center justify-between gap-2"
                >
                    <dt class="text-muted-foreground">Current stock</dt>
                    <dd class="font-mono font-semibold">
                        {{ matchedProduct.on_hand_qty ?? 0 }}
                    </dd>
                </div>
                <div
                    v-if="
                        matchedProduct &&
                        matchedProduct.type === 'consumable' &&
                        Number(qty || 0) > 0
                    "
                    class="flex items-center justify-between gap-2"
                >
                    <dt class="text-muted-foreground">Stock after receipt</dt>
                    <dd
                        class="font-mono font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        {{
                            (matchedProduct.on_hand_qty ?? 0) + Number(qty || 0)
                        }}
                        (+{{ Number(qty || 0) }})
                    </dd>
                </div>
            </dl>

            <Button
                type="submit"
                :disabled="processing"
                data-testid="receive-stock-button"
                class="mt-4 w-full"
            >
                <PackageCheck class="mr-1.5 size-4" />
                {{ processing ? 'Recording receipt…' : 'Receive Stock' }}
            </Button>
        </div>
    </aside>
</template>
