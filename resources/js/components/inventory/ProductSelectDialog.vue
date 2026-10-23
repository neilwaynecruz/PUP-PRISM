<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    Loader2,
    Package,
    Plus,
    Search,
    X,
} from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { lookup as productLookupRoute } from '@/routes/inventory/products';

export type ProductLookupItem = {
    id: number;
    sku: string;
    name: string;
    type: 'consumable' | 'asset';
    category_id: number | null;
    category: string | null;
    on_hand_qty: number;
    reorder_threshold: number;
    unit_price: number | null;
    is_active: boolean;
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        typeFilter?: 'consumable' | 'asset' | 'all';
        onlyInStockDefault?: boolean;
        excludeSkus?: string[];
    }>(),
    {
        title: 'Select product from catalog',
        description:
            'Search products by name or SKU, filter by stock availability, and quickly add items without memorizing codes.',
        typeFilter: 'all',
        onlyInStockDefault: false,
        excludeSkus: () => [],
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'select', product: ProductLookupItem, quantity: number): void;
}>();

const searchTerm = ref('');
const onlyInStock = ref(props.onlyInStockDefault);
const selectedType = ref<'consumable' | 'asset' | 'all'>(props.typeFilter);
const isLoading = ref(false);
const isLoadingMore = ref(false);
const products = ref<ProductLookupItem[]>([]);
const categoryOptions = ref<Array<{ id: number; name: string }>>([]);
const selectedCategory = ref('all');
const currentPage = ref(1);
const lastPage = ref(1);
const totalProducts = ref(0);
const errorMessage = ref('');
const itemQuantities = ref<Record<number, number>>({});
const addedFeedback = ref<Record<number, boolean>>({});

let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;
let activeRequest: AbortController | null = null;

async function fetchProducts(append = false): Promise<void> {
    if (!props.open) {
        return;
    }

    activeRequest?.abort();
    activeRequest = new AbortController();
    errorMessage.value = '';
    isLoading.value = !append;
    isLoadingMore.value = append;

    try {
        const queryParams = new URLSearchParams();

        if (searchTerm.value.trim()) {
            queryParams.set('query', searchTerm.value.trim());
        }

        if (selectedType.value !== 'all') {
            queryParams.set('type', selectedType.value);
        }

        if (onlyInStock.value) {
            queryParams.set('in_stock_only', '1');
        }

        if (selectedCategory.value !== 'all') {
            queryParams.set('category_id', selectedCategory.value);
        }

        queryParams.set('limit', '30');
        queryParams.set('page', String(append ? currentPage.value + 1 : 1));

        const url = `${productLookupRoute.url()}?${queryParams.toString()}`;
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: activeRequest.signal,
        });

        if (!response.ok) {
            throw new Error('Product lookup failed.');
        }

        const data = await response.json();
        const nextProducts = (data.products ?? []) as ProductLookupItem[];
        products.value = append
            ? [...products.value, ...nextProducts]
            : nextProducts;
        categoryOptions.value = data.category_options ?? [];
        currentPage.value = data.pagination?.current_page ?? 1;
        lastPage.value = data.pagination?.last_page ?? 1;
        totalProducts.value = data.pagination?.total ?? products.value.length;

        for (const item of products.value) {
            if (!itemQuantities.value[item.id]) {
                itemQuantities.value[item.id] = 1;
            }
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        if (!append) {
            products.value = [];
        }

        errorMessage.value =
            'The product catalog could not be loaded. Check your connection and try again.';
    } finally {
        isLoading.value = false;
        isLoadingMore.value = false;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selectedType.value = props.typeFilter;
            void fetchProducts();
        } else {
            activeRequest?.abort();
        }
    },
);

watch([searchTerm, onlyInStock, selectedType, selectedCategory], () => {
    if (!props.open) {
        return;
    }

    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        void fetchProducts();
    }, 280);
});

function handleSelect(product: ProductLookupItem): void {
    const enteredQuantity = Number(itemQuantities.value[product.id]);
    const qty = Number.isFinite(enteredQuantity)
        ? Math.min(1_000_000, Math.max(1, Math.trunc(enteredQuantity)))
        : 1;
    itemQuantities.value[product.id] = qty;
    emit('select', product, qty);

    // Provide instant visual confirmation checkmark
    addedFeedback.value[product.id] = true;
    setTimeout(() => {
        addedFeedback.value[product.id] = false;
    }, 1800);
}

function stockBadgeClass(qty: number, reorderThreshold: number): string {
    if (qty <= 0) {
        return 'bg-rose-500/10 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border-rose-200 dark:border-rose-800';
    }

    if (qty <= reorderThreshold) {
        return 'bg-amber-500/10 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border-amber-200 dark:border-amber-800';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800';
}

function isAlreadySelected(sku: string): boolean {
    return props.excludeSkus.includes(sku);
}

function resetFilters(): void {
    searchTerm.value = '';
    onlyInStock.value = props.onlyInStockDefault;
    selectedType.value = props.typeFilter;
    selectedCategory.value = 'all';
}

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);
    activeRequest?.abort();
});
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden p-0 sm:rounded-2xl"
        >
            <DialogHeader class="border-b border-border/60 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Package class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-semibold">{{
                            title
                        }}</DialogTitle>
                        <DialogDescription
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>

                <!-- Search and Filters Bar -->
                <div class="mt-4 grid gap-2.5 sm:grid-cols-[1fr_auto_auto]">
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchTerm"
                            type="search"
                            aria-label="Search the product catalog"
                            placeholder="Search by product name, SKU, or keyword..."
                            class="h-9.5 rounded-lg pr-8 pl-9 text-sm"
                            autofocus
                        />
                        <button
                            v-if="searchTerm"
                            type="button"
                            aria-label="Clear product search"
                            class="absolute top-1/2 right-0 flex size-10 -translate-y-1/2 items-center justify-center text-muted-foreground hover:text-foreground"
                            @click="searchTerm = ''"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 sm:flex-nowrap"
                    >
                        <label
                            class="flex h-9.5 cursor-pointer items-center gap-2 rounded-lg border border-border/60 bg-muted/30 px-3 text-xs font-medium transition hover:bg-muted/50"
                        >
                            <Checkbox v-model:checked="onlyInStock" />
                            <span>In-stock only</span>
                        </label>

                        <select
                            v-if="props.typeFilter === 'all'"
                            v-model="selectedType"
                            aria-label="Filter by product type"
                            class="h-9.5 rounded-lg border border-border/60 bg-background px-3 text-xs font-medium text-foreground transition focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="all">All types</option>
                            <option value="consumable">Consumables</option>
                            <option value="asset">Assets</option>
                        </select>
                        <Badge
                            v-else
                            variant="outline"
                            class="h-9.5 px-3 text-xs capitalize"
                        >
                            {{ props.typeFilter }} products only
                        </Badge>

                        <select
                            v-model="selectedCategory"
                            aria-label="Filter by product category"
                            class="h-9.5 max-w-48 rounded-lg border border-border/60 bg-background px-3 text-xs font-medium text-foreground transition focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="all">All categories</option>
                            <option
                                v-for="category in categoryOptions"
                                :key="category.id"
                                :value="String(category.id)"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </DialogHeader>

            <!-- Results List / Table -->
            <div
                class="max-h-[500px] min-h-[320px] flex-1 overflow-y-auto px-4 py-3 sm:px-6"
            >
                <div
                    v-if="isLoading"
                    class="flex flex-col items-center justify-center py-16 text-muted-foreground"
                >
                    <Loader2 class="h-8 w-8 animate-spin text-primary" />
                    <span class="mt-3 text-xs font-medium"
                        >Searching catalog...</span
                    >
                </div>

                <div
                    v-else-if="errorMessage"
                    role="alert"
                    class="flex flex-col items-center justify-center py-16 text-center"
                >
                    <div
                        class="flex size-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-700 dark:text-rose-300"
                    >
                        <AlertTriangle class="size-6" />
                    </div>
                    <h3 class="mt-3 text-sm font-semibold">
                        Catalog unavailable
                    </h3>
                    <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                        {{ errorMessage }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-4"
                        @click="fetchProducts()"
                    >
                        Try again
                    </Button>
                </div>

                <div
                    v-else-if="products.length === 0"
                    class="flex flex-col items-center justify-center py-16 text-center"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-muted/60 text-muted-foreground"
                    >
                        <Search class="h-6 w-6" />
                    </div>
                    <h3 class="mt-3 text-sm font-semibold">
                        No matching products found
                    </h3>
                    <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                        Try modifying your search query or unchecking "In-stock
                        only" to see items that need reordering.
                    </p>
                    <Button
                        v-if="
                            searchTerm ||
                            onlyInStock ||
                            selectedType !== props.typeFilter ||
                            selectedCategory !== 'all'
                        "
                        variant="ghost"
                        size="sm"
                        class="mt-3 h-8 text-xs text-primary"
                        @click="resetFilters"
                    >
                        Reset filters
                    </Button>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="group flex flex-col justify-between gap-3 rounded-xl border border-border/50 bg-background p-3.5 transition hover:border-primary/40 hover:bg-muted/20 sm:flex-row sm:items-center"
                        :class="{ 'opacity-65': product.on_hand_qty <= 0 }"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{ product.name }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="h-5 px-1.5 font-mono text-[10px] tracking-wide"
                                >
                                    {{ product.sku }}
                                </Badge>
                                <Badge
                                    v-if="product.type === 'asset'"
                                    variant="outline"
                                    class="h-5 border-sky-200 bg-sky-500/10 text-[10px] text-sky-700 dark:border-sky-800 dark:text-sky-300"
                                >
                                    Asset
                                </Badge>
                            </div>

                            <div
                                class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="product.category"
                                    class="inline-flex items-center gap-1"
                                >
                                    <span class="text-muted-foreground/60"
                                        >Category:</span
                                    >
                                    <span
                                        class="font-medium text-foreground/80"
                                        >{{ product.category }}</span
                                    >
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="text-muted-foreground/60"
                                        >Stock:</span
                                    >
                                    <span
                                        class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[11px] font-semibold"
                                        :class="
                                            stockBadgeClass(
                                                product.on_hand_qty,
                                                product.reorder_threshold,
                                            )
                                        "
                                    >
                                        {{ product.on_hand_qty }} available
                                    </span>
                                </span>
                                <span
                                    v-if="isAlreadySelected(product.sku)"
                                    class="text-xs font-medium text-primary"
                                >
                                    • Already in draft (will update qty)
                                </span>
                            </div>
                        </div>

                        <!-- Action controls: Quantity + Add Button -->
                        <div
                            class="flex shrink-0 items-center gap-2 self-end sm:self-center"
                        >
                            <div class="flex items-center gap-1">
                                <Label
                                    :for="`qty-${product.id}`"
                                    class="sr-only"
                                    >Quantity</Label
                                >
                                <Input
                                    :id="`qty-${product.id}`"
                                    v-model.number="itemQuantities[product.id]"
                                    type="number"
                                    min="1"
                                    max="1000000"
                                    class="h-8.5 w-16 rounded-lg text-center text-xs font-semibold"
                                />
                            </div>

                            <Button
                                size="sm"
                                class="h-8.5 gap-1.5 rounded-lg px-3 text-xs transition"
                                :variant="
                                    addedFeedback[product.id]
                                        ? 'default'
                                        : 'secondary'
                                "
                                @click="handleSelect(product)"
                            >
                                <template v-if="addedFeedback[product.id]">
                                    <Check class="h-3.5 w-3.5 text-white" />
                                    <span>Added</span>
                                </template>
                                <template v-else>
                                    <Plus class="h-3.5 w-3.5" />
                                    <span>Add</span>
                                </template>
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="currentPage < lastPage"
                        class="flex justify-center pt-2"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isLoadingMore"
                            @click="fetchProducts(true)"
                        >
                            <Loader2
                                v-if="isLoadingMore"
                                class="mr-2 size-4 animate-spin"
                            />
                            {{
                                isLoadingMore
                                    ? 'Loading…'
                                    : 'Load more products'
                            }}
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter
                class="flex items-center justify-between border-t border-border/60 bg-muted/10 px-6 py-3 sm:justify-between"
            >
                <div class="text-xs text-muted-foreground" aria-live="polite">
                    {{ products.length }} of {{ totalProducts }} matching
                    products shown
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg px-4 text-xs"
                    @click="$emit('update:open', false)"
                >
                    Done
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
