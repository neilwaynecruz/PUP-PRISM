<script setup lang="ts">
import { AlertTriangle, Printer } from 'lucide-vue-next';
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

type CategoryOption = {
    id: number;
    name: string;
};

defineProps<{
    labelScopeDialogOpen: boolean;
    bulkCategoryDialogOpen: boolean;
    bulkCategoryId: string;
    bulkActionDialogOpen: boolean;
    pendingBulkAction: 'activate' | 'deactivate' | null;
    selectedCount: number;
    categories: CategoryOption[];
    bulkActionProcessing: boolean;
}>();

const emit = defineEmits<{
    'update:labelScopeDialogOpen': [value: boolean];
    'update:bulkCategoryDialogOpen': [value: boolean];
    'update:bulkCategoryId': [value: string];
    'update:bulkActionDialogOpen': [value: boolean];
    confirmFilteredLabels: [];
    confirmBulkCategory: [];
    confirmBulkAction: [];
}>();
</script>

<template>
    <div>
        <!-- Print QR Labels for Filtered Products Scope Dialog -->
        <Dialog
            :open="labelScopeDialogOpen"
            @update:open="emit('update:labelScopeDialogOpen', $event)"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle class="flex items-center gap-2">
                        <Printer class="h-5 w-5 text-primary" />
                        <span>Print labels for filtered products?</span>
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        No products are selected. This will prepare one QR label
                        for every active product matching the current search,
                        type, and category filters, up to the 500-label safety
                        limit.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-3.5 text-xs text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300"
                >
                    <div class="flex items-start gap-2">
                        <AlertTriangle
                            class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                        <div>
                            To print only specific products, close this modal
                            and use the row selection checkboxes first.
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button variant="outline" class="rounded-lg"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        class="rounded-lg"
                        @click="emit('confirmFilteredLabels')"
                    >
                        Continue to label preview
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Change Category Dialog -->
        <Dialog
            :open="bulkCategoryDialogOpen"
            @update:open="emit('update:bulkCategoryDialogOpen', $event)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Change category</DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        Update the category for
                        <strong class="text-foreground"
                            >{{ selectedCount }} selected
                            {{
                                selectedCount === 1 ? 'product' : 'products'
                            }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <div class="grid gap-2">
                        <Label
                            for="bulk-category"
                            class="text-xs font-semibold"
                        >
                            Category
                            <span class="text-rose-500">*</span>
                        </Label>
                        <Select
                            :model-value="bulkCategoryId"
                            @update:model-value="
                                emit(
                                    'update:bulkCategoryId',
                                    String($event ?? ''),
                                )
                            "
                        >
                            <SelectTrigger
                                id="bulk-category"
                                class="h-10 rounded-lg text-sm"
                            >
                                <SelectValue
                                    placeholder="Select a category..."
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="String(c.id)"
                                >
                                    {{ c.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button
                            variant="outline"
                            :disabled="bulkActionProcessing"
                            class="rounded-lg"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        :disabled="!bulkCategoryId || bulkActionProcessing"
                        class="rounded-lg"
                        @click="emit('confirmBulkCategory')"
                    >
                        {{
                            bulkActionProcessing
                                ? 'Updating…'
                                : 'Update Category'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Activate / Deactivate Confirmation Dialog -->
        <Dialog
            :open="bulkActionDialogOpen"
            @update:open="emit('update:bulkActionDialogOpen', $event)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="space-y-2">
                    <DialogTitle>
                        {{
                            pendingBulkAction === 'activate'
                                ? 'Activate selected products?'
                                : 'Deactivate selected products?'
                        }}
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        This will update the active status for
                        <strong class="text-foreground"
                            >{{ selectedCount }} selected
                            {{
                                selectedCount === 1 ? 'product' : 'products'
                            }}</strong
                        >
                        while preserving current filter and page navigation
                        states.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button
                            variant="outline"
                            :disabled="bulkActionProcessing"
                            class="rounded-lg"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        :disabled="bulkActionProcessing"
                        class="rounded-lg"
                        @click="emit('confirmBulkAction')"
                    >
                        {{
                            bulkActionProcessing
                                ? 'Updating…'
                                : pendingBulkAction === 'activate'
                                  ? 'Confirm activate'
                                  : 'Confirm deactivate'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
