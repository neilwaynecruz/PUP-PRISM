<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    Eye,
    FileSpreadsheet,
    FileText,
    Package,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    SlidersHorizontal,
    Tag,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminTableShell from '@/components/admin/AdminTableShell.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import ProductBulkAndLabelDialogs from '@/components/inventory/products/ProductBulkAndLabelDialogs.vue';
import ProductDeleteDialog from '@/components/inventory/products/ProductDeleteDialog.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import ToastUndo from '@/components/ToastUndo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { getGlobalUndoManager } from '@/composables/useUndoManager';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
import {
    bulkActivate as productsBulkActivate,
    bulkChangeCategory as productsBulkChangeCategory,
    bulkDeactivate as productsBulkDeactivate,
    create as productsCreate,
    destroy as productsDestroy,
    edit as productsEdit,
    index as productsIndex,
    show as productsShow,
    trash as productsTrash,
} from '@/routes/inventory/products';
import { sheet as productLabelsSheet } from '@/routes/inventory/products/labels';

type Option = { id: number; name: string };
type UnitOption = Option & { abbreviation: string };

type ProductRow = {
    id: number;
    sku: string;
    name: string;
    variant: string | null;
    brand: string | null;
    model: string | null;
    color: string | null;
    size: string | null;
    unit_of_measurement: {
        id: number;
        name: string;
        abbreviation: string;
    } | null;
    type: 'asset' | 'consumable';
    is_active: boolean;
    reorder_threshold: number;
    category: string | null;
    origin: string | null;
    supplier: string | null;
    lead_time_days: number | null;
    unit_price: number | null;
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
        supplier_id: number | null;
        unit_of_measurement_id: number | null;
        active: boolean | null;
    };
    products: Paginated<ProductRow>;
    categories: Option[];
    origins: Option[];
    suppliers: Option[];
    unitsOfMeasurement: UnitOption[];
    exportUrls: { csv: string; pdf: string };
    can: { create: boolean; bulkUpdate: boolean };
}>();

defineOptions({
    name: 'InventoryProductIndexPage',
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: productsIndex() },
            { title: 'Products', href: productsIndex() },
        ],
    },
});

// ── Search & Filter State ──
const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const categoryId = ref<string>(
    props.filters.category_id ? String(props.filters.category_id) : '',
);
const originId = ref<string>(
    props.filters.origin_id ? String(props.filters.origin_id) : '',
);
const supplierId = ref<string>(
    props.filters.supplier_id ? String(props.filters.supplier_id) : '',
);
const unitId = ref<string>(
    props.filters.unit_of_measurement_id
        ? String(props.filters.unit_of_measurement_id)
        : '',
);
const active = ref<string>(
    props.filters.active === null ? '' : props.filters.active ? '1' : '0',
);

const showMobileFilters = ref(false);

const hasActiveFilters = computed(() => {
    return (
        Boolean(search.value.trim()) ||
        Boolean(type.value) ||
        Boolean(categoryId.value) ||
        Boolean(originId.value) ||
        Boolean(supplierId.value) ||
        Boolean(unitId.value) ||
        active.value !== ''
    );
});

const activeFilterCount = computed(() => {
    let count = 0;

    if (search.value.trim()) {
        count++;
    }

    if (type.value) {
        count++;
    }

    if (categoryId.value) {
        count++;
    }

    if (originId.value) {
        count++;
    }

    if (supplierId.value) {
        count++;
    }

    if (unitId.value) {
        count++;
    }

    if (active.value !== '') {
        count++;
    }

    return count;
});

function clearAllFilters(): void {
    search.value = '';
    type.value = '';
    categoryId.value = '';
    originId.value = '';
    supplierId.value = '';
    unitId.value = '';
    active.value = '';
}

// ── Overview Statistics (from current catalog & page context) ──
const stats = computed(() => {
    const list = props.products.data;
    const consumables = list.filter((p) => p.type === 'consumable');
    const assets = list.filter((p) => p.type === 'asset');
    const lowStock = consumables.filter(
        (p) => (p.on_hand_qty ?? 0) <= p.reorder_threshold,
    );
    const inactive = list.filter((p) => !p.is_active);

    return {
        total: props.products.total,
        pageCount: list.length,
        consumablesCount: consumables.length,
        assetsCount: assets.length,
        lowStockCount: lowStock.length,
        inactiveCount: inactive.length,
    };
});

// ── Stock Status Helpers ──
function stockLabel(product: ProductRow): string {
    if (product.type !== 'consumable') {
        return 'Asset tracked';
    }

    const onHand = product.on_hand_qty ?? 0;

    if (onHand <= 0) {
        return 'Out of stock';
    }

    if (onHand <= product.reorder_threshold) {
        return 'Low stock';
    }

    return 'In stock';
}

function stockToneClass(product: ProductRow): string {
    const label = stockLabel(product);

    if (label === 'Out of stock') {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300';
    }

    if (label === 'Low stock') {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300';
    }

    return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300';
}

// ── Delete Dialog ──
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

const undoManager = getGlobalUndoManager();

function confirmDelete(): void {
    if (!selectedProduct.value || !canConfirmDelete.value) {
        return;
    }

    const productId = selectedProduct.value.id;
    const productLabel = `${selectedProduct.value.name} (${selectedProduct.value.sku})`;
    const deletionReason = getDeletionReason();
    router.delete(productsDestroy(productId).url, {
        data: { deletion_reason: deletionReason },
        onSuccess: () => {
            deleteDialogOpen.value = false;
            selectedProduct.value = null;
            deleteReason.value = '';
            deleteReasonCustom.value = '';
            undoManager.registerDelete('product', productId, {
                label: productLabel,
                deletionReason,
            });
            router.reload({
                only: ['products'],
            } as Record<string, unknown>);
        },
    });
}

// ── Bulk Actions ──
const bulkCategoryDialogOpen = ref(false);
const bulkCategoryId = ref<string>('');
const bulkActionDialogOpen = ref(false);
const pendingBulkAction = ref<'activate' | 'deactivate' | null>(null);
const bulkActionProcessing = ref(false);
const isRefreshing = ref(false);
const labelScopeDialogOpen = ref(false);

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
    if (!bulkCategoryId.value || bulkActionProcessing.value) {
        return;
    }

    bulkActionProcessing.value = true;

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
            onFinish: () => {
                bulkActionProcessing.value = false;
            },
        },
    );
}

function openBulkLabels(): void {
    if (selectedIds.value.size === 0) {
        return;
    }

    const ids = Array.from(selectedIds.value).join(',');
    router.visit(productLabelsSheet.url({ query: { product_ids: ids } }));
}

function openBulkLabelsFromHeader(): void {
    if (hasSelection.value) {
        openBulkLabels();

        return;
    }

    labelScopeDialogOpen.value = true;
}

function confirmFilteredLabels(): void {
    const queryParams: Record<string, string> = {};
    queryParams.selection_scope = 'filtered';

    if (search.value) {
        queryParams.search = search.value;
    }

    if (type.value) {
        queryParams.type = type.value;
    }

    if (categoryId.value) {
        queryParams.category_id = categoryId.value;
    }

    if (active.value) {
        queryParams.active = active.value;
    }

    labelScopeDialogOpen.value = false;
    router.visit(productLabelsSheet.url({ query: queryParams }));
}

function confirmBulkAction(): void {
    if (!pendingBulkAction.value || bulkActionProcessing.value) {
        return;
    }

    const endpoint =
        pendingBulkAction.value === 'activate'
            ? productsBulkActivate().url
            : productsBulkDeactivate().url;

    bulkActionProcessing.value = true;

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
            onFinish: () => {
                bulkActionProcessing.value = false;
            },
        },
    );
}

// ── Query & Debounced Filtering ──
const query = computed(() => ({
    search: search.value || undefined,
    type: type.value || undefined,
    category_id: categoryId.value || undefined,
    origin_id: originId.value || undefined,
    supplier_id: supplierId.value || undefined,
    unit_of_measurement_id: unitId.value || undefined,
    active: active.value === '' ? undefined : active.value === '1',
}));

let searchTimer: number | undefined;
let isComponentActive = true;

watch([search, type, categoryId, originId, supplierId, unitId, active], () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        if (
            !isComponentActive ||
            !shouldApplyDebouncedVisit('/inventory/products')
        ) {
            return;
        }

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
    isComponentActive = false;
    window.clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Products" />

    <div
        class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-testid="products-index-page"
    >
        <!-- Page Header & Action Controls -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <Heading
                    variant="small"
                    title="Products"
                    description="Find assets and consumables, review stock levels, and manage catalog records."
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Secondary / Export Actions -->
                <div
                    class="flex items-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 p-0.5"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <a
                            :href="props.exportUrls.csv"
                            title="Export catalog as CSV"
                        >
                            <FileSpreadsheet
                                class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                            />
                            <span class="hidden sm:inline">CSV</span>
                        </a>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <a
                            :href="props.exportUrls.pdf"
                            title="Export catalog as PDF"
                        >
                            <FileText
                                class="h-3.5 w-3.5 text-rose-600 dark:text-rose-400"
                            />
                            <span class="hidden sm:inline">PDF</span>
                        </a>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                        @click="openBulkLabelsFromHeader"
                        title="Print QR code labels"
                    >
                        <Printer class="h-3.5 w-3.5 text-primary" />
                        <span>Print labels</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="h-8 gap-1.5 rounded-md px-2.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <Link
                            :href="productsTrash.url()"
                            title="View deleted products in trash"
                        >
                            <Trash2 class="h-3.5 w-3.5 text-muted-foreground" />
                            <span class="hidden sm:inline">Trash</span>
                        </Link>
                    </Button>
                </div>

                <!-- Primary Action: Dominant & Accessible -->
                <Button
                    v-if="can.create"
                    as-child
                    size="sm"
                    data-test="new-product-button"
                    data-testid="new-product-button"
                    data-shortcut-action="new"
                    class="h-11 min-h-11 gap-1.5 rounded-lg px-4 font-medium shadow-xs sm:h-9 sm:min-h-9"
                >
                    <Link :href="productsCreate()">
                        <Plus class="h-4 w-4" />
                        <span>New product</span>
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Catalog Metrics Summary -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
            <div
                class="flex flex-col rounded-xl border border-border/60 bg-card p-3.5 shadow-2xs"
            >
                <div
                    class="flex items-center justify-between text-xs font-medium text-muted-foreground"
                >
                    <span>Total catalog</span>
                    <Package class="h-4 w-4 text-primary/70" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    {{ stats.total }}
                </div>
                <div class="mt-0.5 text-[11px] text-muted-foreground">
                    {{ stats.pageCount }} on this page
                </div>
            </div>

            <div
                class="flex flex-col rounded-xl border border-border/60 bg-card p-3.5 shadow-2xs"
            >
                <div
                    class="flex items-center justify-between text-xs font-medium text-muted-foreground"
                >
                    <span>Consumables</span>
                    <Boxes class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    {{ stats.consumablesCount }}
                </div>
                <div class="mt-0.5 text-[11px] text-muted-foreground">
                    Issued by quantity
                </div>
            </div>

            <div
                class="flex flex-col rounded-xl border border-border/60 bg-card p-3.5 shadow-2xs"
            >
                <div
                    class="flex items-center justify-between text-xs font-medium text-muted-foreground"
                >
                    <span>Asset products</span>
                    <Tag class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    {{ stats.assetsCount }}
                </div>
                <div class="mt-0.5 text-[11px] text-muted-foreground">
                    Property tagged
                </div>
            </div>

            <div
                class="flex flex-col rounded-xl border border-border/60 bg-card p-3.5 shadow-2xs"
            >
                <div
                    class="flex items-center justify-between text-xs font-medium text-muted-foreground"
                >
                    <span>Low stock alerts</span>
                    <AlertTriangle class="h-4 w-4 text-rose-500" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight"
                    :class="
                        stats.lowStockCount > 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : 'text-foreground'
                    "
                >
                    {{ stats.lowStockCount }}
                </div>
                <div class="mt-0.5 text-[11px] text-muted-foreground">
                    At/below threshold
                </div>
            </div>

            <div
                class="col-span-2 flex flex-col rounded-xl border border-border/60 bg-card p-3.5 shadow-2xs sm:col-span-4 lg:col-span-1"
            >
                <div
                    class="flex items-center justify-between text-xs font-medium text-muted-foreground"
                >
                    <span>Inactive</span>
                    <span class="h-2 w-2 rounded-full bg-muted-foreground/40" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    {{ stats.inactiveCount }}
                </div>
                <div class="mt-0.5 text-[11px] text-muted-foreground">
                    Archived from catalog
                </div>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div
            class="rounded-xl border border-border/70 bg-card/95 p-4 shadow-2xs ring-1 ring-black/2 dark:border-border/60 dark:bg-card/90 dark:ring-white/3"
        >
            <div class="flex flex-col gap-3">
                <!-- Mobile Filter Header Toggle -->
                <div class="flex items-center justify-between sm:hidden">
                    <span
                        class="text-xs font-semibold tracking-wide text-foreground uppercase"
                        >Search & Filters</span
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1.5 rounded-lg text-xs"
                        @click="showMobileFilters = !showMobileFilters"
                    >
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        <span>{{
                            showMobileFilters ? 'Hide filters' : 'Show filters'
                        }}</span>
                        <Badge
                            v-if="activeFilterCount > 0"
                            variant="secondary"
                            class="h-5 px-1.5 text-[10px]"
                        >
                            {{ activeFilterCount }}
                        </Badge>
                    </Button>
                </div>

                <fieldset
                    class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7"
                    :class="{ 'hidden sm:grid': !showMobileFilters }"
                >
                    <legend class="sr-only">Filter products</legend>

                    <!-- Search Input -->
                    <label
                        class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span>Search</span>
                        <div class="relative min-w-0">
                            <Input
                                v-model="search"
                                name="product_search"
                                data-testid="product-search-input"
                                data-shortcut="search"
                                autocomplete="off"
                                placeholder="SKU, product, variant, brand, or model…"
                                class="h-10 w-full rounded-lg pr-8 text-sm placeholder:text-muted-foreground/60 focus-visible:ring-2"
                            />
                            <button
                                v-if="search"
                                type="button"
                                class="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                aria-label="Clear search text"
                                @click="search = ''"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </label>

                    <!-- Product Type -->
                    <label
                        class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span>Product type</span>
                        <select
                            v-model="type"
                            name="product_type"
                            aria-label="Product type"
                            class="h-10 w-full min-w-0 truncate rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All types</option>
                            <option value="consumable">Consumables</option>
                            <option value="asset">Assets</option>
                        </select>
                    </label>

                    <!-- Category -->
                    <label
                        class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span>Category</span>
                        <select
                            v-model="categoryId"
                            name="category_id"
                            aria-label="Category"
                            class="h-10 w-full min-w-0 truncate rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
                    </label>

                    <!-- Origin -->
                    <label
                        class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span>Origin</span>
                        <select
                            v-model="originId"
                            name="origin_id"
                            aria-label="Origin"
                            class="h-10 w-full min-w-0 truncate rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
                    </label>

                    <!-- Supplier -->
                    <label
                        class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span>Supplier</span>
                        <select
                            v-model="supplierId"
                            name="supplier_id"
                            aria-label="Supplier"
                            class="h-10 w-full min-w-0 truncate rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All suppliers</option>
                            <option
                                v-for="supplier in suppliers"
                                :key="supplier.id"
                                :value="String(supplier.id)"
                            >
                                {{ supplier.name }}
                            </option>
                        </select>
                    </label>

                    <!-- Unit of measurement -->
                    <label
                        class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span>Unit</span>
                        <select
                            v-model="unitId"
                            name="unit_of_measurement_id"
                            aria-label="Unit of measurement"
                            class="h-10 w-full min-w-0 truncate rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All units</option>
                            <option
                                v-for="unit in unitsOfMeasurement"
                                :key="unit.id"
                                :value="String(unit.id)"
                            >
                                {{ unit.name }} ({{ unit.abbreviation }})
                            </option>
                        </select>
                    </label>

                    <!-- Record Status -->
                    <label
                        class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span>Record status</span>
                        <select
                            v-model="active"
                            name="active"
                            aria-label="Record status"
                            class="h-10 w-full min-w-0 truncate rounded-lg border border-input bg-background px-3 text-sm text-foreground transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </label>
                </fieldset>

                <!-- Active Filter Tags & Clear Filters Action -->
                <div
                    v-if="hasActiveFilters"
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-border/40 pt-3"
                >
                    <div
                        class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span class="font-medium">Active filters:</span>
                        <Badge
                            v-if="search"
                            variant="secondary"
                            class="h-6 gap-1 rounded-md px-2 text-[11px] font-normal"
                        >
                            Search: "{{ search }}"
                            <button
                                type="button"
                                class="hover:text-foreground"
                                aria-label="Remove search filter"
                                @click="search = ''"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                        <Badge
                            v-if="type"
                            variant="secondary"
                            class="h-6 gap-1 rounded-md px-2 text-[11px] font-normal"
                        >
                            Type:
                            {{
                                type === 'consumable' ? 'Consumables' : 'Assets'
                            }}
                            <button
                                type="button"
                                class="hover:text-foreground"
                                aria-label="Remove type filter"
                                @click="type = ''"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                        <Badge
                            v-if="categoryId"
                            variant="secondary"
                            class="h-6 gap-1 rounded-md px-2 text-[11px] font-normal"
                        >
                            Category:
                            {{
                                categories.find(
                                    (c) => String(c.id) === categoryId,
                                )?.name
                            }}
                            <button
                                type="button"
                                class="hover:text-foreground"
                                aria-label="Remove category filter"
                                @click="categoryId = ''"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                        <Badge
                            v-if="originId"
                            variant="secondary"
                            class="h-6 gap-1 rounded-md px-2 text-[11px] font-normal"
                        >
                            Origin:
                            {{
                                origins.find((o) => String(o.id) === originId)
                                    ?.name
                            }}
                            <button
                                type="button"
                                class="hover:text-foreground"
                                aria-label="Remove origin filter"
                                @click="originId = ''"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                        <Badge
                            v-if="supplierId"
                            variant="secondary"
                            class="h-6 gap-1 rounded-md px-2 text-[11px] font-normal"
                        >
                            Supplier:
                            {{
                                suppliers.find(
                                    (s) => String(s.id) === supplierId,
                                )?.name
                            }}
                            <button
                                type="button"
                                class="hover:text-foreground"
                                aria-label="Remove supplier filter"
                                @click="supplierId = ''"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                        <Badge
                            v-if="active !== ''"
                            variant="secondary"
                            class="h-6 gap-1 rounded-md px-2 text-[11px] font-normal"
                        >
                            Status: {{ active === '1' ? 'Active' : 'Inactive' }}
                            <button
                                type="button"
                                class="hover:text-foreground"
                                aria-label="Remove status filter"
                                @click="active = ''"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                        <Badge
                            v-if="unitId"
                            variant="secondary"
                            class="h-6 gap-1 rounded-md px-2 text-[11px] font-normal"
                        >
                            Unit:
                            {{
                                unitsOfMeasurement.find(
                                    (unit) => String(unit.id) === unitId,
                                )?.name
                            }}
                            <button
                                type="button"
                                class="hover:text-foreground"
                                aria-label="Remove unit filter"
                                @click="unitId = ''"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>

                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 gap-1.5 rounded-md px-2 text-xs text-muted-foreground hover:text-foreground"
                        @click="clearAllFilters"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        <span>Clear all filters</span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Bulk Selection Action Bar -->
        <div
            v-if="props.can.bulkUpdate && hasSelection"
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
                    class="h-8 gap-1.5 rounded-lg border-primary/30 text-xs font-semibold text-primary hover:bg-primary/10"
                    @click="openBulkLabels"
                >
                    <Printer class="h-3.5 w-3.5" />
                    <span>Print labels</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="runBulkActivate"
                >
                    Activate
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="runBulkDeactivate"
                >
                    Deactivate
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="openBulkCategoryDialog"
                >
                    Change category
                </Button>
            </div>
        </div>

        <!-- Loading Feedback -->
        <div
            v-if="isRefreshing"
            class="rounded-xl border border-border/60 bg-card p-6 shadow-2xs"
        >
            <TableSkeleton :rows="6" :columns="10" />
        </div>

        <!-- Main Product Content -->
        <div v-else class="flex flex-col gap-4">
            <!-- Mobile Card Layout (< 768px) -->
            <div class="grid gap-3 md:hidden">
                <div
                    v-if="products.data.length === 0"
                    class="rounded-xl border border-dashed border-border/70 p-8 text-center"
                >
                    <EmptyState
                        icon="product"
                        :title="
                            hasActiveFilters
                                ? 'No products match your filters'
                                : 'No products in catalog'
                        "
                        :description="
                            hasActiveFilters
                                ? 'Try adjusting or clearing your search criteria to see products.'
                                : 'Get started by creating your first product record.'
                        "
                        :action-label="
                            hasActiveFilters
                                ? 'Clear all filters'
                                : 'Add Product'
                        "
                        :action-href="
                            hasActiveFilters ? undefined : productsCreate().url
                        "
                        @action="
                            hasActiveFilters ? clearAllFilters() : undefined
                        "
                    />
                </div>

                <div
                    v-for="p in products.data"
                    :key="p.id"
                    :data-testid="`product-mobile-row-${p.sku}`"
                    class="flex flex-col rounded-xl border border-border/70 bg-card p-4 shadow-2xs transition-colors hover:border-border"
                    :class="{
                        'border-primary/40 bg-primary/3 ring-1 ring-primary/20':
                            selectedIds.has(p.id),
                    }"
                >
                    <!-- Card Top: Select + Name + Status -->
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
                                <div class="font-medium text-foreground">
                                    {{ p.name }}
                                </div>
                                <div
                                    v-if="p.variant"
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{ p.variant }}
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

                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="
                                p.is_active
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    p.is_active
                                        ? 'bg-emerald-500'
                                        : 'bg-muted-foreground/40'
                                "
                            />
                            {{ p.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Card Body: Key Attributes -->
                    <div
                        class="mt-3.5 grid grid-cols-2 gap-2 border-t border-border/40 pt-3 text-xs"
                    >
                        <div>
                            <span class="text-muted-foreground">Category:</span>
                            <span class="ml-1 font-medium text-foreground">{{
                                p.category ?? 'Not assigned'
                            }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Unit:</span>
                            <span class="ml-1 font-medium text-foreground">{{
                                p.unit_of_measurement?.abbreviation ??
                                'Not specified'
                            }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Origin:</span>
                            <span class="ml-1 font-medium text-foreground">{{
                                p.origin ?? 'Not specified'
                            }}</span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-muted-foreground">Supplier:</span>
                            <span class="ml-1 font-medium text-foreground">{{
                                p.supplier ?? 'No preferred supplier'
                            }}</span>
                        </div>
                        <div
                            class="col-span-2 flex items-center justify-between border-t border-border/30 pt-2"
                        >
                            <span class="text-muted-foreground">
                                {{
                                    p.type === 'consumable'
                                        ? 'Stock On Hand:'
                                        : 'Registered Assets:'
                                }}
                            </span>
                            <div
                                v-if="p.type === 'consumable'"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="font-mono font-semibold text-foreground"
                                    >{{ p.on_hand_qty ?? 0 }}</span
                                >
                                <span
                                    class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-medium"
                                    :class="stockToneClass(p)"
                                >
                                    {{ stockLabel(p) }}
                                </span>
                            </div>
                            <div v-else>
                                <span
                                    class="font-mono font-semibold text-foreground"
                                    >{{ p.assets_count }} items</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions (>= 44px touch targets) -->
                    <div
                        class="mt-4 flex flex-wrap items-center gap-2 border-t border-border/40 pt-3"
                    >
                        <Button
                            variant="secondary"
                            size="sm"
                            as-child
                            data-test="view-product-button-mobile"
                            data-testid="view-product-button-mobile"
                            class="h-11 min-h-11 flex-1 rounded-lg text-xs font-medium"
                        >
                            <Link :href="productsShow(p.id)">
                                <Eye class="mr-1.5 h-3.5 w-3.5" />
                                <span>View Details</span>
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            as-child
                            data-test="edit-product-button-mobile"
                            data-testid="edit-product-button-mobile"
                            class="h-11 min-h-11 rounded-lg px-3 text-xs"
                        >
                            <Link :href="productsEdit(p.id)">
                                <Pencil class="h-3.5 w-3.5" />
                                <span class="sr-only">Edit</span>
                            </Link>
                        </Button>

                        <Button
                            v-if="p.can_delete"
                            variant="ghost"
                            size="sm"
                            class="h-11 min-h-11 rounded-lg px-3 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                            aria-label="Move to trash"
                            @click="openDeleteDialog(p)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Desktop Table Layout (>= 768px) -->
            <div class="hidden md:block">
                <AdminTableShell min-width="1360px">
                    <caption class="sr-only">
                        Products catalog with SKU, type, stock levels, and
                        administrative actions
                    </caption>
                    <colgroup>
                        <col v-if="props.can.bulkUpdate" class="w-12" />
                        <col class="w-36" />
                        <col class="w-65" />
                        <col class="w-36" />
                        <col class="w-44" />
                        <col class="w-44" />
                        <col class="w-32" />
                        <col class="w-32" />
                        <col class="w-32" />
                        <col class="w-44" />
                    </colgroup>
                    <thead
                        class="border-b border-border/70 bg-muted/40 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th v-if="props.can.bulkUpdate" class="px-4 py-3.5">
                                <Checkbox
                                    :model-value="
                                        allSelected
                                            ? true
                                            : someSelected
                                              ? 'indeterminate'
                                              : false
                                    "
                                    @update:model-value="toggleSelectAll"
                                    aria-label="Select all products"
                                />
                            </th>
                            <th scope="col" class="px-4 py-3.5">SKU</th>
                            <th scope="col" class="px-4 py-3.5">
                                Product Name
                            </th>
                            <th scope="col" class="px-4 py-3.5">Type</th>
                            <th scope="col" class="px-4 py-3.5">Category</th>
                            <th scope="col" class="px-4 py-3.5">Supplier</th>
                            <th scope="col" class="px-4 py-3.5">Origin</th>
                            <th scope="col" class="px-4 py-3.5 text-right">
                                Inventory
                            </th>
                            <th scope="col" class="px-4 py-3.5 text-center">
                                Status
                            </th>
                            <th scope="col" class="px-4 py-3.5 text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-if="products.data.length === 0">
                            <td
                                class="p-0 text-center"
                                :colspan="props.can.bulkUpdate ? 10 : 9"
                            >
                                <EmptyState
                                    icon="product"
                                    :title="
                                        hasActiveFilters
                                            ? 'No products match your filters'
                                            : 'No products in catalog'
                                    "
                                    :description="
                                        hasActiveFilters
                                            ? 'Try adjusting or clearing your search criteria to see products.'
                                            : 'Get started by creating your first product record.'
                                    "
                                    :action-label="
                                        hasActiveFilters
                                            ? 'Clear all filters'
                                            : 'Add Product'
                                    "
                                    :action-href="
                                        hasActiveFilters
                                            ? undefined
                                            : productsCreate().url
                                    "
                                    @action="
                                        hasActiveFilters
                                            ? clearAllFilters()
                                            : undefined
                                    "
                                />
                            </td>
                        </tr>

                        <tr
                            v-for="p in products.data"
                            :key="p.id"
                            :data-testid="`product-row-${p.sku}`"
                            class="group transition-colors hover:bg-muted/30"
                            :class="{
                                'bg-primary/5 hover:bg-primary/8':
                                    selectedIds.has(p.id),
                            }"
                        >
                            <!-- Bulk selection checkbox -->
                            <td v-if="props.can.bulkUpdate" class="px-4 py-3">
                                <Checkbox
                                    :model-value="selectedIds.has(p.id)"
                                    @update:model-value="
                                        () => toggleSelect(p.id)
                                    "
                                    aria-label="Select product"
                                />
                            </td>

                            <!-- SKU Badge -->
                            <td class="px-4 py-3">
                                <AdminCodeBadge :value="p.sku" />
                            </td>

                            <!-- Product Name -->
                            <td class="px-4 py-3">
                                <Link
                                    :href="productsShow(p.id)"
                                    class="line-clamp-2 font-medium text-foreground hover:text-primary hover:underline"
                                >
                                    {{ p.name }}
                                </Link>
                            </td>

                            <!-- Product Type Badge -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-0.5 text-xs font-medium capitalize"
                                    :class="
                                        p.type === 'consumable'
                                            ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                            : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                    "
                                >
                                    <Boxes
                                        v-if="p.type === 'consumable'"
                                        class="h-3.5 w-3.5"
                                    />
                                    <Tag v-else class="h-3.5 w-3.5" />
                                    {{ p.type }}
                                </span>
                            </td>

                            <!-- Category -->
                            <td
                                class="truncate px-4 py-3 text-muted-foreground"
                            >
                                {{ p.category ?? '—' }}
                            </td>

                            <!-- Supplier -->
                            <td
                                class="truncate px-4 py-3 text-muted-foreground"
                                :title="p.supplier ?? undefined"
                            >
                                {{ p.supplier ?? '—' }}
                            </td>

                            <!-- Origin -->
                            <td
                                class="truncate px-4 py-3 text-muted-foreground"
                            >
                                {{ p.origin ?? '—' }}
                            </td>

                            <!-- Inventory summary -->
                            <td class="px-4 py-3 text-right">
                                <div
                                    v-if="p.type === 'consumable'"
                                    class="flex flex-col items-end gap-1"
                                >
                                    <span
                                        class="font-mono text-xs font-semibold text-foreground"
                                    >
                                        {{ p.on_hand_qty ?? 0 }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-full border px-2 py-0.5 font-sans text-[10px] font-medium"
                                        :class="stockToneClass(p)"
                                    >
                                        {{ stockLabel(p) }}
                                    </span>
                                </div>
                                <div v-else class="text-right">
                                    <span
                                        class="font-mono text-xs font-semibold text-foreground"
                                    >
                                        {{ p.assets_count }}
                                    </span>
                                    <span
                                        class="block text-[11px] text-muted-foreground"
                                        >assets</span
                                    >
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
                                    :class="
                                        p.is_active
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            p.is_active
                                                ? 'bg-emerald-500'
                                                : 'bg-muted-foreground/40'
                                        "
                                    />
                                    {{ p.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div
                                    class="flex items-center justify-end gap-1.5 whitespace-nowrap"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                        data-test="view-product-button"
                                        data-testid="view-product-button"
                                        class="h-8 rounded-lg px-2 text-xs text-muted-foreground hover:text-foreground"
                                        title="View product details"
                                    >
                                        <Link :href="productsShow(p.id)">
                                            <Eye class="mr-1 h-3.5 w-3.5" />
                                            <span>View</span>
                                        </Link>
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                        data-test="edit-product-button"
                                        data-testid="edit-product-button"
                                        class="h-8 rounded-lg px-2 text-xs text-muted-foreground hover:text-foreground"
                                        title="Edit product"
                                    >
                                        <Link :href="productsEdit(p.id)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            <span class="sr-only">Edit</span>
                                        </Link>
                                    </Button>

                                    <Button
                                        v-if="p.can_delete"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 rounded-lg px-2 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                        title="Move to trash"
                                        @click="openDeleteDialog(p)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        <span class="sr-only"
                                            >Move to Trash</span
                                        >
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </AdminTableShell>
            </div>

            <!-- Pagination & Result Summary -->
            <div
                v-if="products.links.length > 3 || products.total > 0"
                class="flex flex-col items-center justify-between gap-3 pt-2 sm:flex-row"
            >
                <div class="text-xs text-muted-foreground">
                    Showing
                    <span class="font-medium text-foreground">{{
                        (products.current_page - 1) * products.per_page +
                        (products.data.length > 0 ? 1 : 0)
                    }}</span>
                    to
                    <span class="font-medium text-foreground">{{
                        Math.min(
                            products.current_page * products.per_page,
                            products.total,
                        )
                    }}</span>
                    of
                    <span class="font-medium text-foreground">{{
                        products.total
                    }}</span>
                    results
                </div>

                <AdminPagination :links="products.links" />
            </div>
        </div>

        <!-- Delete to Trash Dialog -->
        <ProductDeleteDialog
            v-model:open="deleteDialogOpen"
            v-model:reason="deleteReason"
            v-model:custom-reason="deleteReasonCustom"
            :product-name="selectedProduct?.name ?? null"
            :product-sku="selectedProduct?.sku ?? null"
            :deletion-reasons="deletionReasons"
            :is-other-reason="isOtherReason"
            :can-confirm-delete="canConfirmDelete"
            @confirm="confirmDelete"
        />

        <!-- Label Scope & Bulk Action Dialogs -->
        <ProductBulkAndLabelDialogs
            v-model:label-scope-dialog-open="labelScopeDialogOpen"
            v-model:bulk-category-dialog-open="bulkCategoryDialogOpen"
            v-model:bulk-category-id="bulkCategoryId"
            v-model:bulk-action-dialog-open="bulkActionDialogOpen"
            :pending-bulk-action="pendingBulkAction"
            :selected-count="selectedIds.size"
            :categories="categories"
            :bulk-action-processing="bulkActionProcessing"
            @confirm-filtered-labels="confirmFilteredLabels"
            @confirm-bulk-category="confirmBulkCategory"
            @confirm-bulk-action="confirmBulkAction"
        />

        <div
            v-if="undoManager.pendingActions.value.length > 0"
            class="fixed right-4 bottom-4 z-50 flex max-w-sm flex-col gap-2"
        >
            <ToastUndo
                v-for="action in undoManager.pendingActions.value"
                :id="action.id"
                :key="action.id"
                :resource-type="action.resourceType"
                :resource-id="action.resourceId"
                :resource-name="
                    String(action.data.label ?? `#${action.resourceId}`)
                "
                :on-undo="undoManager.undo"
                :on-dismiss="undoManager.cancel"
            />
        </div>
    </div>
</template>
