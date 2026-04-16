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
    reserved_qty: number | null;
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

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Products"
            description="Manage your catalog of assets and consumables."
        />

        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="grid gap-3 md:grid-cols-5">
                    <Input v-model="search" placeholder="Search by SKU or name…" />

                    <select
                        v-model="type"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All types</option>
                        <option value="consumable">Consumables</option>
                        <option value="asset">Assets</option>
                    </select>

                    <select
                        v-model="categoryId"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All categories</option>
                        <option v-for="c in categories" :key="c.id" :value="String(c.id)">
                            {{ c.name }}
                        </option>
                    </select>

                    <select
                        v-model="originId"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All origins</option>
                        <option v-for="s in origins" :key="s.id" :value="String(s.id)">
                            {{ s.name }}
                        </option>
                    </select>

                    <select
                        v-model="active"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Button v-if="can.create" as-child>
                        <Link :href="productsCreate()">New product</Link>
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/30 text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-3">
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
                    <tbody class="divide-y divide-border">
                        <tr v-if="products.data.length === 0">
                            <td class="px-4 py-6 text-muted-foreground" colspan="9">
                                No products found.
                            </td>
                        </tr>

                        <tr v-for="p in products.data" :key="p.id" class="[&>td]:px-4 [&>td]:py-3">
                            <td class="font-mono text-xs">{{ p.sku }}</td>
                            <td class="font-medium">{{ p.name }}</td>
                            <td class="capitalize">{{ p.type }}</td>
                            <td class="text-muted-foreground">{{ p.category ?? '—' }}</td>
                            <td class="text-muted-foreground">{{ p.origin ?? '—' }}</td>
                            <td class="text-right">
                                <span v-if="p.type === 'consumable'">{{ p.on_hand_qty ?? 0 }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="text-right">
                                <span v-if="p.type === 'asset'">{{ p.assets_count }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="text-right">
                                <span
                                    class="inline-flex rounded-md px-2 py-1 text-xs"
                                    :class="p.is_active ? 'bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ p.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="productsShow(p.id)">View</Link>
                                    </Button>
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="productsEdit(p.id)">Edit</Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="products.links.length" class="flex flex-wrap items-center justify-center gap-2">
                <Button
                    v-for="(link, i) in products.links"
                    :key="i"
                    variant="ghost"
                    size="sm"
                    :disabled="!link.url"
                    as-child
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

