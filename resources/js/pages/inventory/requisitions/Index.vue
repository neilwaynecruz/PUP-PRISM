<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RequisitionController from '@/actions/App/Http/Controllers/Inventory/RequisitionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as requisitionsIndex, show as requisitionsShow } from '@/routes/inventory/requisitions';

type ReqRow = {
    id: number;
    status: string;
    created_at: string | null;
    requester: { id: number; name: string | null; email: string | null };
    requester_position: { title: string; department: string | null } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PaginationLink[] };

defineProps<{
    requisitions: Paginated<ReqRow>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: requisitionsIndex() },
            { title: 'Requisitions', href: requisitionsIndex() },
        ],
    },
});

const sku = ref('');
const qty = ref('1');
const notes = ref('');

const lines = computed(() => [
    {
        sku: sku.value.trim(),
        qty_requested: Number(qty.value || 1),
    },
]);
</script>

<template>
    <Head title="Requisitions" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading variant="small" title="Requisitions" description="Submit and track issuance requests." />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border lg:col-span-2">
                <div class="grid gap-3 md:hidden">
                    <div
                        v-for="r in requisitions.data"
                        :key="r.id"
                        class="rounded-lg border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium">#{{ r.id }}</div>
                            <div class="text-muted-foreground">{{ r.status }}</div>
                        </div>
                        <div class="mt-2 space-y-1 text-muted-foreground">
                            <div>{{ r.requester.name ?? r.requester.email ?? '—' }}</div>
                            <div v-if="r.requester_position">
                                {{ r.requester_position.title }}{{ r.requester_position.department ? `, ${r.requester_position.department}` : '' }}
                            </div>
                            <div>{{ r.created_at ?? '—' }}</div>
                        </div>
                        <div class="mt-3">
                            <Button variant="ghost" as-child>
                                <Link :href="requisitionsShow(r.id)">Open</Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <th class="py-2 pr-3">ID</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3">Requester</th>
                                <th class="py-2 pr-3">Created</th>
                                <th class="py-2 pr-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in requisitions.data"
                                :key="r.id"
                                class="border-b border-sidebar-border/50 dark:border-sidebar-border"
                            >
                                <td class="py-2 pr-3 font-medium">#{{ r.id }}</td>
                                <td class="py-2 pr-3">{{ r.status }}</td>
                                <td class="py-2 pr-3">
                                    <div>{{ r.requester.name ?? r.requester.email ?? '—' }}</div>
                                    <div v-if="r.requester_position" class="text-xs text-muted-foreground">
                                        {{ r.requester_position.title }}{{ r.requester_position.department ? `, ${r.requester_position.department}` : '' }}
                                    </div>
                                </td>
                                <td class="py-2 pr-3 text-muted-foreground">{{ r.created_at ?? '—' }}</td>
                                <td class="py-2 pr-3 text-right">
                                    <Button variant="ghost" as-child>
                                        <Link :href="requisitionsShow(r.id)">Open</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <Form v-bind="RequisitionController.store.form()" v-slot="{ errors, processing }" class="grid gap-4">
                    <input type="hidden" name="lines" :value="JSON.stringify(lines)" />

                    <Heading variant="small" title="New requisition" description="One-line quick request (SKU + qty)." />

                    <div class="grid gap-2">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <label class="text-sm font-medium">SKU</label>
                            <QrScannerDialog
                                button-label="Scan SKU"
                                title="Scan requisition SKU"
                                description="Use the camera to capture a labeled product QR code, or type the SKU manually."
                                @scanned="sku = $event"
                            />
                        </div>
                        <Input v-model="sku" name="lines[0][sku]" placeholder="e.g. 4801234567890" required />
                        <InputError :message="errors['lines.0.sku']" />
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Qty</label>
                        <Input v-model="qty" name="lines[0][qty_requested]" type="number" min="1" required />
                        <InputError :message="errors['lines.0.qty_requested']" />
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Notes (optional)</label>
                        <Input v-model="notes" name="notes" placeholder="Reason / destination" />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="processing">Submit</Button>
                    </div>
                </Form>
            </div>
        </div>
    </div>
</template>
