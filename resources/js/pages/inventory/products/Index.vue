<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as productsIndex, create as productsCreate, show as productsShow, edit as productsEdit } from '@/routes/inventory/products';

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
    can: { create: boolean };
}>();

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
const categoryId = ref<string>(props.filters.category_id ? String(props.filters.category_id) : '');
const originId = ref<string>(props.filters.origin_id ? String(props.filters.origin_id) : '');
const active = ref<string>(props.filters.active === null ? '' : props.filters.active ? '1' : '0');

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
        });
    }, 250);
});
</script>

<template>
    <Head title="Products" />

    <div class="flex flex-col gap-6 p-4 sm:p-6" data-testid="products-index-page">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                title="Products"
                description="Manage your catalog of assets and consumables."
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" as-child class="rounded-lg border-dashed">
                    <a :href="props.exportUrls.csv">
                        <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary" />Export CSV
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child class="rounded-lg border-dashed">
                    <a :href="props.exportUrls.pdf">
                        <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary" />Export PDF
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child class="rounded-lg border-dashed">
                    <Link href="/inventory/products/trash">Trash</Link>
                </Button>
                <Button v-if="can.create" as-child size="sm" data-test="new-product-button" data-testid="new-product-button" class="rounded-lg shadow-sm">
                    <Link :href="productsCreate()">New product</Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                <Input v-model="search" data-testid="product-search-input" placeholder="Search by SKU or name…" class="rounded-lg" />

                <select
                    v-model="type"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All types</option>
                    <option value="consumable">Consumables</option>
                    <option value="asset">Assets</option>
                </select>

                <select
                    v-model="categoryId"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All categories</option>
                    <option v-for="c in categories" :key="c.id" :value="String(c.id)">
                        {{ c.name }}
                    </option>
                </select>

                <select
                    v-model="originId"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All origins</option>
                    <option v-for="s in origins" :key="s.id" :value="String(s.id)">
                        {{ s.name }}
                    </option>
                </select>

                <select
                    v-model="active"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm transition-colors focus:border-ring focus:outline-none"
                >
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">
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
                    <tbody class="divide-y divide-border/60/60">
                        <tr v-if="products.data.length === 0">
                            <td class="px-4 py-8 text-center text-sm text-muted-foreground" colspan="9">
                                No products found.
                            </td>
                        </tr>

                        <tr
                            v-for="p in products.data"
                            :key="p.id"
                            :data-testid="`product-row-${p.sku}`"
                            class="group transition-colors hover:bg-muted/40 [&>td]:px-4 [&>td]:py-3"
                        >
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
                            <td class="text-right">
                                <span v-if="p.type === 'consumable'" class="font-mono text-xs font-medium">{{ p.on_hand_qty ?? 0 }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="text-right">
                                <span v-if="p.type === 'asset'" class="font-mono text-xs font-medium">{{ p.assets_count }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="text-right">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
                                    :class="p.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'"
                                >
                                    <span class="h-1 w-1 rounded-full" :class="p.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/40'" />
                                    {{ p.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button variant="ghost" size="sm" as-child data-test="view-product-button" data-testid="view-product-button" class="h-8 rounded-lg text-xs opacity-60 transition-opacity group-hover:opacity-100">
                                        <Link :href="productsShow(p.id)">View</Link>
                                    </Button>
                                    <Button variant="ghost" size="sm" as-child data-test="edit-product-button" data-testid="edit-product-button" class="h-8 rounded-lg text-xs opacity-60 transition-opacity group-hover:opacity-100">
                                        <Link :href="productsEdit(p.id)">Edit</Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

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
    </div>
</template>
