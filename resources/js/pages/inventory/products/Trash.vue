<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

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

const search = ref(props.filters.search);
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const deletedBy = ref(props.filters.deleted_by ?? '');

const hasActiveFilters = computed(() => {
    return search.value || dateFrom.value || dateTo.value || deletedBy.value;
});

function applyFilters(): void {
    router.get('/inventory/products/trash', {
        search: search.value || null,
        date_from: dateFrom.value || null,
        date_to: dateTo.value || null,
        deleted_by: deletedBy.value || null,
    }, { preserveState: true, preserveScroll: true });
}

function resetFilters(): void {
    search.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    deletedBy.value = '';
    applyFilters();
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
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

const selectedProduct = ref<ProductRow | null>(null);
const restoreDialogOpen = ref(false);
const forceDeleteDialogOpen = ref(false);
const bulkRestoreDialogOpen = ref(false);
const bulkForceDeleteDialogOpen = ref(false);
const selectedIds = ref<Set<number>>(new Set());

const allSelected = computed(() => {
    return props.products.data.length > 0 && props.products.data.every(p => selectedIds.value.has(p.id));
});

const hasSelection = computed(() => selectedIds.value.size > 0);

function toggleSelectAll(): void {
    if (allSelected.value) {
        props.products.data.forEach(p => selectedIds.value.delete(p.id));
    } else {
        props.products.data.forEach(p => selectedIds.value.add(p.id));
    }
}

function toggleSelect(id: number): void {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
}

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
    if (!selectedProduct.value) return;
    router.put(`/inventory/products/${selectedProduct.value.id}/restore`, {}, {
        onSuccess: () => {
            restoreDialogOpen.value = false;
            selectedProduct.value = null;
        },
    });
}

function confirmForceDelete(): void {
    if (!selectedProduct.value) return;
    router.delete(`/inventory/products/${selectedProduct.value.id}/force`, {
        onSuccess: () => {
            forceDeleteDialogOpen.value = false;
            selectedProduct.value = null;
        },
    });
}

function confirmBulkRestore(): void {
    router.post('/inventory/products/bulk-restore', {
        ids: Array.from(selectedIds.value),
    }, {
        onSuccess: () => {
            bulkRestoreDialogOpen.value = false;
            selectedIds.value.clear();
        },
    });
}

function confirmBulkForceDelete(): void {
    router.post('/inventory/products/bulk-force-delete', {
        ids: Array.from(selectedIds.value),
    }, {
        onSuccess: () => {
            bulkForceDeleteDialogOpen.value = false;
            selectedIds.value.clear();
        },
    });
}
</script>

<template>
    <Head title="Trash — Products" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <!-- Filters -->
        <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="grid gap-2">
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="search" placeholder="SKU or name..." @keyup.enter="applyFilters" />
                </div>
                <div class="grid gap-2">
                    <Label for="date_from">Deleted From</Label>
                    <Input id="date_from" v-model="dateFrom" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label for="date_to">Deleted To</Label>
                    <Input id="date_to" v-model="dateTo" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label for="deleted_by">Deleted By</Label>
                    <Select v-model="deletedBy">
                        <SelectTrigger id="deleted_by">
                            <SelectValue placeholder="All users" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All users</SelectItem>
                            <SelectItem v-for="user in deleters" :key="user.id" :value="String(user.id)">
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label class="invisible">Actions</Label>
                    <div class="flex gap-2">
                        <Button @click="applyFilters">Filter</Button>
                        <Button v-if="hasActiveFilters" variant="ghost" @click="resetFilters">Reset</Button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Trash"
                description="Deleted products can be restored here."
            />
            <div class="flex items-center gap-2">
                <template v-if="hasSelection">
                    <span class="text-sm text-muted-foreground">{{ selectedIds.size }} selected</span>
                    <Button variant="outline" size="sm" @click="openBulkRestoreDialog">
                        Restore Selected
                    </Button>
                    <Button variant="destructive" size="sm" @click="openBulkForceDeleteDialog">
                        Delete Selected Forever
                    </Button>
                </template>
                <Button variant="outline" size="sm" as-child class="rounded-lg">
                    <Link href="/inventory/products">Back to products</Link>
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">
                        <th class="w-10">
                            <Checkbox
                                :checked="allSelected"
                                @update:checked="toggleSelectAll"
                                aria-label="Select all"
                            />
                        </th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Origin</th>
                        <th>Deleted at</th>
                        <th>Deleted by</th>
                        <th>Reason</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="products.data.length === 0">
                        <td colspan="9" class="p-0">
                            <EmptyState
                                icon="product"
                                title="Trash is empty"
                                description="Deleted products will appear here. You have 30 days to restore them before they are permanently removed."
                                action-label="Back to Products"
                                action-href="/inventory/products"
                            />
                        </td>
                    </tr>
                    <tr
                        v-for="p in products.data"
                        :key="p.id"
                        class="[&>td]:px-4 [&>td]:py-3"
                    >
                        <td>
                            <Checkbox
                                :checked="selectedIds.has(p.id)"
                                @update:checked="() => toggleSelect(p.id)"
                                aria-label="Select product"
                            />
                        </td>
                        <td class="font-mono text-[11px] text-muted-foreground">{{ p.sku }}</td>
                        <td class="font-medium">{{ p.name }}</td>
                        <td>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide"
                                :class="p.type === 'consumable' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'"
                            >
                                {{ p.type }}
                            </span>
                        </td>
                        <td class="text-muted-foreground">{{ p.category ?? '—' }}</td>
                        <td class="text-muted-foreground">{{ p.origin ?? '—' }}</td>
                        <td class="text-muted-foreground">{{ formatDateTime(p.deleted_at) }}</td>
                        <td class="text-muted-foreground">{{ p.deleted_by?.name ?? '—' }}</td>
                        <td class="max-w-[200px] truncate text-muted-foreground" :title="p.deletion_reason ?? undefined">{{ p.deletion_reason ?? '—' }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 rounded-lg text-xs"
                                    @click="openRestoreDialog(p)"
                                >
                                    Restore
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                    @click="openForceDeleteDialog(p)"
                                >
                                    Delete Forever
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="restoreDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Restore product?</DialogTitle>
                    <DialogDescription>
                        This will restore <strong>{{ selectedProduct?.name }}</strong> ({{ selectedProduct?.sku }}) to the active products list.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button @click="confirmRestore">Restore</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="forceDeleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle class="text-rose-600">Permanently delete product?</DialogTitle>
                    <DialogDescription>
                        This will <strong class="text-rose-600">permanently delete</strong> <strong>{{ selectedProduct?.name }}</strong> ({{ selectedProduct?.sku }}).
                        <br><br>
                        <span class="text-rose-600 font-medium">This action cannot be undone.</span> The product and all associated records will be gone forever.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmForceDelete">Permanently Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bulkRestoreDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Bulk restore products?</DialogTitle>
                    <DialogDescription>
                        This will restore <strong>{{ selectedIds.size }} product(s)</strong> to the active products list.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button @click="confirmBulkRestore">Restore All</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bulkForceDeleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle class="text-rose-600">Permanently delete {{ selectedIds.size }} products?</DialogTitle>
                    <DialogDescription>
                        This will <strong class="text-rose-600">permanently delete</strong> <strong>{{ selectedIds.size }} product(s)</strong>.
                        <br><br>
                        <span class="text-rose-600 font-medium">This action cannot be undone.</span> These products and all associated records will be gone forever.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmBulkForceDelete">Permanently Delete All</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div v-if="products.links.length" class="flex flex-wrap items-center justify-center gap-1">
            <Button
                v-for="(link, i) in products.links"
                :key="i"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="link.active ? 'bg-primary/10 text-primary font-medium' : ''"
            >
                <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state>
                    <span v-html="link.label" />
                </Link>
                <span v-else v-html="link.label" />
            </Button>
        </div>
    </div>
</template>
