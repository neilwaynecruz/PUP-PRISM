<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Inventory/PurchaseOrderController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPhilippinePeso } from '@/lib/utils';
import {
    create as purchaseOrdersCreate,
    index as purchaseOrdersIndex,
    show as purchaseOrdersShow,
} from '@/routes/inventory/purchase-orders';

type Option = {
    id: number;
    name: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type PurchaseOrderRow = {
    id: number;
    po_number: string;
    status: string;
    status_label: string;
    total_amount: number;
    expected_delivery_at: string | null;
    progress_pct: number;
    supplier: {
        id: number;
        name: string;
    } | null;
    requester: {
        name: string;
    } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const props = defineProps<{
    filters: {
        search: string;
        status: string;
        supplier_id: number | null;
        date_from: string;
        date_to: string;
    };
    purchaseOrders: Paginated<PurchaseOrderRow>;
    suppliers: Option[];
    statuses: StatusOption[];
    can: {
        create: boolean;
    };
}>();

defineOptions({
    name: 'InventoryPurchaseOrderIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: purchaseOrdersIndex() },
            { title: 'Purchase Orders', href: purchaseOrdersIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const supplierId = ref(
    props.filters.supplier_id ? String(props.filters.supplier_id) : '',
);
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let refreshTimer: number | undefined;

watch([search, status, supplierId, dateFrom, dateTo], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            purchaseOrdersIndex().url,
            {
                search: search.value || undefined,
                status: status.value || undefined,
                supplier_id: supplierId.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
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

function generateDrafts(): void {
    router.post(PurchaseOrderController.generate().url);
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Purchase Orders" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Purchase orders"
                description="Track supplier orders from draft through receiving."
            />

            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-lg"
                    @click="generateDrafts"
                >
                    Generate from alerts
                </Button>
                <Button v-if="can.create" as-child size="sm" class="rounded-lg">
                    <Link :href="purchaseOrdersCreate()">New purchase order</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <Input
                v-model="search"
                placeholder="Search PO number or supplier..."
                class="h-10 rounded-lg"
            />
            <select
                v-model="status"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All statuses</option>
                <option
                    v-for="statusOption in statuses"
                    :key="statusOption.value"
                    :value="statusOption.value"
                >
                    {{ statusOption.label }}
                </option>
            </select>
            <select
                v-model="supplierId"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
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
            <Input
                v-model="dateFrom"
                type="date"
                class="h-10 rounded-lg"
            />
            <Input
                v-model="dateTo"
                type="date"
                class="h-10 rounded-lg"
            />
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr
                        class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
                    >
                        <th>PO number</th>
                        <th>Supplier</th>
                        <th>Requester</th>
                        <th>Expected delivery</th>
                        <th>Status</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Progress</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="purchaseOrders.data.length === 0">
                        <td
                            colspan="8"
                            class="px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            No purchase orders found.
                        </td>
                    </tr>

                    <tr
                        v-for="purchaseOrder in purchaseOrders.data"
                        :key="purchaseOrder.id"
                        class="transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3"
                    >
                        <td class="font-medium">{{ purchaseOrder.po_number }}</td>
                        <td>{{ purchaseOrder.supplier?.name ?? '—' }}</td>
                        <td class="text-muted-foreground">
                            {{ purchaseOrder.requester?.name ?? '—' }}
                        </td>
                        <td class="text-muted-foreground">
                            {{ formatDate(purchaseOrder.expected_delivery_at) }}
                        </td>
                        <td>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                            >
                                {{ purchaseOrder.status_label }}
                            </span>
                        </td>
                        <td class="text-right font-mono">
                            {{ formatPhilippinePeso(purchaseOrder.total_amount) }}
                        </td>
                        <td class="text-right">
                            <div class="inline-flex min-w-24 items-center gap-2">
                                <div class="h-2 flex-1 rounded-full bg-muted">
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :style="{
                                            width: `${purchaseOrder.progress_pct}%`,
                                        }"
                                    />
                                </div>
                                <span class="text-xs text-muted-foreground">
                                    {{ purchaseOrder.progress_pct }}%
                                </span>
                            </div>
                        </td>
                        <td class="text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="purchaseOrdersShow(purchaseOrder.id)">
                                    View
                                </Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="purchaseOrders.links.length"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, index) in purchaseOrders.links"
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
