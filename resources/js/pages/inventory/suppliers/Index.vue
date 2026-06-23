<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create as suppliersCreate,
    index as suppliersIndex,
    show as suppliersShow,
} from '@/routes/inventory/suppliers';

type SupplierRow = {
    id: number;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    lead_time_days: number | null;
    is_active: boolean;
    products_count: number | null;
    purchase_orders_count: number | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const props = defineProps<{
    filters: {
        search: string;
        active: boolean | null;
    };
    suppliers: Paginated<SupplierRow>;
    can: {
        create: boolean;
    };
}>();

defineOptions({
    name: 'InventorySupplierIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: suppliersIndex() },
            { title: 'Suppliers', href: suppliersIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const active = ref(
    props.filters.active === null ? '' : props.filters.active ? '1' : '0',
);

let refreshTimer: number | undefined;

watch([search, active], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            suppliersIndex().url,
            {
                search: search.value || undefined,
                active: active.value === '' ? undefined : active.value === '1',
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 250);
});

onBeforeUnmount(() => {
    window.clearTimeout(refreshTimer);
});
</script>

<template>
    <Head title="Suppliers" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Suppliers"
                description="Maintain vendor records, contacts, and procurement readiness."
            />

            <Button v-if="can.create" as-child size="sm" class="rounded-lg">
                <Link :href="suppliersCreate()">New supplier</Link>
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Input
                v-model="search"
                placeholder="Search supplier, contact, or email..."
                class="h-10 rounded-lg"
            />

            <select
                v-model="active"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr
                        class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
                    >
                        <th>Supplier</th>
                        <th>Contact</th>
                        <th>Lead time</th>
                        <th class="text-right">Products</th>
                        <th class="text-right">POs</th>
                        <th class="text-right">Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="suppliers.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            No suppliers found.
                        </td>
                    </tr>

                    <tr
                        v-for="supplier in suppliers.data"
                        :key="supplier.id"
                        class="transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3"
                    >
                        <td>
                            <div class="font-medium">{{ supplier.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ supplier.email ?? 'No email' }}
                            </div>
                        </td>
                        <td>
                            <div>{{ supplier.contact_person ?? '—' }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ supplier.phone ?? '—' }}
                            </div>
                        </td>
                        <td class="text-muted-foreground">
                            {{
                                supplier.lead_time_days !== null
                                    ? `${supplier.lead_time_days} day(s)`
                                    : '—'
                            }}
                        </td>
                        <td class="text-right font-mono">
                            {{ supplier.products_count ?? 0 }}
                        </td>
                        <td class="text-right font-mono">
                            {{ supplier.purchase_orders_count ?? 0 }}
                        </td>
                        <td class="text-right">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="
                                    supplier.is_active
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{
                                    supplier.is_active ? 'Active' : 'Inactive'
                                }}
                            </span>
                        </td>
                        <td class="text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="suppliersShow(supplier.id)"
                                    >View</Link
                                >
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="suppliers.links.length"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, index) in suppliers.links"
                :key="index"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="link.active ? 'bg-primary/10 text-primary' : ''"
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
</template>
