<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RequisitionController from '@/actions/App/Http/Controllers/Inventory/RequisitionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
type PositionSummary = {
    title: string;
    code: string;
    department: string | null;
};

const props = defineProps<{
    requisition: {
        id: number;
        status: string;
        created_at: string | null;
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
    isDeleted?: boolean;
}>();

const rejectDialogOpen = ref(false);

const workflowSteps = computed(() => {
    const currentStatus = requisitionStatus.value;

    return [
        {
            key: 'submitted',
            title: 'Submitted',
            completed: true,
            current: currentStatus === 'Submitted',
            timestamp: props.requisition.created_at,
            actor: props.requisition.requester?.name ?? 'Requester unavailable',
            detail: props.requisition.requester_position?.title ?? null,
        },
        {
            key: 'approved',
            title:
                currentStatus === 'Rejected' ? 'Approval skipped' : 'Approved',
            completed: props.requisition.approved_at !== null,
            current: currentStatus === 'Approved',
            timestamp: props.requisition.approved_at,
            actor:
                props.requisition.approver?.name ??
                (currentStatus === 'Rejected'
                    ? 'Rejected before approval'
                    : 'Pending approval'),
            detail: props.requisition.approver_position?.title ?? null,
        },
        {
            key: 'issued',
            title: 'Issued',
            completed: props.requisition.issued_at !== null,
            current: currentStatus === 'Issued',
            timestamp: props.requisition.issued_at,
            actor:
                props.requisition.issuer?.name ??
                (currentStatus === 'Rejected'
                    ? 'Not issued'
                    : 'Pending issuance'),
            detail: props.requisition.issued_position?.title ?? null,
        },
    ];
});

const requisitionStatus = computed(() => props.requisition.status);

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

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

    <div
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="requisition-show-page"
    >
        <div
            v-if="isDeleted"
            class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950"
        >
            <div class="flex items-center gap-3">
                <span class="h-2 w-2 rounded-full bg-rose-500" />
                <span class="font-medium text-rose-900 dark:text-rose-100"
                    >This requisition has been deleted and is in trash.</span
                >
                <Button variant="ghost" size="sm" as-child class="ml-auto">
                    <Link href="/inventory/requisitions/trash"
                        >Go to Trash</Link
                    >
                </Button>
            </div>
        </div>

        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <Heading
                variant="small"
                :title="`Requisition #${requisition.id}`"
                :description="`Status: ${requisition.status}`"
            />
            <div
                class="text-sm font-medium text-muted-foreground"
                data-testid="requisition-status-value"
            >
                {{ requisition.status }}
            </div>

            <div class="flex items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="requisitionsIndex()">Back</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Requester</div>
                <div class="mt-1 font-medium">
                    {{ requisition.requester?.name ?? '—' }}
                </div>
                <div
                    v-if="requisition.requester_position"
                    class="text-sm text-muted-foreground"
                >
                    {{ requisition.requester_position.title
                    }}{{
                        requisition.requester_position.department
                            ? `, ${requisition.requester_position.department}`
                            : ''
                    }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Notes</div>
                <div class="mt-1 font-medium">
                    {{ requisition.notes ?? '—' }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Audit trail</div>
                <div class="mt-1 space-y-1 text-sm">
                    <div>
                        Requested IP:
                        {{ requisition.requested_ip_address ?? '—' }}
                    </div>
                    <div>
                        Approved IP:
                        {{ requisition.approved_ip_address ?? '—' }}
                    </div>
                    <div>
                        Issued IP: {{ requisition.issued_ip_address ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <div class="font-medium">Workflow timeline</div>
                    <div class="text-sm text-muted-foreground">
                        Submitted, approved, and issued milestones update as the
                        requisition moves through the workflow.
                    </div>
                </div>
                <div class="text-sm text-muted-foreground">
                    Current status: {{ requisition.status }}
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div
                    v-for="step in workflowSteps"
                    :key="step.key"
                    class="rounded-xl border p-4"
                    :class="
                        step.completed
                            ? 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900 dark:bg-emerald-950/30'
                            : step.current
                              ? 'border-amber-200 bg-amber-50/80 dark:border-amber-900 dark:bg-amber-950/30'
                              : 'border-border/60 bg-background/60'
                    "
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold"
                            :class="
                                step.completed
                                    ? 'bg-emerald-600 text-white'
                                    : step.current
                                      ? 'bg-amber-500 text-white'
                                      : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{
                                step.completed
                                    ? '✓'
                                    : step.key === 'submitted'
                                      ? '1'
                                      : step.key === 'approved'
                                        ? '2'
                                        : '3'
                            }}
                        </span>
                        <div>
                            <div class="font-medium">{{ step.title }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    step.completed
                                        ? 'Completed'
                                        : step.current
                                          ? 'In progress'
                                          : 'Pending'
                                }}
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 space-y-1 text-sm">
                        <div>{{ step.actor }}</div>
                        <div v-if="step.detail" class="text-muted-foreground">
                            {{ step.detail }}
                        </div>
                        <div class="text-muted-foreground">
                            {{ formatDateTime(step.timestamp) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Approver</div>
                <div class="mt-1 font-medium">
                    {{ requisition.approver?.name ?? 'Pending approval' }}
                </div>
                <div
                    v-if="requisition.approver_position"
                    class="text-sm text-muted-foreground"
                >
                    {{ requisition.approver_position.title
                    }}{{
                        requisition.approver_position.department
                            ? `, ${requisition.approver_position.department}`
                            : ''
                    }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
                <div class="text-sm text-muted-foreground">Issuer</div>
                <div class="mt-1 font-medium">
                    {{ requisition.issuer?.name ?? 'Not yet issued' }}
                </div>
                <div
                    v-if="requisition.issued_position"
                    class="text-sm text-muted-foreground"
                >
                    {{ requisition.issued_position.title
                    }}{{
                        requisition.issued_position.department
                            ? `, ${requisition.issued_position.department}`
                            : ''
                    }}
                </div>
            </div>
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
            >
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

            <div class="grid gap-3 md:hidden">
                <div
                    v-for="l in requisition.lines"
                    :key="l.id"
                    class="rounded-xl border border-border/40 p-4 text-sm"
                >
                    <div class="font-medium">{{ l.name ?? '—' }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ l.sku ?? '—' }}
                    </div>
                    <div class="mt-3 grid gap-2">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Type</span>
                            <span>{{ l.type ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Requested</span>
                            <span>{{ l.qty_requested }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Issued</span>
                            <span
                                :data-testid="`requisition-line-issued-mobile-${l.sku ?? l.id}`"
                                >{{ l.qty_issued }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead
                        class="text-left text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                    >
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
                            <td class="py-2 pr-3 font-medium">
                                {{ l.sku ?? '—' }}
                            </td>
                            <td class="py-2 pr-3">{{ l.name ?? '—' }}</td>
                            <td class="py-2 pr-3 text-muted-foreground">
                                {{ l.type ?? '—' }}
                            </td>
                            <td class="py-2 pr-3">{{ l.qty_requested }}</td>
                            <td
                                :data-testid="`requisition-line-issued-${l.sku ?? l.id}`"
                                class="py-2 pr-3"
                            >
                                {{ l.qty_issued }}
                            </td>
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
                    <label class="text-sm font-medium"
                        >Approval notes (optional)</label
                    >
                    <Input name="notes" placeholder="Optional notes" />
                    <InputError :message="errors.notes" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="approve-requisition-button"
                    data-testid="approve-requisition-button"
                    >Approve</Button
                >
            </Form>

            <Dialog v-if="can.reject" v-model:open="rejectDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="secondary" type="button">Reject</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Reject requisition?</DialogTitle>
                        <DialogDescription>
                            This will permanently reject requisition #{{
                                requisition.id
                            }}. The requester will be notified.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        v-bind="
                            RequisitionController.reject.form(requisition.id)
                        "
                        v-slot="{ errors, processing }"
                        @success="rejectDialogOpen = false"
                        class="grid gap-4"
                    >
                        <div class="grid gap-2">
                            <Label for="reject_notes">Rejection reason</Label>
                            <Input
                                id="reject_notes"
                                name="notes"
                                placeholder="Explain why this request is being rejected"
                                required
                            />
                            <InputError :message="errors.notes" />
                        </div>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" type="button"
                                    >Cancel</Button
                                >
                            </DialogClose>
                            <Button
                                type="submit"
                                :disabled="processing"
                                variant="destructive"
                                >Confirm reject</Button
                            >
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>

            <Form
                v-if="can.issue"
                v-bind="RequisitionController.issue.form(requisition.id)"
                v-slot="{ errors, processing }"
                class="flex flex-1 items-end gap-2"
            >
                <div class="grid flex-1 gap-1">
                    <label class="text-sm font-medium"
                        >Issuance notes (optional)</label
                    >
                    <Input name="notes" placeholder="Optional notes" />
                    <InputError :message="errors.notes" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="issue-requisition-button"
                    data-testid="issue-requisition-button"
                    >Issue</Button
                >
            </Form>
        </div>
    </div>
</template>
