<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
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
    inheritAttrs: false,
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
let isComponentActive = true;

watch([search, active], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        if (
            !isComponentActive ||
            !shouldApplyDebouncedVisit('/inventory/suppliers')
        ) {
            return;
        }

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
    isComponentActive = false;
    window.clearTimeout(refreshTimer);
});
</script>

<template>
    <Head title="Suppliers" />

    <div
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="suppliers-index-page"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Suppliers"
                description="Maintain vendor records, contacts, and procurement readiness."
            />

            <Button
                v-if="can.create"
                as-child
                size="sm"
                class="min-h-11 rounded-lg sm:min-h-8"
            >
                <Link :href="suppliersCreate()">New supplier</Link>
            </Button>
        </div>

        <fieldset
            class="grid gap-3 rounded-xl border border-border/60 bg-card p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-3"
        >
            <legend class="sr-only">Filter suppliers</legend>
            <label
                class="grid gap-1.5 text-xs font-medium text-muted-foreground sm:col-span-2 lg:col-span-2"
            >
                Search suppliers
                <Input
                    v-model="search"
                    name="supplier_search"
                    autocomplete="off"
                    placeholder="Search supplier, contact, or email..."
                    class="min-h-11 rounded-lg sm:min-h-10"
                />
            </label>

            <label
                class="grid gap-1.5 text-xs font-medium text-muted-foreground"
            >
                Status
                <select
                    v-model="active"
                    name="supplier_status"
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm sm:min-h-10"
                >
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </label>
        </fieldset>

        <!-- Mobile Record Cards -->
        <div
            v-if="suppliers.data.length"
            class="grid gap-3 md:hidden"
            data-testid="suppliers-mobile-list"
        >
            <article
                v-for="supplier in suppliers.data"
                :key="`mobile-${supplier.id}`"
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold break-words text-foreground">
                            {{ supplier.name }}
                        </h2>
                        <p
                            class="mt-0.5 truncate text-xs text-muted-foreground"
                        >
                            {{ supplier.email ?? 'No email' }}
                        </p>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                        :class="
                            supplier.is_active
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ supplier.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Contact</dt>
                        <dd class="mt-0.5 font-medium text-foreground">
                            {{ supplier.contact_person ?? '—' }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ supplier.phone ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Lead time</dt>
                        <dd class="mt-0.5 font-medium text-foreground">
                            {{
                                supplier.lead_time_days !== null
                                    ? `${supplier.lead_time_days} ${supplier.lead_time_days === 1 ? 'day' : 'days'}`
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Products</dt>
                        <dd class="mt-0.5 font-mono text-foreground">
                            {{ supplier.products_count ?? 0 }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Purchase Orders
                        </dt>
                        <dd class="mt-0.5 font-mono text-foreground">
                            {{ supplier.purchase_orders_count ?? 0 }}
                        </dd>
                    </div>
                </dl>

                <Button variant="outline" class="mt-4 min-h-11 w-full" as-child>
                    <Link :href="suppliersShow(supplier.id)"
                        >View Supplier</Link
                    >
                </Button>
            </article>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground md:hidden"
        >
            No suppliers found.
        </div>

        <div
            class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm md:block"
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
                                    ? `${supplier.lead_time_days} ${supplier.lead_time_days === 1 ? 'day' : 'days'}`
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
                                {{ supplier.is_active ? 'Active' : 'Inactive' }}
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

        <AdminPagination :links="suppliers.links" label="Supplier pages" />
    </div>
</template>
