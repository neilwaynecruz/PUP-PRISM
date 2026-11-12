<script setup lang="ts">
import { useId } from 'vue';
import OcrAssistButton from '@/components/inventory/OcrAssistButton.vue';
import { Input } from '@/components/ui/input';
defineProps<{ tags: string[] }>();
const serials = defineModel<Record<string, string>>({ required: true });
const id = useId();
</script>

<template>
    <div
        v-if="tags.length"
        class="grid min-w-0 gap-3 rounded-lg border border-border p-3"
    >
        <p class="text-sm text-muted-foreground">
            Manufacturer serial numbers are optional and separate from
            institutional tags. Verify each serial against its physical item.
        </p>
        <div
            v-for="(tag, index) in tags"
            :key="`${tag}-${index}`"
            class="grid min-w-0 gap-2"
        >
            <label :for="`${id}-${index}`" class="text-sm font-medium break-all"
                >Manufacturer serial for {{ tag }}</label
            >
            <Input
                :id="`${id}-${index}`"
                v-model="serials[tag]"
                :maxlength="120"
                class="min-h-11"
                autocomplete="off"
            />
            <OcrAssistButton
                label="Read serial number"
                :title="`Read serial for ${tag}`"
                description="Review the manufacturer's serial number. This does not change the institutional asset tag or submit a receipt."
                field-label="Manufacturer serial number (editable)"
                submit-label="Apply reviewed serial"
                help="Only this asset's optional serial field changes. Verify the physical label before confirming the receipt."
                @reviewed="serials[tag] = $event.slice(0, 120)"
            />
        </div>
    </div>
</template>
