<script setup lang="ts">
import { FileText, Loader2, ScanText } from 'lucide-vue-next';
import { onBeforeUnmount, ref, shallowRef } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import type { ProcurementOcrDraft } from '@/lib/ocrReview';

withDefaults(
    defineProps<{
        label: string;
        title: string;
        description: string;
        fieldLabel?: string;
        submitLabel?: string;
        help?: string;
        multiline?: boolean;
        document?: boolean;
        disabled?: boolean;
    }>(),
    { multiline: false, document: false, disabled: false },
);
const emit = defineEmits<{
    reviewed: [value: string];
    document: [value: ProcurementOcrDraft];
}>();
const reader = shallowRef<Component | null>(null);
const trigger = ref<InstanceType<typeof Button> | null>(null);
const open = ref(false);
const loading = ref(false);
const error = ref('');
let mounted = true;
onBeforeUnmount(() => {
    mounted = false;
});

async function start(): Promise<void> {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const component = (
            await import('@/components/inventory/AssetOcrDialog.vue')
        ).default;

        if (mounted) {
            reader.value = component;
            open.value = true;
        }
    } catch {
        if (mounted) {
            error.value =
                'The text reader could not load. Reload to retry, or use the manual fields and search.';
        }
    } finally {
        loading.value = false;
    }
}

function restoreFocus(): void {
    trigger.value?.$el?.focus();
}
</script>

<template>
    <div class="min-w-0">
        <Button
            ref="trigger"
            type="button"
            variant="outline"
            class="group inline-flex min-h-11 max-w-full items-center justify-center gap-2 rounded-lg border-input bg-background/80 px-4 py-2 text-sm font-medium whitespace-normal shadow-xs backdrop-blur-xs transition-all hover:border-primary/40 hover:bg-muted/70 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :disabled="disabled || loading"
            :aria-busy="loading"
            @click="start"
        >
            <Loader2
                v-if="loading"
                aria-hidden="true"
                class="size-4 shrink-0 animate-spin text-primary"
            />
            <FileText
                v-else-if="document"
                aria-hidden="true"
                class="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary"
            />
            <ScanText
                v-else
                aria-hidden="true"
                class="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary"
            />
            <span>{{ loading ? 'Loading text reader…' : label }}</span>
        </Button>
        <p v-if="error" role="alert" class="mt-2 text-sm text-destructive">
            {{ error }}
        </p>
        <component
            :is="reader"
            v-if="reader"
            v-model:open="open"
            :title="title"
            :description="description"
            :field-label="fieldLabel"
            :submit-label="submitLabel"
            :help="help"
            :multiline="multiline"
            :document="document"
            @search="emit('reviewed', $event)"
            @document="emit('document', $event)"
            @closed="restoreFocus"
        />
    </div>
</template>
