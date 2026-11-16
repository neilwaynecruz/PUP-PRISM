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

defineProps<{
    open: boolean;
    pendingBulkAction: 'approve' | 'issue' | null;
    selectedCount: number;
    approvableCount: number;
    issuableCount: number;
    processing: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-3">
                <DialogTitle>
                    {{
                        pendingBulkAction === 'approve'
                            ? `Approve ${approvableCount} requisition(s)?`
                            : `Issue ${issuableCount} requisition(s)?`
                    }}
                </DialogTitle>
                <DialogDescription>
                    <template v-if="pendingBulkAction === 'approve'">
                        This will approve
                        <strong
                            >{{ approvableCount }} submitted
                            requisition(s)</strong
                        >
                        and transition them to Approved status for inventory
                        fulfillment.
                        <span
                            v-if="selectedCount > approvableCount"
                            class="mt-2 block text-xs text-muted-foreground"
                        >
                            Note:
                            {{ selectedCount - approvableCount }}
                            selected requisition(s) are not in submitted status
                            and will be excluded.
                        </span>
                    </template>
                    <template v-else>
                        This will issue inventory for
                        <strong
                            >{{ issuableCount }} approved requisition(s)</strong
                        >
                        based on available stock.
                        <span
                            v-if="selectedCount > issuableCount"
                            class="mt-2 block text-xs text-muted-foreground"
                        >
                            Note:
                            {{ selectedCount - issuableCount }}
                            selected requisition(s) cannot be issued and will be
                            excluded.
                        </span>
                    </template>
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="processing"
                        >Cancel</Button
                    >
                </DialogClose>
                <Button
                    :disabled="
                        processing ||
                        (pendingBulkAction === 'approve'
                            ? approvableCount === 0
                            : issuableCount === 0)
                    "
                    @click="emit('confirm')"
                >
                    {{
                        processing
                            ? 'Processing…'
                            : pendingBulkAction === 'approve'
                              ? `Approve Requisitions (${approvableCount})`
                              : `Issue Requisitions (${issuableCount})`
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
