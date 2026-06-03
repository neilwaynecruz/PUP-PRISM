<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Inventory/StockMovementController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type PaginationLink = { url: string | null; label: string; active: boolean };

type Movement = {
    id: number;
    movement_type: string;
    qty_delta: number | null;
    performed_at: string;
    ip_address: string | null;
    notes: string | null;
    product: { id: number; sku: string; name: string } | null;
    stock_lot: { id: number; reference_no: string | null } | null;
    asset: { id: number; tag_code: string; status: string } | null;
    performed_by: { id: number; name: string; email: string };
    accountable_position: { title: string; code: string; department: string | null } | null;
};

type Paginated<T> = { data: T[]; links: PaginationLink[] };

const props = defineProps<{
    filters: { type: string; search: string };
    movements: Paginated<Movement>;
    exportUrls: { csv: string; pdf: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Movements', href: StockMovementController.index() }],
    },
});

const type = ref(props.filters.type ?? '');
const search = ref(props.filters.search ?? '');

const query = computed(() => ({
    type: type.value || undefined,
    search: search.value || undefined,
}));

let timer: number | undefined;
watch([type, search], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        router.get(StockMovementController.index().url, query.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
});
</script>

<template>
    <Head title="Audit movements" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Audit movements"
            description="Every inbound and outbound transaction records the actor, accountable position, timestamp, and IP address."
        />

        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
            <Input v-model="search" placeholder="Search SKU or name…" />

            <select
                v-model="type"
                class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All types</option>
                <option value="receive">Receive</option>
                <option value="issue">Issue</option>
                <option value="transfer">Transfer</option>
                <option value="condemn">Condemn</option>
                <option value="return">Return</option>
            </select>

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <a :href="props.exportUrls.csv">Export CSV</a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="props.exportUrls.pdf">Export PDF</a>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 md:hidden">
            <div
                v-for="m in movements.data"
                :key="m.id"
                class="rounded-xl border border-border/60 p-4 text-sm dark:border-sidebar-border"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium capitalize">{{ m.movement_type }}</div>
                        <div class="font-mono text-xs text-muted-foreground">{{ m.performed_at }}</div>
                    </div>
                    <div class="font-mono text-xs text-muted-foreground">IP {{ m.ip_address ?? '—' }}</div>
                </div>

                <div class="mt-3 space-y-2 text-muted-foreground">
                    <div v-if="m.product">
                        {{ m.product.name }} · {{ m.product.sku }}
                    </div>
                    <div v-if="m.asset">Asset tag: {{ m.asset.tag_code }} ({{ m.asset.status }})</div>
                    <div>Actor: {{ m.performed_by.name }}</div>
                    <div v-if="m.accountable_position">
                        Position: {{ m.accountable_position.title }}{{ m.accountable_position.department ? `, ${m.accountable_position.department}` : '' }}
                    </div>
                    <div v-if="m.qty_delta !== null">Quantity: {{ m.qty_delta }}</div>
                    <div v-if="m.notes">Notes: {{ m.notes }}</div>
                </div>
            </div>
        </div>

        <div class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3">
                        <th>When</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Asset</th>
                        <th class="text-right">Qty</th>
                        <th>Actor</th>
                        <th>Position</th>
                        <th>IP</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="movements.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="9">
                            No movements found.
                        </td>
                    </tr>

                    <tr v-for="m in movements.data" :key="m.id" class="[&>td]:px-4 [&>td]:py-3">
                        <td class="whitespace-nowrap font-mono text-xs">
                            {{ m.performed_at }}
                        </td>
                        <td class="capitalize">{{ m.movement_type }}</td>
                        <td>
                            <div v-if="m.product">
                                <div class="font-medium">{{ m.product.name }}</div>
                                <div class="font-mono text-xs text-muted-foreground">
                                    {{ m.product.sku }}
                                </div>
                            </div>
                            <div v-else class="text-muted-foreground">—</div>
                        </td>
                        <td>
                            <div v-if="m.asset">
                                <div class="font-medium">{{ m.asset.tag_code }}</div>
                                <div class="text-xs text-muted-foreground">{{ m.asset.status }}</div>
                            </div>
                            <div v-else class="text-muted-foreground">—</div>
                        </td>
                        <td class="text-right font-mono">
                            <span v-if="m.qty_delta !== null">{{ m.qty_delta }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td>
                            <div class="font-medium">{{ m.performed_by.name }}</div>
                        </td>
                        <td>
                            <div v-if="m.accountable_position" class="font-medium">
                                {{ m.accountable_position.title }}
                            </div>
                            <div v-if="m.accountable_position" class="text-xs text-muted-foreground">
                                {{ m.accountable_position.department ?? m.accountable_position.code }}
                            </div>
                            <div v-else class="text-muted-foreground">—</div>
                        </td>
                        <td class="font-mono text-xs text-muted-foreground">
                            {{ m.ip_address ?? '—' }}
                        </td>
                        <td class="max-w-sm truncate text-muted-foreground">
                            {{ m.notes ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="movements.links.length" class="flex flex-wrap items-center justify-center gap-2">
            <Button
                v-for="(link, i) in movements.links"
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
</template>

