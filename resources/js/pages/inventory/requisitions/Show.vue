<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import RequisitionController from '@/actions/App/Http/Controllers/Inventory/RequisitionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as requisitionsIndex } from '@/routes/inventory/requisitions';

type Line = {
    id: number;
    sku: string | null;
    name: string | null;
    type: string | null;
    qty_requested: number;
    qty_issued: number;
};

type Person = { id: number; name: string };
type PositionSummary = { title: string; code: string; department: string | null };

defineProps<{
    requisition: {
        id: number;
        status: string;
        notes: string | null;
        approved_at: string | null;
        issued_at: string | null;
        requested_ip_address: string | null;
        approved_ip_address: string | null;
        issued_ip_address: string | null;
        requester: Person | null;
        requester_position: PositionSummary | null;
        approver: Person | null;
        approver_position: PositionSummary | null;
        issuer: Person | null;
        issued_position: PositionSummary | null;
        lines: Line[];
    };
    can: { approve: boolean; reject: boolean; issue: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: requisitionsIndex() },
            { title: 'Requisitions', href: requisitionsIndex() },
            { title: 'View', href: requisitionsIndex() },
        ],
    },
});
</script>

<template>
    <Head :title="`Requisition #${requisition.id}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6" data-testid="requisition-show-page">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading
                variant="small"
                :title="`Requisition #${requisition.id}`"
                :description="`Status: ${requisition.status}`"
            />
            <div class="text-sm font-medium text-muted-foreground" data-testid="requisition-status-value">
                {{ requisition.status }}
            </div>

            <div class="flex items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="requisitionsIndex()">Back</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Requester</div>
                <div class="mt-1 font-medium">{{ requisition.requester?.name ?? '—' }}</div>
                <div v-if="requisition.requester_position" class="text-sm text-muted-foreground">
                    {{ requisition.requester_position.title }}{{ requisition.requester_position.department ? `, ${requisition.requester_position.department}` : '' }}
                </div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Notes</div>
                <div class="mt-1 font-medium">{{ requisition.notes ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Audit trail</div>
                <div class="mt-1 space-y-1 text-sm">
                    <div>Requested IP: {{ requisition.requested_ip_address ?? '—' }}</div>
                    <div>Approved IP: {{ requisition.approved_ip_address ?? '—' }}</div>
                    <div>Issued IP: {{ requisition.issued_ip_address ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Approver</div>
                <div class="mt-1 font-medium">{{ requisition.approver?.name ?? 'Pending approval' }}</div>
                <div v-if="requisition.approver_position" class="text-sm text-muted-foreground">
                    {{ requisition.approver_position.title }}{{ requisition.approver_position.department ? `, ${requisition.approver_position.department}` : '' }}
                </div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Issuer</div>
                <div class="mt-1 font-medium">{{ requisition.issuer?.name ?? 'Not yet issued' }}</div>
                <div v-if="requisition.issued_position" class="text-sm text-muted-foreground">
                    {{ requisition.issued_position.title }}{{ requisition.issued_position.department ? `, ${requisition.issued_position.department}` : '' }}
                </div>
            </div>
            <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                <div class="text-sm text-muted-foreground">Timing</div>
                <div class="mt-1 space-y-1 text-sm">
                    <div>Approved at: {{ requisition.approved_at ?? '—' }}</div>
                    <div>Issued at: {{ requisition.issued_at ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <div class="font-medium">Lines</div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">
                        <tr class="border-b border-border/60">
                            <th class="py-2 pr-3">SKU</th>
                            <th class="py-2 pr-3">Name</th>
                            <th class="py-2 pr-3">Type</th>
                            <th class="py-2 pr-3">Requested</th>
                            <th class="py-2 pr-3">Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="l in requisition.lines"
                            :key="l.id"
                            class="border-b border-border/40"
                        >
                            <td class="py-2 pr-3 font-medium">{{ l.sku ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ l.name ?? '—' }}</td>
                            <td class="py-2 pr-3 text-muted-foreground">{{ l.type ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ l.qty_requested }}</td>
                            <td :data-testid="`requisition-line-issued-${l.sku ?? l.id}`" class="py-2 pr-3">{{ l.qty_issued }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <Form
                v-if="can.approve"
                v-bind="RequisitionController.approve.form(requisition.id)"
                v-slot="{ errors, processing }"
                class="flex flex-1 items-end gap-2"
            >
                <div class="grid flex-1 gap-1">
                    <label class="text-sm font-medium">Approval notes (optional)</label>
                    <Input name="notes" placeholder="Optional notes" />
                    <InputError :message="errors.notes" />
                </div>
                <Button type="submit" :disabled="processing" data-test="approve-requisition-button" data-testid="approve-requisition-button">Approve</Button>
            </Form>

            <Form
                v-if="can.reject"
                v-bind="RequisitionController.reject.form(requisition.id)"
                v-slot="{ errors, processing }"
                class="flex flex-1 items-end gap-2"
            >
                <div class="grid flex-1 gap-1">
                    <label class="text-sm font-medium">Rejection reason</label>
                    <Input name="notes" placeholder="Explain why this request is being rejected" required />
                    <InputError :message="errors.notes" />
                </div>
                <Button type="submit" :disabled="processing" variant="secondary">Reject</Button>
            </Form>

            <Form
                v-if="can.issue"
                v-bind="RequisitionController.issue.form(requisition.id)"
                v-slot="{ errors, processing }"
                class="flex flex-1 items-end gap-2"
            >
                <div class="grid flex-1 gap-1">
                    <label class="text-sm font-medium">Issuance notes (optional)</label>
                    <Input name="notes" placeholder="Optional notes" />
                    <InputError :message="errors.notes" />
                </div>
                <Button type="submit" :disabled="processing" data-test="issue-requisition-button" data-testid="issue-requisition-button">Issue</Button>
            </Form>
        </div>
    </div>
</template>
