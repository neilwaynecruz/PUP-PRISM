<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
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
    index as productsIndex,
    create as productsCreate,
    bulkActivate as productsBulkActivate,
    bulkChangeCategory as productsBulkChangeCategory,
    bulkDeactivate as productsBulkDeactivate,
    show as productsShow,
    edit as productsEdit,
    destroy as productsDestroy,
} from '@/routes/inventory/products';

type Option = { id: number; name: string };

type ProductRow = {
    id: number;
    sku: string;
    name: string;
    type: 'asset' | 'consumable';
    is_active: boolean;
    reorder_threshold: number;
    category: string | null;
    origin: string | null;
    on_hand_qty: number | null;
    assets_count: number;
    can_delete: boolean;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

const props = defineProps<{
    filters: {
        search: string;
        type: string;
        category_id: number | null;
        origin_id: number | null;
        active: boolean | null;
    };
    products: Paginated<ProductRow>;
    categories: Option[];
    origins: Option[];
    exportUrls: { csv: string; pdf: string };
    can: { create: boolean; bulkUpdate: boolean };
}>();

const selectedProduct = ref<ProductRow | null>(null);
const deleteDialogOpen = ref(false);
const deleteReason = ref('');
const deleteReasonCustom = ref('');

const deletionReasons = [
    { value: 'No longer needed', label: 'No longer needed' },
    { value: 'Damaged/Defective', label: 'Damaged / Defective' },
    { value: 'Data entry error', label: 'Data entry error' },
    { value: 'Duplicate record', label: 'Duplicate record' },
    { value: 'Other', label: 'Other (please specify)' },
];

const isOtherReason = computed(() => deleteReason.value === 'Other');
const canConfirmDelete = computed(() => {
    if (!deleteReason.value) {
        return false;
    }

    if (deleteReason.value === 'Other' && !deleteReasonCustom.value.trim()) {
        return false;
    }

    return true;
});

function getDeletionReason(): string {
    if (deleteReason.value === 'Other') {
        return deleteReasonCustom.value.trim();
    }

    return deleteReason.value;
}

function openDeleteDialog(product: ProductRow): void {
    selectedProduct.value = product;
    deleteReason.value = '';
    deleteReasonCustom.value = '';
    deleteDialogOpen.value = true;
}

function confirmDelete(): void {
    if (!selectedProduct.value || !canConfirmDelete.value) {
        return;
    }

    const productId = selectedProduct.value.id;
    router.delete(productsDestroy(productId).url, {
        data: { deletion_reason: getDeletionReason() },
        onSuccess: () => {
            deleteDialogOpen.value = false;
            selectedProduct.value = null;
            deleteReason.value = '';
            deleteReasonCustom.value = '';
            // Reload page to reflect deleted item in trash
            router.reload({
                only: ['products'],
            } as Record<string, unknown>);
        },
    });
}

// ── Bulk actions ──
const bulkCategoryDialogOpen = ref(false);
const bulkCategoryId = ref<string>('');
const bulkActionDialogOpen = ref(false);
const pendingBulkAction = ref<'activate' | 'deactivate' | null>(null);
const isRefreshing = ref(false);

const {
    selectedIds,
    allSelected,
    someSelected,
    hasSelection,
    toggleSelectAll,
    toggleSelect,
    clearSelection,
} = useBulkSelection(() => props.products.data);

function runBulkActivate(): void {
    pendingBulkAction.value = 'activate';
    bulkActionDialogOpen.value = true;
}

function runBulkDeactivate(): void {
    pendingBulkAction.value = 'deactivate';
    bulkActionDialogOpen.value = true;
}

function openBulkCategoryDialog(): void {
    bulkCategoryId.value = '';
    bulkCategoryDialogOpen.value = true;
}

function confirmBulkCategory(): void {
    if (!bulkCategoryId.value) {
        return;
    }

    router.post(
        productsBulkChangeCategory().url,
        {
            ids: Array.from(selectedIds.value),
            category_id: Number(bulkCategoryId.value),
        },
        {
            onSuccess: () => {
                bulkCategoryDialogOpen.value = false;
                bulkCategoryId.value = '';
                clearSelection();
            },
        },
    );
}

function confirmBulkAction(): void {
    if (!pendingBulkAction.value) {
        return;
    }

    const endpoint =
        pendingBulkAction.value === 'activate'
            ? productsBulkActivate().url
            : productsBulkDeactivate().url;

    router.post(
        endpoint,
        {
            ids: Array.from(selectedIds.value),
        },
        {
            onSuccess: () => {
                clearSelection();
                bulkActionDialogOpen.value = false;
                pendingBulkAction.value = null;
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Products', href: productsIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const categoryId = ref<string>(
    props.filters.category_id ? String(props.filters.category_id) : '',
);
const originId = ref<string>(
    props.filters.origin_id ? String(props.filters.origin_id) : '',
);
const active = ref<string>(
    props.filters.active === null ? '' : props.filters.active ? '1' : '0',
);

const query = computed(() => ({
    search: search.value || undefined,
    type: type.value || undefined,
    category_id: categoryId.value || undefined,
    origin_id: originId.value || undefined,
    active: active.value === '' ? undefined : active.value === '1',
}));

let searchTimer: number | undefined;
watch([search, type, categoryId, originId, active], () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        router.get(productsIndex().url, query.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                isRefreshing.value = true;
            },
            onFinish: () => {
                isRefreshing.value = false;
            },
        });
    }, 250);
});

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Products" />

    <div
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="products-index-page"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Products"
                description="Manage your catalog of assets and consumables."
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <a :href="props.exportUrls.csv">
                        <span
                            class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary"
                        />Export CSV
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <a :href="props.exportUrls.pdf">
                        <span
                            class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary"
                        />Export PDF
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="rounded-lg border-dashed"
                >
                    <Link href="/inventory/products/trash">Trash</Link>
                </Button>
                <Button
                    v-if="can.create"
                    as-child
                    size="sm"
                    data-test="new-product-button"
                    data-testid="new-product-button"
                    data-shortcut-action="new"
                    class="rounded-lg shadow-sm"
                >
                    <Link :href="productsCreate()">New product</Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div
                class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5"
            >
                <Input
                    v-model="search"
                    data-testid="product-search-input"
                    data-shortcut="search"
                    placeholder="Search by SKU or name…"
                    class="h-10 rounded-lg"
                />

                <select
                    v-model="type"
                    class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All types</option>
                    <option value="consumable">Consumables</option>
                    <option value="asset">Assets</option>
                </select>

                <select
                    v-model="categoryId"
                    class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All categories</option>
                    <option
                        v-for="c in categories"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.name }}
                    </option>
                </select>

                <select
                    v-model="originId"
                    class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All origins</option>
                    <option
                        v-for="s in origins"
                        :key="s.id"
                        :value="String(s.id)"
                    >
                        {{ s.name }}
                    </option>
                </select>

                <select
                    v-model="active"
                    class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <!-- Bulk action bar -->
            <div
                v-if="props.can.bulkUpdate && hasSelection"
                class="flex flex-wrap items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-4 py-2 text-sm"
            >
                <span class="font-medium text-primary"
                    >{{ selectedIds.size }} selected</span
                >
                <div class="ml-auto flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7 rounded-lg text-xs"
                        @click="runBulkActivate"
                        >Activate</Button
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7 rounded-lg text-xs"
                        @click="runBulkDeactivate"
                        >Deactivate</Button
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7 rounded-lg text-xs"
                        @click="openBulkCategoryDialog"
                        >Change category</Button
                    >
                </div>
            </div>

            <div
                v-if="isRefreshing"
                class="rounded-xl border border-border/60 bg-card p-4 shadow-sm"
            >
                <TableSkeleton :rows="6" :columns="10" />
            </div>

            <div v-else class="grid gap-4">
                <div class="grid gap-3 md:hidden">
                    <div
                        v-if="products.data.length === 0"
                        class="rounded-xl border border-dashed border-border/60 p-8 text-center text-sm text-muted-foreground"
                    >
                        No products found.
                    </div>

                    <div
                        v-for="p in products.data"
                        :key="p.id"
                        :data-testid="`product-mobile-row-${p.sku}`"
                        class="rounded-xl border border-border/60 bg-card p-4 shadow-sm transition-colors"
                    :class="{ 'bg-primary/5': selectedIds.has(p.id) }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <Checkbox
                                    v-if="props.can.bulkUpdate"
                                    :model-value="selectedIds.has(p.id)"
                                    @update:model-value="() => toggleSelect(p.id)"
                                    aria-label="Select product"
                                    class="mt-1"
                                />
                                <div>
                                    <div class="font-medium">{{ p.name }}</div>
                                    <div
                                        class="font-mono text-[11px] text-muted-foreground"
                                    >
                                        {{ p.sku }}
                                    </div>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="
                                    p.is_active
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                <span
                                    class="h-1 w-1 rounded-full"
                                    :class="
                                        p.is_active
                                            ? 'bg-emerald-500'
                                            : 'bg-muted-foreground/40'
                                    "
                                />
                                {{ p.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <div class="mt-3 grid gap-2 text-sm">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground">Type</span>
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase"
                                    :class="
                                        p.type === 'consumable'
                                            ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                            : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                    "
                                >
                                    {{ p.type }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >Category</span
                                >
                                <span>{{ p.category ?? '—' }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >Origin</span
                                >
                                <span>{{ p.origin ?? '—' }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >On hand</span
                                >
                                <span>{{
                                    p.type === 'consumable'
                                        ? (p.on_hand_qty ?? 0)
                                        : '—'
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-muted-foreground"
                                    >Assets</span
                                >
                                <span>{{
                                    p.type === 'asset' ? p.assets_count : '—'
                                }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                data-test="view-product-button-mobile"
                                data-testid="view-product-button-mobile"
                            >
                                <Link :href="productsShow(p.id)">View</Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                data-test="edit-product-button-mobile"
                                data-testid="edit-product-button-mobile"
                            >
                                <Link :href="productsEdit(p.id)">Edit</Link>
                            </Button>
                            <Button
                                v-if="p.can_delete"
                                variant="ghost"
                                size="sm"
                                class="text-rose-600 hover:text-rose-700"
                                @click="openDeleteDialog(p)"
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm md:block"
                >
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
                            >
                                <th v-if="props.can.bulkUpdate" class="w-10">
                                    <Checkbox
                                        :model-value="allSelected ? true : someSelected ? 'indeterminate' : false"
                                        @update:model-value="toggleSelectAll"
                                        aria-label="Select all products"
                                    />
                                </th>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Origin</th>
                                <th class="text-right">On hand</th>
                                <th class="text-right">Assets</th>
                                <th class="text-right">Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-border/60/60 divide-y">
                            <tr v-if="products.data.length === 0">
                                <td
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                    :colspan="props.can.bulkUpdate ? 10 : 9"
                                >
                                    No products found.
                                </td>
                            </tr>

                            <tr
                                v-for="p in products.data"
                                :key="p.id"
                                :data-testid="`product-row-${p.sku}`"
                                class="group transition-colors hover:bg-muted/40 [&>td]:px-4 [&>td]:py-3"
                                :class="{ 'bg-primary/5': selectedIds.has(p.id) }"
                            >
                                <td v-if="props.can.bulkUpdate">
                                    <Checkbox
                                        :model-value="selectedIds.has(p.id)"
                                        @update:model-value="
                                            () => toggleSelect(p.id)
                                        "
                                        aria-label="Select product"
                                    />
                                </td>
                                <td
                                    class="font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ p.sku }}
                                </td>
                                <td class="font-medium">{{ p.name }}</td>
                                <td>
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase"
                                        :class="
                                            p.type === 'consumable'
                                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                        "
                                    >
                                        {{ p.type }}
                                    </span>
                                </td>
                                <td class="text-muted-foreground">
                                    {{ p.category ?? '—' }}
                                </td>
                                <td class="text-muted-foreground">
                                    {{ p.origin ?? '—' }}
                                </td>
                                <td class="text-right">
                                    <span
                                        v-if="p.type === 'consumable'"
                                        class="font-mono text-xs font-medium"
                                        >{{ p.on_hand_qty ?? 0 }}</span
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="text-right">
                                    <span
                                        v-if="p.type === 'asset'"
                                        class="font-mono text-xs font-medium"
                                        >{{ p.assets_count }}</span
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="text-right">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
                                        :class="
                                            p.is_active
                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        <span
                                            class="h-1 w-1 rounded-full"
                                            :class="
                                                p.is_active
                                                    ? 'bg-emerald-500'
                                                    : 'bg-muted-foreground/40'
                                            "
                                        />
                                        {{
                                            p.is_active ? 'Active' : 'Inactive'
                                        }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                            data-test="view-product-button"
                                            data-testid="view-product-button"
                                            class="h-8 rounded-lg text-xs opacity-60 transition-opacity group-hover:opacity-100"
                                        >
                                            <Link :href="productsShow(p.id)"
                                                >View</Link
                                            >
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                            data-test="edit-product-button"
                                            data-testid="edit-product-button"
                                            class="h-8 rounded-lg text-xs opacity-60 transition-opacity group-hover:opacity-100"
                                        >
                                            <Link :href="productsEdit(p.id)"
                                                >Edit</Link
                                            >
                                        </Button>
                                        <Button
                                            v-if="p.can_delete"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 rounded-lg text-xs text-rose-600 opacity-60 transition-opacity group-hover:opacity-100 hover:text-rose-700"
                                            @click="openDeleteDialog(p)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete product?</DialogTitle>
                        <DialogDescription>
                            This will move
                            <strong>{{ selectedProduct?.name }}</strong> ({{
                                selectedProduct?.sku
                            }}) to the trash. You can restore it later if
                            needed.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label for="delete-reason"
                                >Reason for deletion
                                <span class="text-rose-500">*</span></Label
                            >
                            <Select v-model="deleteReason">
                                <SelectTrigger id="delete-reason">
                                    <SelectValue
                                        placeholder="Select a reason..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="reason in deletionReasons"
                                        :key="reason.value"
                                        :value="reason.value"
                                    >
                                        {{ reason.label }}
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
                                v-model="deleteReasonCustom"
                                placeholder="Enter your reason..."
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                            @click="confirmDelete"
                            >Delete</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Bulk change category dialog -->
            <Dialog v-model:open="bulkCategoryDialogOpen">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Change category</DialogTitle>
                        <DialogDescription>
                            Update the category for
                            <strong
                                >{{ selectedIds.size }} selected
                                product(s)</strong
                            >.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label for="bulk-category"
                                >Category
                                <span class="text-rose-500">*</span></Label
                            >
                            <Select v-model="bulkCategoryId">
                                <SelectTrigger id="bulk-category">
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
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            :disabled="!bulkCategoryId"
                            @click="confirmBulkCategory"
                            >Update</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="bulkActionDialogOpen">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{
                                pendingBulkAction === 'activate'
                                    ? 'Activate selected products?'
                                    : 'Deactivate selected products?'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            This will update
                            <strong
                                >{{ selectedIds.size }} selected
                                product(s)</strong
                            >
                            and keep the current filters and page state intact.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button @click="confirmBulkAction">
                            {{
                                pendingBulkAction === 'activate'
                                    ? 'Confirm activate'
                                    : 'Confirm deactivate'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div
                v-if="products.links.length"
                class="flex flex-wrap items-center justify-center gap-1"
            >
                <Button
                    v-for="(link, i) in products.links"
                    :key="i"
                    variant="ghost"
                    size="sm"
                    :disabled="!link.url"
                    as-child
                    class="h-8 rounded-lg text-xs"
                    :class="
                        link.active
                            ? 'bg-primary/10 font-medium text-primary'
                            : ''
                    "
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span v-else v-html="link.label" />
                </Button>
            </div>
        </div>
    </div>
</template>
