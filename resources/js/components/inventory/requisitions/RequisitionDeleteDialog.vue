<script setup lang="ts">
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
    requisitionId: number | null;
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
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Move requisition to trash?</DialogTitle>
                <DialogDescription>
                    This will move requisition
                    <strong>#{{ requisitionId }}</strong> to the trash. You can
                    restore it later if needed.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="delete-reason"
                        >Reason for deletion
                        <span class="text-rose-500">*</span></Label
                    >
                    <Select
                        :model-value="reason"
                        @update:model-value="
                            emit('update:reason', String($event ?? ''))
                        "
                    >
                        <SelectTrigger id="delete-reason">
                            <SelectValue placeholder="Select a reason…" />
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
                    <Label for="delete-reason-custom"
                        >Please specify
                        <span class="text-rose-500">*</span></Label
                    >
                    <textarea
                        id="delete-reason-custom"
                        :value="customReason"
                        placeholder="Enter your reason…"
                        rows="3"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        @input="
                            emit(
                                'update:customReason',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    ></textarea>
                </div>
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="!canConfirmDelete"
                    @click="emit('confirm')"
                    >Move to Trash</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
