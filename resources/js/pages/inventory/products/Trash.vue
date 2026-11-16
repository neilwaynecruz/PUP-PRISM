<script setup lang="ts">
defineOptions({ name: 'ProductTrashPage' });

import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Filter,
    RotateCcw,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminTableShell from '@/components/admin/AdminTableShell.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useBulkSelection } from '@/composables/useBulkSelection';
import {
    bulkForceDelete,
    bulkRestore,
    forceDelete,
    index as productsIndex,
    restore,
    trash as productsTrash,
} from '@/routes/inventory/products';

type PaginationLink = { url: string | null; label: string; active: boolean };

type UserStub = { id: number; name: string; email: string };

type ProductRow = {
    id: number;
    sku: string;
    name: string;
    type: 'asset' | 'consumable';
    category: string | null;
    origin: string | null;
    deleted_at: string;
    deleted_by: UserStub | null;
    deletion_reason: string | null;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
};

const props = defineProps<{
    products: Paginated<ProductRow>;
    filters: {
        search: string;
        date_from: string;
        date_to: string;
        deleted_by: number | null;
    };
    deleters: { id: number; name: string }[];
}>();

const search = ref(props.filters.search ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const deletedBy = ref(
    props.filters.deleted_by ? String(props.filters.deleted_by) : '',
);

const hasActiveFilters = computed(() => {
    return (
        Boolean(search.value.trim()) ||
        Boolean(dateFrom.value) ||
        Boolean(dateTo.value) ||
        Boolean(deletedBy.value)
    );
});

function applyFilters(): void {
    router.get(
        productsTrash.url(),
        {
            search: search.value || null,
            date_from: dateFrom.value || null,
            date_to: dateTo.value || null,
            deleted_by: deletedBy.value || null,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function resetFilters(): void {
    search.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    deletedBy.value = '';
    applyFilters();
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return 'Not recorded';
    }

    const d = new Date(iso);

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

// ── Bulk & Single Dialogs ──
const selectedProduct = ref<ProductRow | null>(null);
const restoreDialogOpen = ref(false);
const forceDeleteDialogOpen = ref(false);
const bulkRestoreDialogOpen = ref(false);
const bulkForceDeleteDialogOpen = ref(false);
const isProcessing = ref(false);

const {
    selectedIds,
    allSelected,
    someSelected,
    hasSelection,
    toggleSelectAll,
    toggleSelect,
    clearSelection,
} = useBulkSelection(() => props.products.data);

function openRestoreDialog(product: ProductRow): void {
    selectedProduct.value = product;
    restoreDialogOpen.value = true;
}

function openForceDeleteDialog(product: ProductRow): void {
    selectedProduct.value = product;
    forceDeleteDialogOpen.value = true;
}

function openBulkRestoreDialog(): void {
    bulkRestoreDialogOpen.value = true;
}

function openBulkForceDeleteDialog(): void {
    bulkForceDeleteDialogOpen.value = true;
}

function confirmRestore(): void {
    if (!selectedProduct.value || isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    router.put(
        restore.url(selectedProduct.value.id),
        {},
        {
            onSuccess: () => {
                restoreDialogOpen.value = false;
                selectedProduct.value = null;
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
}

function confirmForceDelete(): void {
    if (!selectedProduct.value || isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    router.delete(forceDelete.url(selectedProduct.value.id), {
        onSuccess: () => {
            forceDeleteDialogOpen.value = false;
            selectedProduct.value = null;
        },
        onFinish: () => {
            isProcessing.value = false;
        },
    });
}

function confirmBulkRestore(): void {
    if (isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    router.post(
        bulkRestore.url(),
        {
            ids: Array.from(selectedIds.value),
        },
        {
            onSuccess: () => {
                bulkRestoreDialogOpen.value = false;
                clearSelection();
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
}

function confirmBulkForceDelete(): void {
    if (isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    router.post(
        bulkForceDelete.url(),
        {
            ids: Array.from(selectedIds.value),
        },
        {
            onSuccess: () => {
                bulkForceDeleteDialogOpen.value = false;
                clearSelection();
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Trash — Products" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <Heading
                    variant="small"
                    title="Product Trash"
                    description="Deleted product records are retained here for accountability and historical reference. Items can be restored or permanently removed."
                />
            </div>

            <Button
                variant="outline"
                size="sm"
                as-child
                class="h-9 gap-1.5 rounded-lg text-xs"
            >
                <Link :href="productsIndex.url()">
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Back to products</span>
                </Link>
            </Button>
        </div>

        <!-- Filters Section -->
        <div
            class="rounded-xl border border-border/70 bg-card/95 p-4 shadow-2xs ring-1 ring-black/[0.02] dark:border-border/60 dark:bg-card/90 dark:ring-white/[0.03]"
        >
            <div
                class="mb-3 flex items-center justify-between border-b border-border/40 pb-2.5"
            >
                <div class="flex items-center gap-2">
                    <Filter class="h-3.5 w-3.5 text-primary" />
                    <span
                        class="text-xs font-semibold tracking-wide text-foreground uppercase"
                        >Filter Deleted Records</span
                    >
                </div>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                    @click="resetFilters"
                >
                    Reset filters
                </button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <!-- Search -->
                <div class="grid gap-1.5">
                    <Label
                        for="search"
                        class="text-xs font-medium text-muted-foreground"
                        >Search</Label
                    >
                    <Input
                        id="search"
                        v-model="search"
                        placeholder="SKU or product name…"
                        class="h-10 rounded-lg text-sm"
                        @keyup.enter="applyFilters"
                    />
                </div>

                <!-- Date From -->
                <div class="grid gap-1.5">
                    <Label
                        for="date_from"
                        class="text-xs font-medium text-muted-foreground"
                        >Deleted From</Label
                    >
                    <Input
                        id="date_from"
                        v-model="dateFrom"
                        type="date"
                        class="h-10 rounded-lg text-sm"
                    />
                </div>

                <!-- Date To -->
                <div class="grid gap-1.5">
                    <Label
                        for="date_to"
                        class="text-xs font-medium text-muted-foreground"
                        >Deleted To</Label
                    >
                    <Input
                        id="date_to"
                        v-model="dateTo"
                        type="date"
                        class="h-10 rounded-lg text-sm"
                    />
                </div>

                <!-- Deleted By -->
                <div class="grid gap-1.5">
                    <Label
                        for="deleted_by"
                        class="text-xs font-medium text-muted-foreground"
                        >Deleted By</Label
                    >
                    <Select v-model="deletedBy">
                        <SelectTrigger
                            id="deleted_by"
                            class="h-10 rounded-lg text-sm"
                        >
                            <SelectValue placeholder="All users" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All users</SelectItem>
                            <SelectItem
                                v-for="user in deleters"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Filter Actions -->
                <div class="grid gap-1.5">
                    <Label class="invisible hidden text-xs sm:block"
                        >Filter Action</Label
                    >
                    <div class="flex items-center gap-2">
                        <Button
                            class="h-10 flex-1 rounded-lg text-xs font-semibold"
                            @click="applyFilters"
                        >
                            Filter
                        </Button>
                        <Button
                            v-if="hasActiveFilters"
                            variant="outline"
                            class="h-10 rounded-lg px-3 text-xs"
                            @click="resetFilters"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Action Bar -->
        <div
            v-if="hasSelection"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/25 bg-primary/5 px-4 py-3 shadow-2xs dark:border-primary/30 dark:bg-primary/10"
        >
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex h-6 items-center rounded-full bg-primary/15 px-2.5 text-xs font-semibold text-primary"
                >
                    {{ selectedIds.size }} selected
                </span>
                <button
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                    @click="clearSelection"
                >
                    Clear selection
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 rounded-lg text-xs font-semibold text-primary"
                    @click="openBulkRestoreDialog"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    <span>Restore selected</span>
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    class="h-8 gap-1.5 rounded-lg text-xs font-semibold"
                    @click="openBulkForceDeleteDialog"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    <span>Delete permanently</span>
                </Button>
            </div>
        </div>

        <!-- Main Deleted Products Content -->
        <div class="flex flex-col gap-4">
            <!-- Mobile Cards (< 768px) -->
            <div class="grid gap-3 md:hidden">
                <div
                    v-if="products.data.length === 0"
                    class="rounded-xl border border-dashed border-border/70 p-8 text-center"
                >
                    <EmptyState
                        icon="trash"
                        :title="
                            hasActiveFilters
                                ? 'No deleted products match your filter'
                                : 'Trash is empty'
                        "
                        :description="
                            hasActiveFilters
                                ? 'Try adjusting your search criteria.'
                                : 'Deleted products will appear here for recovery or permanent removal.'
                        "
                        :action-label="
                            hasActiveFilters
                                ? 'Clear filters'
                                : 'Back to Products'
                        "
                        :action-href="
                            hasActiveFilters ? undefined : productsIndex.url()
                        "
                        @action="hasActiveFilters ? resetFilters() : undefined"
                    />
                </div>

                <div
                    v-for="p in products.data"
                    :key="p.id"
                    class="flex flex-col rounded-xl border border-border/70 bg-card p-4 shadow-2xs"
                    :class="{
                        'border-primary/40 bg-primary/[0.03] ring-1 ring-primary/20':
                            selectedIds.has(p.id),
                    }"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                :model-value="selectedIds.has(p.id)"
                                @update:model-value="() => toggleSelect(p.id)"
                                aria-label="Select product"
                                class="mt-1"
                            />
                            <div>
                                <div class="font-medium text-foreground">
                                    {{ p.name }}
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <AdminCodeBadge :value="p.sku" />
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase"
                                        :class="
                                            p.type === 'consumable'
                                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                        "
                                    >
                                        {{ p.type }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-3 grid gap-1.5 border-t border-border/40 pt-3 text-xs"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Deleted At:</span
                            >
                            <span class="font-medium text-foreground">{{
                                formatDateTime(p.deleted_at)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Deleted By:</span
                            >
                            <span class="font-medium text-foreground">{{
                                p.deleted_by?.name ?? 'System'
                            }}</span>
                        </div>
                        <div
                            v-if="p.deletion_reason"
                            class="flex flex-col gap-0.5 pt-1"
                        >
                            <span class="text-muted-foreground">Reason:</span>
                            <span class="text-foreground italic">{{
                                p.deletion_reason
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex items-center gap-2 border-t border-border/40 pt-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-11 min-h-[44px] flex-1 gap-1.5 rounded-lg text-xs font-medium"
                            @click="openRestoreDialog(p)"
                        >
                            <RotateCcw class="h-3.5 w-3.5 text-primary" />
                            <span>Restore</span>
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            class="h-11 min-h-[44px] flex-1 gap-1.5 rounded-lg text-xs font-semibold"
                            @click="openForceDeleteDialog(p)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>Delete permanently</span>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Desktop Table (>= 768px) -->
            <div class="hidden md:block">
                <AdminTableShell min-width="1200px">
                    <caption class="sr-only">
                        Deleted products retained in trash
                    </caption>
                    <colgroup>
                        <col class="w-12" />
                        <col class="w-36" />
                        <col class="min-w-[180px]" />
                        <col class="w-36" />
                        <col class="w-40" />
                        <col class="w-36" />
                        <col class="w-32" />
                        <col class="w-44" />
                        <col class="w-44" />
                    </colgroup>
                    <thead
                        class="border-b border-border/70 bg-muted/40 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3.5">
                                <Checkbox
                                    :model-value="
                                        allSelected
                                            ? true
                                            : someSelected
                                              ? 'indeterminate'
                                              : false
                                    "
                                    @update:model-value="toggleSelectAll"
                                    aria-label="Select all"
                                />
                            </th>
                            <th scope="col" class="px-4 py-3.5">SKU</th>
                            <th scope="col" class="px-4 py-3.5">
                                Product Name
                            </th>
                            <th scope="col" class="px-4 py-3.5">Type</th>
                            <th scope="col" class="px-4 py-3.5">Category</th>
                            <th scope="col" class="px-4 py-3.5">Deleted At</th>
                            <th scope="col" class="px-4 py-3.5">Deleted By</th>
                            <th scope="col" class="px-4 py-3.5">Reason</th>
                            <th scope="col" class="px-4 py-3.5 text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-if="products.data.length === 0">
                            <td colspan="9" class="p-0 text-center">
                                <EmptyState
                                    icon="trash"
                                    :title="
                                        hasActiveFilters
                                            ? 'No deleted products match your filter'
                                            : 'Trash is empty'
                                    "
                                    :description="
                                        hasActiveFilters
                                            ? 'Try adjusting your search criteria.'
                                            : 'Deleted products will appear here for recovery or permanent removal.'
                                    "
                                    :action-label="
                                        hasActiveFilters
                                            ? 'Clear filters'
                                            : 'Back to Products'
                                    "
                                    :action-href="
                                        hasActiveFilters
                                            ? undefined
                                            : productsIndex.url()
                                    "
                                    @action="
                                        hasActiveFilters
                                            ? resetFilters()
                                            : undefined
                                    "
                                />
                            </td>
                        </tr>

                        <tr
                            v-for="p in products.data"
                            :key="p.id"
                            class="transition-colors hover:bg-muted/30"
                            :class="{
                                'bg-primary/5 hover:bg-primary/8':
                                    selectedIds.has(p.id),
                            }"
                        >
                            <td class="px-4 py-3">
                                <Checkbox
                                    :model-value="selectedIds.has(p.id)"
                                    @update:model-value="
                                        () => toggleSelect(p.id)
                                    "
                                    aria-label="Select product"
                                />
                            </td>

                            <td class="px-4 py-3">
                                <AdminCodeBadge :value="p.sku" />
                            </td>

                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ p.name }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium capitalize"
                                    :class="
                                        p.type === 'consumable'
                                            ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                            : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                    "
                                >
                                    {{ p.type }}
                                </span>
                            </td>

                            <td
                                class="truncate px-4 py-3 text-muted-foreground"
                            >
                                {{ p.category ?? '—' }}
                            </td>

                            <td
                                class="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(p.deleted_at) }}
                            </td>

                            <td
                                class="truncate px-4 py-3 text-muted-foreground"
                            >
                                {{ p.deleted_by?.name ?? '—' }}
                            </td>

                            <td
                                class="max-w-[180px] truncate px-4 py-3 text-xs text-muted-foreground"
                                :title="p.deletion_reason ?? undefined"
                            >
                                {{ p.deletion_reason ?? 'No reason recorded' }}
                            </td>

                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div
                                    class="flex items-center justify-end gap-1.5 whitespace-nowrap"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1 rounded-lg px-2 text-xs font-medium"
                                        @click="openRestoreDialog(p)"
                                    >
                                        <RotateCcw
                                            class="h-3 w-3 text-primary"
                                        />
                                        <span>Restore</span>
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 gap-1 rounded-lg px-2 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                        @click="openForceDeleteDialog(p)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                        <span>Permanent</span>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </AdminTableShell>
            </div>

            <!-- Pagination -->
            <div
                v-if="products.links.length > 3"
                class="flex items-center justify-center pt-2"
            >
                <AdminPagination :links="products.links" />
            </div>
        </div>

        <!-- Single Restore Confirmation Dialog -->
        <Dialog v-model:open="restoreDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="space-y-2">
                    <DialogTitle class="flex items-center gap-2">
                        <RotateCcw class="h-5 w-5 text-primary" />
                        <span>Restore product?</span>
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        This will restore
                        <strong class="text-foreground">{{
                            selectedProduct?.name
                        }}</strong>
                        <span class="font-mono text-xs">
                            ({{ selectedProduct?.sku }})</span
                        >
                        back to the active catalog and make it available for
                        requisitions and receiving.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button variant="outline" class="rounded-lg"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        class="rounded-lg font-semibold"
                        :disabled="isProcessing"
                        @click="confirmRestore"
                    >
                        {{ isProcessing ? 'Restoring…' : 'Restore product' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Single Permanent Delete Confirmation Dialog -->
        <Dialog v-model:open="forceDeleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="space-y-2">
                    <DialogTitle
                        class="flex items-center gap-2 text-rose-600 dark:text-rose-400"
                    >
                        <AlertTriangle class="h-5 w-5" />
                        <span>Permanently delete product?</span>
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        This will permanently remove
                        <strong class="text-foreground">{{
                            selectedProduct?.name
                        }}</strong>
                        <span class="font-mono text-xs">
                            ({{ selectedProduct?.sku }})</span
                        >
                        from the database.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="rounded-xl border border-rose-500/25 bg-rose-500/10 p-3.5 text-xs text-rose-800 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300"
                >
                    <span class="font-bold">Caution:</span> This action is
                    permanent and cannot be undone. All catalog history
                    associated with this item will be deleted.
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button variant="outline" class="rounded-lg"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        variant="destructive"
                        class="rounded-lg font-semibold"
                        :disabled="isProcessing"
                        @click="confirmForceDelete"
                    >
                        {{ isProcessing ? 'Deleting…' : 'Delete permanently' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Restore Dialog -->
        <Dialog v-model:open="bulkRestoreDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="space-y-2">
                    <DialogTitle class="flex items-center gap-2">
                        <RotateCcw class="h-5 w-5 text-primary" />
                        <span>Bulk restore products?</span>
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        This will restore
                        <strong class="text-foreground"
                            >{{ selectedIds.size }} selected
                            {{
                                selectedIds.size === 1 ? 'product' : 'products'
                            }}</strong
                        >
                        to the active inventory catalog.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button variant="outline" class="rounded-lg"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        class="rounded-lg font-semibold"
                        :disabled="isProcessing"
                        @click="confirmBulkRestore"
                    >
                        {{ isProcessing ? 'Restoring…' : 'Restore selected' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Permanent Delete Dialog -->
        <Dialog v-model:open="bulkForceDeleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="space-y-2">
                    <DialogTitle
                        class="flex items-center gap-2 text-rose-600 dark:text-rose-400"
                    >
                        <AlertTriangle class="h-5 w-5" />
                        <span
                            >Permanently delete
                            {{ selectedIds.size }} products?</span
                        >
                    </DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        This will permanently remove
                        <strong class="text-foreground"
                            >{{ selectedIds.size }} selected
                            {{
                                selectedIds.size === 1 ? 'product' : 'products'
                            }}</strong
                        >
                        from the database.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="rounded-xl border border-rose-500/25 bg-rose-500/10 p-3.5 text-xs text-rose-800 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300"
                >
                    <span class="font-bold">Caution:</span> This action cannot
                    be undone. Products referenced by existing purchase orders
                    or historical audits cannot be permanently removed.
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button variant="outline" class="rounded-lg"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        variant="destructive"
                        class="rounded-lg font-semibold"
                        :disabled="isProcessing"
                        @click="confirmBulkForceDelete"
                    >
                        {{ isProcessing ? 'Deleting…' : 'Delete permanently' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
