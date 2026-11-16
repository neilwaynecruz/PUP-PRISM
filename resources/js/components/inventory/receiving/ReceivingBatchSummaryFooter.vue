<script setup lang="ts">
import { PackageCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    populatedLines: number;
    totalConsumableQty: number;
    totalAssetTags: number;
    processing: boolean;
}>();
</script>

<template>
    <div
        class="mt-5 flex flex-col gap-3 rounded-xl border border-border/70 bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div
            class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
        >
            <span>
                Active lines:
                <strong class="text-foreground">{{ populatedLines }}</strong>
            </span>
            <span>•</span>
            <span>
                Consumable units:
                <strong class="text-foreground">{{
                    totalConsumableQty
                }}</strong>
            </span>
            <span>•</span>
            <span>
                Serialized asset tags:
                <strong class="text-foreground">{{ totalAssetTags }}</strong>
            </span>
        </div>

        <Button type="submit" :disabled="processing" class="w-full sm:w-auto">
            <PackageCheck class="mr-1.5 size-4" />
            {{ processing ? 'Processing batch…' : 'Receive Batch' }}
        </Button>
    </div>
</template>
