<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as requisitionTemplatesDestroy,
    duplicate as requisitionTemplatesDuplicate,
    store as requisitionTemplatesStore,
    update as requisitionTemplatesUpdate,
} from '@/routes/inventory/requisition-templates';
import {
    bulkApprove as requisitionsBulkApprove,
    bulkIssue as requisitionsBulkIssue,
    destroy as requisitionsDestroy,
    index as requisitionsIndex,
    show as requisitionsShow,
    store as requisitionsStore,
} from '@/routes/inventory/requisitions';

type ReqRow = {
    id: number;
    status: string;
    created_at: string | null;
    requester: { id: number; name: string | null; email: string | null };
    requester_position: { title: string; department: string | null } | null;
    can_delete: boolean;
};

type TemplateLine = {
    sku: string;
    name: string | null;
    qty_requested: number;
    availability: {
        available: boolean;
        message: string | null;
    };
};

type TemplateSummary = {
    id: number;
    name: string;
    notes: string | null;
    updated_at: string | null;
    line_count: number;
    lines: TemplateLine[];
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PaginationLink[] };

type DraftLine = {
    key: string;
    sku: string;
    qty_requested: string;
    name: string | null;
    availabilityMessage: string | null;
};

const props = defineProps<{
    requisitions: Paginated<ReqRow>;
    templates: TemplateSummary[];
    exportUrls: { csv: string; pdf: string };
    can: { bulkApprove: boolean; bulkIssue: boolean; manageTemplates: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: requisitionsIndex() },
            { title: 'Requisitions', href: requisitionsIndex() },
        ],
    },
});

function makeDraftLine(line?: Partial<TemplateLine>): DraftLine {
    return {
        key:
            typeof crypto !== 'undefined' && 'randomUUID' in crypto
                ? crypto.randomUUID()
                : `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        sku: line?.sku ?? '',
        qty_requested: line?.qty_requested ? String(line.qty_requested) : '1',
        name: line?.name ?? null,
        availabilityMessage: line?.availability?.message ?? null,
    };
}

function buildPayloadLines(
    lines: DraftLine[],
): Array<{ sku: string; qty_requested: number }> {
    return lines
        .filter(
            (line) =>
                line.sku.trim() !== '' || line.qty_requested.trim() !== '',
        )
        .map((line) => ({
            sku: line.sku.trim(),
            qty_requested: Number.parseInt(line.qty_requested, 10) || 0,
        }));
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function firstTemplateNameSuggestion(): string {
    const firstLine = requisitionForm.lines[0];

    return firstLine?.name ?? firstLine?.sku ?? '';
}

function currentLineError(
    index: number,
    field: 'sku' | 'qty_requested',
): string | undefined {
    const key = `lines.${index}.${field}` as const;

    return requisitionForm.errors[key] || saveTemplateForm.errors[key];
}

function editorLineError(
    index: number,
    field: 'sku' | 'qty_requested',
): string | undefined {
    return templateEditorForm.errors[`lines.${index}.${field}` as const];
}

function clearLineAvailability(line: DraftLine): void {
    line.availabilityMessage = null;
}

const requisitionForm = useForm<{
    notes: string;
    lines: DraftLine[];
}>({
    notes: '',
    lines: [makeDraftLine()],
});

const saveTemplateForm = useForm<{
    name: string;
    notes: string;
    lines: DraftLine[];
}>({
    name: '',
    notes: '',
    lines: [],
});

const templateEditorForm = useForm<{
    name: string;
    notes: string;
    lines: DraftLine[];
}>({
    name: '',
    notes: '',
    lines: [makeDraftLine()],
});

const saveTemplateDialogOpen = ref(false);
const templateEditorDialogOpen = ref(false);
const templateDeleteDialogOpen = ref(false);
const editingTemplateId = ref<number | null>(null);
const templatePendingDeletion = ref<TemplateSummary | null>(null);
const selectedRequisition = ref<ReqRow | null>(null);
const deleteDialogOpen = ref(false);
const deleteReason = ref('');
const deleteReasonCustom = ref('');

const deletionReasons = [
    { value: 'No longer needed', label: 'No longer needed' },
    { value: 'Request cancelled', label: 'Request cancelled' },
    { value: 'Data entry error', label: 'Data entry error' },
    { value: 'Duplicate request', label: 'Duplicate request' },
    { value: 'Other', label: 'Other (please specify)' },
];

const isOtherReason = computed(() => deleteReason.value === 'Other');
const canConfirmDelete = computed(() => {
    if (!deleteReason.value) {
        return false;
    }

    if (deleteReason.value === 'Other' && !deleteReasonCustom.value.trim()) {
        return false;
    }

    return true;
});

const hasIncompleteLines = computed(() =>
    requisitionForm.lines.some(
        (line) =>
            line.sku.trim() === '' ||
            line.qty_requested.trim() === '' ||
            Number.parseInt(line.qty_requested, 10) < 1,
    ),
);

const canSubmitRequisition = computed(
    () => requisitionForm.lines.length > 0 && !hasIncompleteLines.value,
);

const canSaveTemplate = computed(
    () =>
        props.can.manageTemplates &&
        requisitionForm.lines.length > 0 &&
        !hasIncompleteLines.value,
);

function addRequestLine(): void {
    requisitionForm.lines.push(makeDraftLine());
}

function removeRequestLine(index: number): void {
    if (requisitionForm.lines.length === 1) {
        requisitionForm.lines = [makeDraftLine()];
        requisitionForm.clearErrors();

        return;
    }

    requisitionForm.lines.splice(index, 1);
}

function openSaveTemplateDialog(): void {
    saveTemplateForm.reset();
    saveTemplateForm.clearErrors();
    saveTemplateForm.name = firstTemplateNameSuggestion();
    saveTemplateDialogOpen.value = true;
}

function saveCurrentTemplate(): void {
    saveTemplateForm.clearErrors();

    saveTemplateForm
        .transform(() => ({
            name: saveTemplateForm.name,
            notes: requisitionForm.notes || '',
            lines: buildPayloadLines(requisitionForm.lines),
        }))
        .post(requisitionTemplatesStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                saveTemplateDialogOpen.value = false;
                saveTemplateForm.reset();
            },
        });
}

function applyTemplate(template: TemplateSummary): void {
    requisitionForm.clearErrors();
    saveTemplateForm.clearErrors();
    requisitionForm.notes = template.notes ?? '';
    requisitionForm.lines = template.lines.map((line) => makeDraftLine(line));

    window.setTimeout(() => {
        document
            .querySelector('[data-shortcut="new"]')
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 0);
}

function openTemplateEditor(template: TemplateSummary): void {
    editingTemplateId.value = template.id;
    templateEditorForm.clearErrors();
    templateEditorForm.name = template.name;
    templateEditorForm.notes = template.notes ?? '';
    templateEditorForm.lines = template.lines.map((line) =>
        makeDraftLine(line),
    );
    templateEditorDialogOpen.value = true;
}

function addTemplateEditorLine(): void {
    templateEditorForm.lines.push(makeDraftLine());
}

function removeTemplateEditorLine(index: number): void {
    if (templateEditorForm.lines.length === 1) {
        templateEditorForm.lines = [makeDraftLine()];

        return;
    }

    templateEditorForm.lines.splice(index, 1);
}

function updateTemplate(): void {
    if (!editingTemplateId.value) {
        return;
    }

    templateEditorForm
        .transform((data) => ({
            name: data.name,
            notes: data.notes || '',
            lines: buildPayloadLines(data.lines),
        }))
        .put(requisitionTemplatesUpdate(editingTemplateId.value).url, {
            preserveScroll: true,
            onSuccess: () => {
                templateEditorDialogOpen.value = false;
                editingTemplateId.value = null;
                templateEditorForm.reset();
            },
        });
}

function duplicateTemplate(template: TemplateSummary): void {
    router.post(
        requisitionTemplatesDuplicate(template.id).url,
        {},
        {
            preserveScroll: true,
        },
    );
}

function openTemplateDeleteDialog(template: TemplateSummary): void {
    templatePendingDeletion.value = template;
    templateDeleteDialogOpen.value = true;
}

function confirmTemplateDelete(): void {
    if (!templatePendingDeletion.value) {
        return;
    }

    router.delete(
        requisitionTemplatesDestroy(templatePendingDeletion.value.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                templateDeleteDialogOpen.value = false;
                templatePendingDeletion.value = null;
            },
        },
    );
}

function submitRequisition(): void {
    requisitionForm
        .transform((data) => ({
            notes: data.notes || '',
            lines: buildPayloadLines(data.lines),
        }))
        .post(requisitionsStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                requisitionForm.reset();
                requisitionForm.lines = [makeDraftLine()];
            },
        });
}

function getDeletionReason(): string {
    if (deleteReason.value === 'Other') {
        return deleteReasonCustom.value.trim();
    }

    return deleteReason.value;
}

function openDeleteDialog(requisition: ReqRow): void {
    selectedRequisition.value = requisition;
    deleteReason.value = '';
    deleteReasonCustom.value = '';
    deleteDialogOpen.value = true;
}

function confirmDelete(): void {
    if (!selectedRequisition.value || !canConfirmDelete.value) {
        return;
    }

    router.delete(requisitionsDestroy(selectedRequisition.value.id).url, {
        data: { deletion_reason: getDeletionReason() },
        onSuccess: () => {
            deleteDialogOpen.value = false;
            selectedRequisition.value = null;
            deleteReason.value = '';
            deleteReasonCustom.value = '';
        },
    });
}

const selectedIds = ref<Set<number>>(new Set());
const bulkActionDialogOpen = ref(false);
const pendingBulkAction = ref<'approve' | 'issue' | null>(null);

const allSelected = computed(
    () =>
        props.requisitions.data.length > 0 &&
        selectedIds.value.size === props.requisitions.data.length,
);
const someSelected = computed(
    () =>
        selectedIds.value.size > 0 &&
        selectedIds.value.size < props.requisitions.data.length,
);
const hasSelection = computed(() => selectedIds.value.size > 0);

function toggleSelectAll(): void {
    if (allSelected.value) {
        selectedIds.value.clear();
    } else {
        selectedIds.value = new Set(props.requisitions.data.map((r) => r.id));
    }
}

function toggleSelect(id: number): void {
    const next = new Set(selectedIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selectedIds.value = next;
}

function runBulkApprove(): void {
    pendingBulkAction.value = 'approve';
    bulkActionDialogOpen.value = true;
}

function runBulkIssue(): void {
    pendingBulkAction.value = 'issue';
    bulkActionDialogOpen.value = true;
}

function confirmBulkAction(): void {
    if (!pendingBulkAction.value) {
        return;
    }

    const endpoint =
        pendingBulkAction.value === 'approve'
            ? requisitionsBulkApprove().url
            : requisitionsBulkIssue().url;

    router.post(
        endpoint,
        {
            ids: Array.from(selectedIds.value),
        },
        {
            onSuccess: () => {
                selectedIds.value.clear();
                bulkActionDialogOpen.value = false;
                pendingBulkAction.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Requisitions" />

    <div
        class="flex flex-col gap-6 p-4 sm:p-6"
        data-testid="requisitions-index-page"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Requisitions"
                description="Submit, template, and track issuance requests."
            />

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <a :href="exportUrls.csv">Export CSV</a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="exportUrls.pdf">Export PDF</a>
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/inventory/requisitions/trash">Trash</Link>
                </Button>
            </div>
        </div>

        <div
            v-if="
                (props.can.bulkApprove || props.can.bulkIssue) && hasSelection
            "
            class="flex flex-wrap items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-4 py-2 text-sm"
        >
            <span class="font-medium text-primary"
                >{{ selectedIds.size }} selected</span
            >
            <div class="ml-auto flex flex-wrap gap-2">
                <Button
                    v-if="props.can.bulkApprove"
                    variant="outline"
                    size="sm"
                    class="h-7 rounded-lg text-xs"
                    @click="runBulkApprove"
                    >Approve</Button
                >
                <Button
                    v-if="props.can.bulkIssue"
                    variant="outline"
                    size="sm"
                    class="h-7 rounded-lg text-xs"
                    @click="runBulkIssue"
                    >Issue</Button
                >
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div
                class="rounded-xl border border-border/60 bg-card p-5 shadow-sm lg:col-span-2"
            >
                <div class="grid gap-3 md:hidden">
                    <div
                        v-for="r in requisitions.data"
                        :key="r.id"
                        class="rounded-xl border border-border/40 p-4 text-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    v-if="
                                        props.can.bulkApprove ||
                                        props.can.bulkIssue
                                    "
                                    :checked="selectedIds.has(r.id)"
                                    @update:checked="() => toggleSelect(r.id)"
                                    aria-label="Select requisition"
                                />
                                <span class="font-medium">#{{ r.id }}</span>
                            </div>
                            <div class="text-muted-foreground">
                                {{ r.status }}
                            </div>
                        </div>
                        <div class="mt-2 space-y-1 text-muted-foreground">
                            <div>
                                {{
                                    r.requester.name ?? r.requester.email ?? '—'
                                }}
                            </div>
                            <div v-if="r.requester_position">
                                {{ r.requester_position.title
                                }}{{
                                    r.requester_position.department
                                        ? `, ${r.requester_position.department}`
                                        : ''
                                }}
                            </div>
                            <div>{{ formatDateTime(r.created_at) }}</div>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <Button
                                variant="ghost"
                                as-child
                                data-testid="open-requisition-button"
                            >
                                <Link :href="requisitionsShow(r.id)">Open</Link>
                            </Button>
                            <Button
                                v-if="r.can_delete"
                                variant="ghost"
                                size="sm"
                                class="h-8 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                @click="openDeleteDialog(r)"
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead
                            class="text-left text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                        >
                            <tr class="border-b border-border/60">
                                <th
                                    v-if="
                                        props.can.bulkApprove ||
                                        props.can.bulkIssue
                                    "
                                    class="w-8 py-2 pr-2"
                                >
                                    <Checkbox
                                        :checked="allSelected"
                                        :indeterminate="someSelected"
                                        @update:checked="toggleSelectAll"
                                        aria-label="Select all requisitions"
                                    />
                                </th>
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
                                class="border-b border-border/40"
                            >
                                <td
                                    v-if="
                                        props.can.bulkApprove ||
                                        props.can.bulkIssue
                                    "
                                    class="py-2 pr-2"
                                >
                                    <Checkbox
                                        :checked="selectedIds.has(r.id)"
                                        @update:checked="
                                            () => toggleSelect(r.id)
                                        "
                                        aria-label="Select requisition"
                                    />
                                </td>
                                <td class="py-2 pr-3 font-medium">
                                    #{{ r.id }}
                                </td>
                                <td class="py-2 pr-3">{{ r.status }}</td>
                                <td class="py-2 pr-3">
                                    <div>
                                        {{
                                            r.requester.name ??
                                            r.requester.email ??
                                            '—'
                                        }}
                                    </div>
                                    <div
                                        v-if="r.requester_position"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ r.requester_position.title
                                        }}{{
                                            r.requester_position.department
                                                ? `, ${r.requester_position.department}`
                                                : ''
                                        }}
                                    </div>
                                </td>
                                <td class="py-2 pr-3 text-muted-foreground">
                                    {{ formatDateTime(r.created_at) }}
                                </td>
                                <td class="py-2 pr-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            variant="ghost"
                                            as-child
                                            data-testid="open-requisition-button"
                                        >
                                            <Link :href="requisitionsShow(r.id)"
                                                >Open</Link
                                            >
                                        </Button>
                                        <Button
                                            v-if="r.can_delete"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                            @click="openDeleteDialog(r)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6">
                <div
                    class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold tracking-tight">
                                Saved templates
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Reuse frequent requisitions without bypassing
                                stock checks or approvals.
                            </div>
                        </div>
                        <span
                            class="rounded-full bg-muted px-2.5 py-1 text-[11px] font-medium text-muted-foreground"
                        >
                            {{ templates.length }} saved
                        </span>
                    </div>

                    <div
                        v-if="templates.length === 0"
                        class="rounded-xl border border-dashed border-border/60 p-4 text-sm text-muted-foreground"
                    >
                        No requisition templates yet. Build a request below,
                        then save it as a reusable template.
                    </div>

                    <div v-else class="grid gap-3">
                        <div
                            v-for="template in templates"
                            :key="template.id"
                            class="rounded-xl border border-border/50 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">
                                        {{ template.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ template.line_count }} line<span
                                            v-if="template.line_count !== 1"
                                            >s</span
                                        >
                                        • Updated
                                        {{
                                            formatDateTime(template.updated_at)
                                        }}
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                                    :class="
                                        template.lines.some(
                                            (line) =>
                                                !line.availability.available,
                                        )
                                            ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                            : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                    "
                                >
                                    {{
                                        template.lines.some(
                                            (line) =>
                                                !line.availability.available,
                                        )
                                            ? 'Needs review'
                                            : 'Ready'
                                    }}
                                </span>
                            </div>

                            <div
                                v-if="template.notes"
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                {{ template.notes }}
                            </div>

                            <div class="mt-3 grid gap-2 text-sm">
                                <div
                                    v-for="(
                                        line, index
                                    ) in template.lines.slice(0, 3)"
                                    :key="`${template.id}-${line.sku}-${index}`"
                                    class="rounded-lg bg-muted/40 px-3 py-2"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <div class="font-medium">
                                                {{ line.name ?? line.sku }}
                                            </div>
                                            <div
                                                class="font-mono text-[11px] text-muted-foreground"
                                            >
                                                {{ line.sku }}
                                            </div>
                                        </div>
                                        <div
                                            class="text-xs font-medium text-muted-foreground"
                                        >
                                            Qty {{ line.qty_requested }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="line.availability.message"
                                        class="mt-1 text-xs text-amber-700 dark:text-amber-400"
                                    >
                                        {{ line.availability.message }}
                                    </div>
                                </div>
                                <div
                                    v-if="template.lines.length > 3"
                                    class="text-xs text-muted-foreground"
                                >
                                    +{{ template.lines.length - 3 }} more
                                    line<span
                                        v-if="template.lines.length - 3 !== 1"
                                        >s</span
                                    >
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    class="rounded-lg"
                                    @click="applyTemplate(template)"
                                    >Use template</Button
                                >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="rounded-lg"
                                    @click="openTemplateEditor(template)"
                                    >Edit</Button
                                >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="rounded-lg"
                                    @click="duplicateTemplate(template)"
                                    >Duplicate</Button
                                >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="rounded-lg text-rose-600 hover:text-rose-700"
                                    @click="openTemplateDeleteDialog(template)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-border/60 bg-card p-5 shadow-sm"
                >
                    <form
                        class="grid gap-4"
                        data-shortcut="new"
                        @submit.prevent="submitRequisition"
                    >
                        <Heading
                            variant="small"
                            title="New requisition"
                            description="Build a quick request or start from a saved template."
                        />

                        <div class="grid gap-3">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <Label class="text-sm font-medium"
                                    >Line items</Label
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="rounded-lg"
                                    @click="addRequestLine"
                                    >Add line</Button
                                >
                            </div>

                            <div
                                v-for="(line, index) in requisitionForm.lines"
                                :key="line.key"
                                class="rounded-xl border border-border/50 p-4"
                            >
                                <div class="grid gap-3">
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div
                                            class="text-xs font-semibold tracking-wider text-muted-foreground/70 uppercase"
                                        >
                                            Item {{ index + 1 }}
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 rounded-lg px-2 text-xs text-muted-foreground"
                                            :disabled="
                                                requisitionForm.lines.length ===
                                                1
                                            "
                                            @click="removeRequestLine(index)"
                                        >
                                            Remove
                                        </Button>
                                    </div>

                                    <div
                                        class="grid gap-3 sm:grid-cols-[1fr_116px]"
                                    >
                                        <div class="grid gap-2">
                                            <div
                                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <Label
                                                    :for="`line-sku-${line.key}`"
                                                    >SKU</Label
                                                >
                                                <QrScannerDialog
                                                    button-label="Scan SKU"
                                                    title="Scan requisition SKU"
                                                    description="Use the camera to capture a labeled product QR code, or type the SKU manually."
                                                    @scanned="
                                                        line.sku = $event;
                                                        clearLineAvailability(
                                                            line,
                                                        );
                                                    "
                                                />
                                            </div>
                                            <Input
                                                :id="`line-sku-${line.key}`"
                                                v-model="line.sku"
                                                :name="`lines[${index}][sku]`"
                                                data-testid="requisition-sku-input"
                                                placeholder="e.g. 4801234567890"
                                                required
                                                @input="
                                                    clearLineAvailability(line)
                                                "
                                            />
                                            <div
                                                v-if="line.name"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ line.name }}
                                            </div>
                                            <div
                                                v-if="line.availabilityMessage"
                                                class="text-xs text-amber-700 dark:text-amber-400"
                                            >
                                                {{ line.availabilityMessage }}
                                            </div>
                                            <InputError
                                                :message="
                                                    currentLineError(
                                                        index,
                                                        'sku',
                                                    )
                                                "
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label :for="`line-qty-${line.key}`"
                                                >Qty</Label
                                            >
                                            <Input
                                                :id="`line-qty-${line.key}`"
                                                v-model="line.qty_requested"
                                                :name="`lines[${index}][qty_requested]`"
                                                data-testid="requisition-qty-input"
                                                type="number"
                                                min="1"
                                                required
                                            />
                                            <InputError
                                                :message="
                                                    currentLineError(
                                                        index,
                                                        'qty_requested',
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="requisition-notes"
                                >Notes (optional)</Label
                            >
                            <Input
                                id="requisition-notes"
                                v-model="requisitionForm.notes"
                                name="notes"
                                data-testid="requisition-notes-input"
                                placeholder="Reason, destination, or context"
                            />
                            <InputError
                                :message="
                                    requisitionForm.errors.notes ||
                                    saveTemplateForm.errors.notes
                                "
                            />
                        </div>

                        <div
                            v-if="hasIncompleteLines"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300"
                        >
                            Finish or remove incomplete lines before submitting
                            or saving this request as a template.
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Button
                                v-if="props.can.manageTemplates"
                                type="button"
                                variant="outline"
                                class="rounded-lg border-dashed"
                                :disabled="
                                    !canSaveTemplate ||
                                    saveTemplateForm.processing
                                "
                                @click="openSaveTemplateDialog"
                            >
                                Save as template
                            </Button>

                            <div class="ml-auto flex items-center gap-2">
                                <Button
                                    type="submit"
                                    :disabled="
                                        requisitionForm.processing ||
                                        !canSubmitRequisition
                                    "
                                    data-test="submit-requisition-button"
                                    data-testid="submit-requisition-button"
                                >
                                    Submit requisition
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <Dialog v-model:open="saveTemplateDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Save requisition template</DialogTitle>
                    <DialogDescription>
                        Save the current requisition lines and notes as a
                        reusable template.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="template-name">Template name</Label>
                        <Input
                            id="template-name"
                            v-model="saveTemplateForm.name"
                            placeholder="e.g. Monthly office supplies"
                        />
                        <InputError :message="saveTemplateForm.errors.name" />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        :disabled="
                            saveTemplateForm.processing ||
                            saveTemplateForm.name.trim() === ''
                        "
                        @click="saveCurrentTemplate"
                    >
                        Save template
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="templateEditorDialogOpen">
            <DialogContent class="max-w-3xl">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Edit template</DialogTitle>
                    <DialogDescription>
                        Update reusable lines, quantities, and notes without
                        changing the current requisition workflow.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="edit-template-name">Template name</Label>
                        <Input
                            id="edit-template-name"
                            v-model="templateEditorForm.name"
                        />
                        <InputError :message="templateEditorForm.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-template-notes">Notes</Label>
                        <Input
                            id="edit-template-notes"
                            v-model="templateEditorForm.notes"
                            placeholder="Optional context for this template"
                        />
                        <InputError
                            :message="templateEditorForm.errors.notes"
                        />
                    </div>

                    <div class="grid gap-3">
                        <div class="flex items-center justify-between gap-3">
                            <Label>Template lines</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="rounded-lg"
                                @click="addTemplateEditorLine"
                                >Add line</Button
                            >
                        </div>

                        <div
                            v-for="(line, index) in templateEditorForm.lines"
                            :key="line.key"
                            class="rounded-xl border border-border/50 p-4"
                        >
                            <div
                                class="grid gap-3 sm:grid-cols-[1fr_116px_auto]"
                            >
                                <div class="grid gap-2">
                                    <Label
                                        :for="`template-line-sku-${line.key}`"
                                        >SKU</Label
                                    >
                                    <Input
                                        :id="`template-line-sku-${line.key}`"
                                        v-model="line.sku"
                                        placeholder="e.g. 4801234567890"
                                        @input="clearLineAvailability(line)"
                                    />
                                    <div
                                        v-if="line.availabilityMessage"
                                        class="text-xs text-amber-700 dark:text-amber-400"
                                    >
                                        {{ line.availabilityMessage }}
                                    </div>
                                    <InputError
                                        :message="editorLineError(index, 'sku')"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        :for="`template-line-qty-${line.key}`"
                                        >Qty</Label
                                    >
                                    <Input
                                        :id="`template-line-qty-${line.key}`"
                                        v-model="line.qty_requested"
                                        type="number"
                                        min="1"
                                    />
                                    <InputError
                                        :message="
                                            editorLineError(
                                                index,
                                                'qty_requested',
                                            )
                                        "
                                    />
                                </div>
                                <div class="flex items-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-9 rounded-lg text-muted-foreground"
                                        :disabled="
                                            templateEditorForm.lines.length ===
                                            1
                                        "
                                        @click="removeTemplateEditorLine(index)"
                                    >
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        :disabled="
                            templateEditorForm.processing ||
                            templateEditorForm.name.trim() === ''
                        "
                        @click="updateTemplate"
                    >
                        Update template
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="templateDeleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete template?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete
                        <strong>{{ templatePendingDeletion?.name }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button variant="destructive" @click="confirmTemplateDelete"
                        >Delete template</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete requisition?</DialogTitle>
                    <DialogDescription>
                        This will move requisition
                        <strong>#{{ selectedRequisition?.id }}</strong> to the
                        trash. You can restore it later if needed.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="delete-reason"
                            >Reason for deletion
                            <span class="text-rose-500">*</span></Label
                        >
                        <Select v-model="deleteReason">
                            <SelectTrigger id="delete-reason">
                                <SelectValue placeholder="Select a reason..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="reason in deletionReasons"
                                    :key="reason.value"
                                    :value="reason.value"
                                >
                                    {{ reason.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-if="isOtherReason" class="grid gap-2">
                        <Label for="delete-reason-custom"
                            >Please specify
                            <span class="text-rose-500">*</span></Label
                        >
                        <textarea
                            id="delete-reason-custom"
                            v-model="deleteReasonCustom"
                            placeholder="Enter your reason..."
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="!canConfirmDelete"
                        @click="confirmDelete"
                        >Delete</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bulkActionDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{
                            pendingBulkAction === 'approve'
                                ? 'Approve selected requisitions?'
                                : 'Issue selected requisitions?'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        This will process
                        <strong
                            >{{ selectedIds.size }} selected
                            requisition(s)</strong
                        >
                        while preserving your current filters and pagination.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button @click="confirmBulkAction">
                        {{
                            pendingBulkAction === 'approve'
                                ? 'Confirm approve'
                                : 'Confirm issue'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
