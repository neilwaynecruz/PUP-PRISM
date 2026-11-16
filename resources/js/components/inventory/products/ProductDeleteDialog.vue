<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type DeletionReasonOption = {
    value: string;
    label: string;
};

defineProps<{
    open: boolean;
    productName: string | null;
    productSku: string | null;
    reason: string;
    customReason: string;
    deletionReasons: DeletionReasonOption[];
    isOtherReason: boolean;
    canConfirmDelete: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:reason': [value: string];
    'update:customReason': [value: string];
    confirm: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-2">
                <DialogTitle
                    class="flex items-center gap-2 text-rose-600 dark:text-rose-400"
                >
                    <Trash2 class="h-5 w-5" />
                    <span>Move product to trash?</span>
                </DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">
                    This will move
                    <strong class="text-foreground">{{ productName }}</strong>
                    <span v-if="productSku" class="font-mono text-xs">
                        ({{ productSku }})</span
                    >
                    to the trash. You can restore it later if needed.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2">
                <div class="grid gap-2">
                    <Label for="delete-reason" class="text-xs font-semibold">
                        Reason for deletion
                        <span class="text-rose-500">*</span>
                    </Label>
                    <Select
                        :model-value="reason"
                        @update:model-value="
                            emit('update:reason', String($event ?? ''))
                        "
                    >
                        <SelectTrigger
                            id="delete-reason"
                            class="h-10 rounded-lg text-sm"
                        >
                            <SelectValue placeholder="Select a reason..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in deletionReasons"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="isOtherReason" class="grid gap-2">
                    <Label
                        for="delete-reason-custom"
                        class="text-xs font-semibold"
                    >
                        Please specify reason
                        <span class="text-rose-500">*</span>
                    </Label>
                    <textarea
                        id="delete-reason-custom"
                        :value="customReason"
                        placeholder="Enter specific reason for deletion..."
                        rows="3"
                        class="flex min-h-20 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        @input="
                            emit(
                                'update:customReason',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-end">
                <DialogClose as-child>
                    <Button variant="outline" class="rounded-lg">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="!canConfirmDelete"
                    class="rounded-lg"
                    @click="emit('confirm')"
                >
                    Move to Trash
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
