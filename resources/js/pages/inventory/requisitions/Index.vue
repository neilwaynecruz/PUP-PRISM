<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ClipboardList,
    Minus,
    MoreHorizontal,
    Package,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProductSelectDialog from '@/components/inventory/ProductSelectDialog.vue';
import type { ProductLookupItem } from '@/components/inventory/ProductSelectDialog.vue';
import QrScannerDialog from '@/components/inventory/QrScannerDialog.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { shouldApplyDebouncedVisit } from '@/lib/inertiaNavigation';
import { lookup as productLookupRoute } from '@/routes/inventory/products';
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
    lines: Array<{
        id: number;
        sku: string | null;
        name: string | null;
        qty_requested: number;
        qty_issued: number;
    }>;
    totals: {
        line_count: number;
        qty_requested: number;
        qty_issued: number;
    } | null;
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
type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
};

type DraftLine = {
    key: string;
    sku: string;
    qty_requested: string;
    name: string | null;
    category?: string | null;
    variant?: string | null;
    unit?: string | null;
    type?: string | null;
    on_hand_qty?: number | null;
    availabilityMessage: string | null;
    searchQuery?: string;
    suggestions?: ProductLookupItem[];
    showSuggestions?: boolean;
    isSearching?: boolean;
};

const props = defineProps<{
    filters: {
        search: string;
        status: string | null;
        date_from: string | null;
        date_to: string | null;
        requester_id: number | null;
    };
    statusOptions: Array<{ value: string; label: string }>;
    requisitions: Paginated<ReqRow>;
    statusCounts: Record<string, number>;
    templates: TemplateSummary[];
    exportUrls: { csv: string; pdf: string };
    can: { bulkApprove: boolean; bulkIssue: boolean; manageTemplates: boolean };
}>();

defineOptions({
    name: 'RequisitionIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: requisitionsIndex() },
            { title: 'Requisitions', href: requisitionsIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const listQuery = computed(() => ({
    search: search.value || undefined,
    status: status.value === 'all' ? undefined : status.value,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
}));

let listFilterTimer: number | undefined;
let isComponentActive = true;

watch([search, status, dateFrom, dateTo], () => {
    window.clearTimeout(listFilterTimer);
    listFilterTimer = window.setTimeout(() => {
        if (
            !isComponentActive ||
            !shouldApplyDebouncedVisit('/inventory/requisitions')
        ) {
            return;
        }

        router.get(requisitionsIndex().url, listQuery.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
});

onBeforeUnmount(() => {
    isComponentActive = false;
    window.clearTimeout(listFilterTimer);
    Object.values(lineSearchTimers).forEach((timer) => clearTimeout(timer));
    Object.values(lineSearchControllers).forEach((controller) =>
        controller.abort(),
    );
});

let nextDraftKey = 0;

function makeDraftLine(line?: Partial<TemplateLine>): DraftLine {
    nextDraftKey += 1;

    return {
        key: `draft-line-${nextDraftKey}`,
        sku: line?.sku ?? '',
        qty_requested: line?.qty_requested ? String(line.qty_requested) : '1',
        name: line?.name ?? null,
        category: null,
        type: null,
        on_hand_qty: null,
        availabilityMessage: line?.availability?.message ?? null,
        searchQuery: line?.sku ?? '',
        suggestions: [],
        showSuggestions: false,
        isSearching: false,
    };
}

const catalogDialogOpen = ref(false);
const targetLineIndex = ref<number | null>(null);
const lineSearchTimers: Record<string, ReturnType<typeof setTimeout>> = {};
const lineSearchControllers: Record<string, AbortController> = {};

function updateLineAvailabilityWarning(line: DraftLine): void {
    const qty = parseInt(line.qty_requested, 10) || 0;

    if (line.on_hand_qty !== null && line.on_hand_qty !== undefined) {
        if (line.on_hand_qty <= 0) {
            line.availabilityMessage =
                'Item currently has 0 available on-hand stock. Request will be queued for replenishment.';
        } else if (qty > line.on_hand_qty) {
            line.availabilityMessage = `Requested quantity (${qty}) exceeds available stock (${line.on_hand_qty}).`;
        } else {
            line.availabilityMessage = null;
        }
    }
}

function applyProductToLine(
    line: DraftLine,
    product: ProductLookupItem,
    index: number,
    quantity?: number,
): void {
    // Prevent accidental duplicate line items by consolidating into existing line if present
    const existingIndex = requisitionForm.lines.findIndex(
        (l, i) =>
            i !== index &&
            l.sku.trim().toLowerCase() === product.sku.trim().toLowerCase(),
    );

    if (existingIndex !== -1) {
        const existing = requisitionForm.lines[existingIndex];
        const addQty = quantity ?? (parseInt(line.qty_requested, 10) || 1);
        const currentQty = parseInt(existing.qty_requested, 10) || 0;
        existing.qty_requested = String(currentQty + addQty);
        existing.name = product.name;
        existing.category = product.category;
        existing.variant = product.variant;
        existing.unit = product.unit;
        existing.on_hand_qty = product.on_hand_qty;
        updateLineAvailabilityWarning(existing);

        if (requisitionForm.lines.length > 1) {
            requisitionForm.lines.splice(index, 1);
        } else {
            line.sku = '';
            line.name = null;
            line.searchQuery = '';
            line.suggestions = [];
            line.showSuggestions = false;
            line.availabilityMessage = null;
        }

        return;
    }

    line.sku = product.sku;
    line.name = product.name;
    line.category = product.category;
    line.variant = product.variant;
    line.unit = product.unit;
    line.type = product.type;
    line.on_hand_qty = product.on_hand_qty;
    line.searchQuery = product.sku;
    line.showSuggestions = false;
    line.suggestions = [];

    if (quantity !== undefined) {
        line.qty_requested = String(quantity);
    }

    updateLineAvailabilityWarning(line);
}

function onProductSelectedFromCatalog(
    product: ProductLookupItem,
    qty: number,
): void {
    if (
        targetLineIndex.value !== null &&
        requisitionForm.lines[targetLineIndex.value]
    ) {
        applyProductToLine(
            requisitionForm.lines[targetLineIndex.value],
            product,
            targetLineIndex.value,
            qty,
        );
        targetLineIndex.value = null;
        catalogDialogOpen.value = false;

        return;
    }

    const existingIndex = requisitionForm.lines.findIndex(
        (l) => l.sku.trim().toLowerCase() === product.sku.trim().toLowerCase(),
    );

    if (existingIndex !== -1) {
        const existing = requisitionForm.lines[existingIndex];
        const currentQty = parseInt(existing.qty_requested, 10) || 0;
        existing.qty_requested = String(currentQty + qty);
        existing.name = product.name;
        existing.category = product.category;
        existing.variant = product.variant;
        existing.unit = product.unit;
        existing.on_hand_qty = product.on_hand_qty;
        updateLineAvailabilityWarning(existing);

        return;
    }

    const emptyLineIndex = requisitionForm.lines.findIndex(
        (l) => !l.sku.trim(),
    );

    if (emptyLineIndex !== -1) {
        applyProductToLine(
            requisitionForm.lines[emptyLineIndex],
            product,
            emptyLineIndex,
            qty,
        );
    } else {
        const newLine = makeDraftLine();
        requisitionForm.lines.push(newLine);
        applyProductToLine(
            newLine,
            product,
            requisitionForm.lines.length - 1,
            qty,
        );
    }
}

async function onSkuScanned(
    line: DraftLine,
    scannedSku: string,
    index: number,
): Promise<void> {
    line.sku = scannedSku;
    line.searchQuery = scannedSku;
    clearLineAvailability(line);

    try {
        const url = `${productLookupRoute.url()}?sku=${encodeURIComponent(scannedSku)}&type=consumable`;
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
        });

        if (res.ok) {
            const data = await res.json();

            if (data.products && data.products.length > 0) {
                applyProductToLine(line, data.products[0], index);

                return;
            }
        }
    } catch {
        line.availabilityMessage =
            'The scanned SKU could not be checked. You can try again or search by product name.';

        return;
    }

    line.availabilityMessage =
        "We couldn't find an active consumable with that SKU. Try searching by product name.";
}

function onLineSearchInput(line: DraftLine): void {
    clearLineAvailability(line);
    const query = (line.searchQuery ?? '').trim();
    line.sku = query;

    if (!query || query.length < 2) {
        line.suggestions = [];
        line.showSuggestions = false;

        return;
    }

    clearTimeout(lineSearchTimers[line.key]);
    lineSearchTimers[line.key] = setTimeout(async () => {
        lineSearchControllers[line.key]?.abort();
        const requestController = new AbortController();
        lineSearchControllers[line.key] = requestController;
        line.isSearching = true;

        try {
            const url = `${productLookupRoute.url()}?query=${encodeURIComponent(query)}&type=consumable&limit=8`;
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: requestController.signal,
            });

            if (res.ok) {
                const data = await res.json();
                line.suggestions = data.products ?? [];
                line.showSuggestions = (line.suggestions?.length ?? 0) > 0;
                line.availabilityMessage = line.showSuggestions
                    ? null
                    : 'No active consumables match this search. Try another product name or SKU.';
            } else {
                line.availabilityMessage =
                    'The product search could not be completed. Please try again.';
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            line.suggestions = [];
            line.showSuggestions = false;
            line.availabilityMessage =
                'The product search could not be completed. Check your connection and try again.';
        } finally {
            if (lineSearchControllers[line.key] === requestController) {
                line.isSearching = false;
            }
        }
    }, 250);
}

function selectSuggestion(
    line: DraftLine,
    item: ProductLookupItem,
    index: number,
): void {
    applyProductToLine(line, item, index);
}

function hideSuggestionsWithDelay(line: DraftLine): void {
    setTimeout(() => {
        line.showSuggestions = false;
    }, 200);
}

function buildPayloadLines(
    lines: DraftLine[],
): Array<{ sku: string; qty_requested: number }> {
    return lines
        .filter(
            (line) =>
                String(line.sku ?? '').trim() !== '' ||
                String(line.qty_requested ?? '').trim() !== '',
        )
        .map((line) => ({
            sku: String(line.sku ?? '').trim(),
            qty_requested:
                Number.parseInt(String(line.qty_requested ?? '0'), 10) || 0,
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

function formatStatusLabel(statusValue: string): string {
    return statusValue
        .replace(/_/g, ' ')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
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
    submission_token: string;
    notes: string;
    lines: DraftLine[];
}>({
    submission_token: crypto.randomUUID(),
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
            String(line.sku ?? '').trim() === '' ||
            String(line.qty_requested ?? '').trim() === '' ||
            Number.parseInt(String(line.qty_requested ?? '0'), 10) < 1,
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
            submission_token: data.submission_token,
            notes: data.notes || '',
            lines: buildPayloadLines(data.lines),
        }))
        .post(requisitionsStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                requisitionForm.reset();
                requisitionForm.submission_token = crypto.randomUUID();
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

// ── Composer conveniences ──
const filledLines = computed(() =>
    requisitionForm.lines.filter(
        (line) => String(line.sku ?? '').trim() !== '',
    ),
);

const composerTotals = computed(() => ({
    items: filledLines.value.length,
    units: filledLines.value.reduce(
        (sum, line) => sum + (Number.parseInt(line.qty_requested, 10) || 0),
        0,
    ),
    shortages: filledLines.value.filter(
        (line) =>
            line.on_hand_qty !== null &&
            line.on_hand_qty !== undefined &&
            (Number.parseInt(line.qty_requested, 10) || 0) > line.on_hand_qty,
    ).length,
}));

function stepQuantity(line: DraftLine, delta: number): void {
    const next = Math.max(
        1,
        Math.min(
            1000000,
            (Number.parseInt(line.qty_requested, 10) || 0) + delta,
        ),
    );
    line.qty_requested = String(next);
    updateLineAvailabilityWarning(line);
}

function targetLineForNewProduct(): number {
    const emptyIndex = requisitionForm.lines.findIndex(
        (line) => !String(line.sku ?? '').trim(),
    );

    if (emptyIndex !== -1) {
        return emptyIndex;
    }

    requisitionForm.lines.push(makeDraftLine());

    return requisitionForm.lines.length - 1;
}

function onToolbarScan(sku: string): void {
    const index = targetLineForNewProduct();

    void onSkuScanned(requisitionForm.lines[index], sku, index);
}

function openCatalog(): void {
    targetLineIndex.value = null;
    catalogDialogOpen.value = true;
}

function clearComposer(): void {
    requisitionForm.lines = [makeDraftLine()];
    requisitionForm.notes = '';
    requisitionForm.clearErrors();
}

// ── List presentation ──
const statusTabs = computed(() => {
    const counts = props.statusCounts ?? {};
    const total = Object.values(counts).reduce((sum, count) => sum + count, 0);

    return [
        { value: 'all', label: 'All', count: total },
        ...props.statusOptions
            .filter(
                (option) =>
                    option.value !== 'Draft' || (counts[option.value] ?? 0) > 0,
            )
            .map((option) => ({
                value: option.value,
                label: formatStatusLabel(option.label),
                count: counts[option.value] ?? 0,
            })),
    ];
});

function statusBadgeClass(value: string): string {
    switch (value) {
        case 'Submitted':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300';
        case 'Approved':
            return 'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300';
        case 'PartiallyIssued':
        case 'Backordered':
            return 'border-violet-500/25 bg-violet-500/10 text-violet-700 dark:text-violet-300';
        case 'Issued':
        case 'Closed':
            return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'Rejected':
            return 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-300';
        default:
            return 'border-border bg-muted/60 text-muted-foreground';
    }
}

function itemsSummary(row: ReqRow): string {
    if (!row.lines?.length) {
        return 'No items';
    }

    const names = row.lines
        .slice(0, 2)
        .map(
            (line) =>
                `${line.name ?? line.sku ?? 'Item'} ×${line.qty_requested}`,
        );
    const remaining = row.lines.length - names.length;

    return remaining > 0
        ? `${names.join(', ')} +${remaining} more`
        : names.join(', ');
}

function issuedPercent(row: ReqRow): number {
    if (!row.totals || row.totals.qty_requested === 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((row.totals.qty_issued / row.totals.qty_requested) * 100),
    );
}

const page = usePage();
const currentUserId = computed<number | null>(() => {
    const auth = page.props.auth as { user?: { id: number } } | undefined;

    return auth?.user?.id ?? null;
});

function canApproveRow(r: ReqRow): boolean {
    if (!props.can.bulkApprove) {
        return false;
    }

    if (r.status !== 'Submitted') {
        return false;
    }

    if (
        currentUserId.value !== null &&
        r.requester.id === currentUserId.value
    ) {
        return false;
    }

    return true;
}

function canIssueRow(r: ReqRow): boolean {
    if (!props.can.bulkIssue) {
        return false;
    }

    return ['Approved', 'PartiallyIssued', 'Backordered'].includes(r.status);
}

function isRowActionable(r: ReqRow): boolean {
    return canApproveRow(r) || canIssueRow(r);
}

function getRowDisabledReason(r: ReqRow): string | null {
    if (r.status === 'Issued') {
        return 'Requisition has already been fully issued and completed.';
    }

    if (r.status === 'Closed') {
        return 'Requisition is closed and cannot be modified.';
    }

    if (r.status === 'Rejected') {
        return 'Requisition was rejected.';
    }

    if (r.status === 'Draft') {
        return 'Draft requisitions must be submitted before they can be approved.';
    }

    if (r.status === 'Submitted') {
        if (!props.can.bulkApprove) {
            return 'You do not have permission to approve requisitions.';
        }

        if (
            currentUserId.value !== null &&
            r.requester.id === currentUserId.value
        ) {
            return 'You cannot approve your own submitted requisition.';
        }
    }

    if (
        ['Approved', 'PartiallyIssued', 'Backordered'].includes(r.status) &&
        !props.can.bulkIssue
    ) {
        return 'You do not have permission to issue inventory.';
    }

    return null;
}

const actionableRequisitions = computed(() =>
    props.requisitions.data.filter((r) => isRowActionable(r)),
);

const selectedApprovableRows = computed(() =>
    props.requisitions.data.filter(
        (r) => selectedIds.value.has(r.id) && canApproveRow(r),
    ),
);

const selectedIssuableRows = computed(() =>
    props.requisitions.data.filter(
        (r) => selectedIds.value.has(r.id) && canIssueRow(r),
    ),
);

const bulkActionDialogOpen = ref(false);
const pendingBulkAction = ref<'approve' | 'issue' | null>(null);
const bulkActionProcessing = ref(false);

const {
    selectedIds,
    allSelected,
    someSelected,
    hasSelection,
    toggleSelectAll,
    toggleSelect,
    clearSelection,
} = useBulkSelection(() => actionableRequisitions.value);

watch(
    () => props.requisitions.data,
    () => {
        clearSelection();
    },
);

function runBulkApprove(): void {
    if (selectedApprovableRows.value.length === 0) {
        return;
    }

    pendingBulkAction.value = 'approve';
    bulkActionDialogOpen.value = true;
}

function runBulkIssue(): void {
    if (selectedIssuableRows.value.length === 0) {
        return;
    }

    pendingBulkAction.value = 'issue';
    bulkActionDialogOpen.value = true;
}

function confirmBulkAction(): void {
    if (!pendingBulkAction.value || bulkActionProcessing.value) {
        return;
    }

    const targetIds =
        pendingBulkAction.value === 'approve'
            ? selectedApprovableRows.value.map((r) => r.id)
            : selectedIssuableRows.value.map((r) => r.id);

    if (targetIds.length === 0) {
        bulkActionDialogOpen.value = false;
        pendingBulkAction.value = null;

        return;
    }

    const endpoint =
        pendingBulkAction.value === 'approve'
            ? requisitionsBulkApprove().url
            : requisitionsBulkIssue().url;

    bulkActionProcessing.value = true;

    router.post(
        endpoint,
        {
            ids: targetIds,
        },
        {
            onSuccess: () => {
                clearSelection();
                bulkActionDialogOpen.value = false;
                pendingBulkAction.value = null;
            },
            onFinish: () => {
                bulkActionProcessing.value = false;
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
                description="Request inventory items, follow approval progress, and track how many units have been issued."
            />

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" class="rounded-lg" as-child>
                    <a :href="exportUrls.csv">Download CSV</a>
                </Button>
                <Button variant="outline" size="sm" class="rounded-lg" as-child>
                    <a :href="exportUrls.pdf">Download PDF</a>
                </Button>
                <Button variant="outline" size="sm" class="rounded-lg" as-child>
                    <Link href="/inventory/requisitions/trash">Trash</Link>
                </Button>
            </div>
        </div>

        <div
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]"
        >
            <section
                class="rounded-xl border border-border/60 bg-card shadow-sm"
                aria-labelledby="new-requisition-heading"
            >
                <form
                    class="grid gap-0"
                    data-shortcut="new"
                    @submit.prevent="submitRequisition"
                >
                    <header
                        class="flex flex-col gap-3 border-b border-border/50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                id="new-requisition-heading"
                                class="text-sm font-semibold"
                            >
                                New requisition
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Add products by typing a name or SKU, scanning a
                                label, or browsing the catalog. Stock is checked
                                again when items are issued.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 rounded-lg text-xs"
                                @click="openCatalog"
                            >
                                <Package class="h-3.5 w-3.5" />
                                <span>Browse Catalog</span>
                            </Button>
                            <QrScannerDialog
                                button-label="Scan"
                                button-size="sm"
                                title="Scan product label"
                                description="Scan a product QR label to add it to this requisition, or type the SKU in a line below."
                                @scanned="onToolbarScan"
                            />
                        </div>
                    </header>

                    <div class="px-5 py-4">
                        <div
                            class="hidden grid-cols-[1.5rem_minmax(0,1fr)_9rem_2rem] gap-3 px-1 pb-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase md:grid"
                            aria-hidden="true"
                        >
                            <span>#</span>
                            <span>Product</span>
                            <span>Quantity</span>
                            <span />
                        </div>

                        <ol class="grid gap-2" aria-label="Requested items">
                            <li
                                v-for="(line, index) in requisitionForm.lines"
                                :key="line.key"
                                class="grid gap-2 rounded-lg border border-border/50 p-3 md:grid-cols-[1.5rem_minmax(0,1fr)_9rem_2rem] md:items-start md:gap-3 md:border-0 md:border-b md:border-border/40 md:p-1 md:pb-3 md:last:border-b-0"
                            >
                                <span
                                    class="hidden pt-2.5 text-xs font-semibold text-muted-foreground tabular-nums md:block"
                                    >{{ index + 1 }}</span
                                >

                                <div class="relative grid min-w-0 gap-1.5">
                                    <Label
                                        :for="`line-sku-${line.key}`"
                                        class="text-xs font-medium md:sr-only"
                                    >
                                        Item {{ index + 1 }}: product name or
                                        SKU
                                    </Label>
                                    <div class="relative">
                                        <Search
                                            class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <Input
                                            :id="`line-sku-${line.key}`"
                                            v-model="line.searchQuery"
                                            :name="`lines[${index}][sku]`"
                                            data-testid="requisition-sku-input"
                                            autocomplete="off"
                                            placeholder="Type a product name or SKU"
                                            class="h-9 pl-8"
                                            required
                                            role="combobox"
                                            :aria-expanded="
                                                Boolean(
                                                    line.showSuggestions &&
                                                    line.suggestions?.length,
                                                )
                                            "
                                            :aria-controls="`line-suggestions-${line.key}`"
                                            @input="onLineSearchInput(line)"
                                            @focus="
                                                line.showSuggestions =
                                                    (line.suggestions?.length ??
                                                        0) > 0
                                            "
                                            @blur="
                                                hideSuggestionsWithDelay(line)
                                            "
                                        />
                                        <span
                                            v-if="line.isSearching"
                                            class="absolute top-1/2 right-3 -translate-y-1/2 text-[11px] text-muted-foreground"
                                            >Searching…</span
                                        >

                                        <ul
                                            v-if="
                                                line.showSuggestions &&
                                                line.suggestions &&
                                                line.suggestions.length > 0
                                            "
                                            :id="`line-suggestions-${line.key}`"
                                            role="listbox"
                                            class="absolute top-full right-0 left-0 z-50 mt-1 max-h-64 overflow-y-auto rounded-xl border border-border/80 bg-popover p-1.5 shadow-lg"
                                        >
                                            <li
                                                v-for="suggestion in line.suggestions"
                                                :key="suggestion.id"
                                                role="option"
                                                class="flex cursor-pointer items-center justify-between gap-2 rounded-lg p-2 text-xs transition hover:bg-accent"
                                                @mousedown.prevent="
                                                    selectSuggestion(
                                                        line,
                                                        suggestion,
                                                        index,
                                                    )
                                                "
                                            >
                                                <div class="min-w-0 flex-1">
                                                    <div
                                                        class="truncate font-medium text-foreground"
                                                    >
                                                        {{ suggestion.name }}
                                                    </div>
                                                    <div
                                                        class="mt-0.5 flex items-center gap-2 text-[11px] text-muted-foreground"
                                                    >
                                                        <span
                                                            class="font-mono"
                                                            >{{
                                                                suggestion.sku
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="
                                                                suggestion.variant
                                                            "
                                                            >·
                                                            {{
                                                                suggestion.variant
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="
                                                                suggestion.category
                                                            "
                                                            >·
                                                            {{
                                                                suggestion.category
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>
                                                <span
                                                    class="shrink-0 rounded-md border px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :class="
                                                        suggestion.on_hand_qty >
                                                        0
                                                            ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                            : 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                                    "
                                                >
                                                    {{ suggestion.on_hand_qty }}
                                                    {{ suggestion.unit ?? '' }}
                                                    in stock
                                                </span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div
                                        v-if="line.name"
                                        class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs"
                                    >
                                        <span
                                            class="font-medium text-foreground"
                                            >{{ line.name }}</span
                                        >
                                        <Badge
                                            variant="outline"
                                            class="h-4 px-1 font-mono text-[10px]"
                                            >{{ line.sku }}</Badge
                                        >
                                        <span
                                            v-if="line.variant"
                                            class="text-muted-foreground"
                                            >{{ line.variant }}</span
                                        >
                                        <span
                                            v-if="line.category"
                                            class="text-muted-foreground"
                                            >{{ line.category }}</span
                                        >
                                        <span
                                            v-if="
                                                line.on_hand_qty !== null &&
                                                line.on_hand_qty !== undefined
                                            "
                                            class="font-medium"
                                            :class="
                                                line.on_hand_qty > 0
                                                    ? 'text-emerald-700 dark:text-emerald-400'
                                                    : 'text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            · {{ line.on_hand_qty }}
                                            {{ line.unit ?? '' }} available
                                        </span>
                                    </div>

                                    <p
                                        v-if="line.availabilityMessage"
                                        role="status"
                                        aria-live="polite"
                                        class="flex items-start gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-400"
                                    >
                                        <AlertTriangle
                                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                                        />
                                        <span>{{
                                            line.availabilityMessage
                                        }}</span>
                                    </p>
                                    <InputError
                                        :message="
                                            currentLineError(index, 'sku')
                                        "
                                    />
                                </div>

                                <div class="grid gap-1">
                                    <Label
                                        :for="`line-qty-${line.key}`"
                                        class="text-xs font-medium md:sr-only"
                                        >Quantity{{
                                            line.unit ? ` (${line.unit})` : ''
                                        }}</Label
                                    >
                                    <div
                                        class="flex h-9 items-stretch overflow-hidden rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/20"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-8 items-center justify-center text-muted-foreground hover:bg-muted disabled:opacity-40"
                                            :aria-label="`Decrease quantity for item ${index + 1}`"
                                            :disabled="
                                                (Number.parseInt(
                                                    line.qty_requested,
                                                    10,
                                                ) || 0) <= 1
                                            "
                                            @click="stepQuantity(line, -1)"
                                        >
                                            <Minus class="h-3.5 w-3.5" />
                                        </button>
                                        <input
                                            :id="`line-qty-${line.key}`"
                                            v-model="line.qty_requested"
                                            :name="`lines[${index}][qty_requested]`"
                                            data-testid="requisition-qty-input"
                                            type="number"
                                            min="1"
                                            max="1000000"
                                            inputmode="numeric"
                                            required
                                            class="w-full min-w-0 border-x border-input bg-transparent text-center text-sm tabular-nums focus:outline-none [&::-webkit-inner-spin-button]:appearance-none"
                                            @input="
                                                updateLineAvailabilityWarning(
                                                    line,
                                                )
                                            "
                                        />
                                        <button
                                            type="button"
                                            class="flex w-8 items-center justify-center text-muted-foreground hover:bg-muted"
                                            :aria-label="`Increase quantity for item ${index + 1}`"
                                            @click="stepQuantity(line, 1)"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <InputError
                                        :message="
                                            currentLineError(
                                                index,
                                                'qty_requested',
                                            )
                                        "
                                    />
                                </div>

                                <div class="flex justify-end md:pt-0.5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 rounded-lg text-muted-foreground hover:bg-rose-500/10 hover:text-rose-600"
                                        :aria-label="`Remove item ${index + 1}`"
                                        @click="removeRequestLine(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </li>
                        </ol>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="mt-2 h-8 rounded-lg text-xs text-primary hover:bg-primary/5"
                            @click="addRequestLine"
                        >
                            <Plus class="mr-1 h-3.5 w-3.5" />
                            Add another item
                        </Button>

                        <div class="mt-4 grid gap-1.5">
                            <Label for="requisition-notes"
                                >Purpose or notes (optional)</Label
                            >
                            <Input
                                id="requisition-notes"
                                v-model="requisitionForm.notes"
                                name="notes"
                                data-testid="requisition-notes-input"
                                placeholder="e.g. Supplies for the Registrar's enrollment week"
                            />
                            <InputError
                                :message="
                                    requisitionForm.errors.notes ||
                                    saveTemplateForm.errors.notes
                                "
                            />
                        </div>
                    </div>

                    <footer
                        class="sticky bottom-0 z-10 flex flex-col gap-3 rounded-b-xl border-t border-border/50 bg-card/95 px-5 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div
                            class="text-xs text-muted-foreground"
                            aria-live="polite"
                        >
                            <span
                                class="font-semibold text-foreground tabular-nums"
                                >{{ composerTotals.items }}</span
                            >
                            {{ composerTotals.items === 1 ? 'item' : 'items' }}
                            ·
                            <span
                                class="font-semibold text-foreground tabular-nums"
                                >{{
                                    composerTotals.units.toLocaleString()
                                }}</span
                            >
                            units
                            <span
                                v-if="composerTotals.shortages > 0"
                                class="ml-1 text-amber-700 dark:text-amber-400"
                            >
                                · {{ composerTotals.shortages }} above current
                                stock (may be partly issued or backordered)
                            </span>
                            <span
                                v-if="
                                    hasIncompleteLines &&
                                    composerTotals.items > 0
                                "
                                class="block text-amber-700 dark:text-amber-400"
                            >
                                Finish or remove empty lines before submitting.
                            </span>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-end gap-2"
                        >
                            <Button
                                v-if="composerTotals.items > 0"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="clearComposer"
                            >
                                Clear
                            </Button>
                            <Button
                                v-if="props.can.manageTemplates"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="
                                    !canSaveTemplate ||
                                    saveTemplateForm.processing
                                "
                                @click="openSaveTemplateDialog"
                            >
                                Save as template
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    requisitionForm.processing ||
                                    !canSubmitRequisition
                                "
                                data-test="submit-requisition-button"
                                data-testid="submit-requisition-button"
                            >
                                {{
                                    requisitionForm.processing
                                        ? 'Submitting…'
                                        : 'Submit request'
                                }}
                            </Button>
                        </div>
                    </footer>
                </form>
            </section>

            <aside
                class="rounded-xl border border-border/60 bg-card shadow-sm"
                aria-labelledby="templates-heading"
            >
                <header
                    class="flex items-center justify-between gap-3 border-b border-border/50 px-4 py-3.5"
                >
                    <div>
                        <h2
                            id="templates-heading"
                            class="text-sm font-semibold"
                        >
                            Saved templates
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Load a frequent request in one click.
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground tabular-nums"
                        >{{ templates.length }}</span
                    >
                </header>

                <p
                    v-if="templates.length === 0"
                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    No templates yet. Build a request, then choose
                    <span class="font-medium text-foreground"
                        >Save as template</span
                    >.
                </p>

                <ul
                    v-else
                    class="max-h-[32rem] divide-y divide-border/50 overflow-y-auto"
                >
                    <li
                        v-for="template in templates"
                        :key="template.id"
                        class="px-4 py-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">
                                    {{ template.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ template.line_count }}
                                    {{
                                        template.line_count === 1
                                            ? 'item'
                                            : 'items'
                                    }}
                                    ·
                                    <span
                                        :class="
                                            template.lines.some(
                                                (line) =>
                                                    !line.availability
                                                        .available,
                                            )
                                                ? 'text-amber-700 dark:text-amber-400'
                                                : 'text-emerald-700 dark:text-emerald-400'
                                        "
                                        >{{
                                            template.lines.some(
                                                (line) =>
                                                    !line.availability
                                                        .available,
                                            )
                                                ? 'Some items need review'
                                                : 'All items available'
                                        }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 line-clamp-2 text-xs text-muted-foreground/90"
                                >
                                    {{
                                        template.lines
                                            .map(
                                                (line) =>
                                                    `${line.name ?? line.sku} ×${line.qty_requested}`,
                                            )
                                            .join(', ')
                                    }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-7 rounded-lg px-2.5 text-xs"
                                    @click="applyTemplate(template)"
                                    >Use</Button
                                >
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="h-7 w-7 rounded-lg"
                                            :aria-label="`More actions for ${template.name}`"
                                        >
                                            <MoreHorizontal class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            @select="
                                                openTemplateEditor(template)
                                            "
                                            >Edit</DropdownMenuItem
                                        >
                                        <DropdownMenuItem
                                            @select="
                                                duplicateTemplate(template)
                                            "
                                            >Duplicate</DropdownMenuItem
                                        >
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="text-rose-600 focus:text-rose-700"
                                            @select="
                                                openTemplateDeleteDialog(
                                                    template,
                                                )
                                            "
                                            >Delete</DropdownMenuItem
                                        >
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </li>
                </ul>
            </aside>
        </div>

        <section
            class="rounded-xl border border-border/60 bg-card shadow-sm"
            aria-labelledby="requisition-list-heading"
        >
            <header class="border-b border-border/50 px-5 pt-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2
                        id="requisition-list-heading"
                        class="flex items-center gap-2 text-sm font-semibold"
                    >
                        <ClipboardList class="h-4 w-4 text-primary" />
                        Requisitions
                    </h2>
                    <span class="text-xs text-muted-foreground tabular-nums">
                        <template v-if="requisitions.total">
                            Showing {{ requisitions.from }}–{{
                                requisitions.to
                            }}
                            of
                            {{ requisitions.total }}
                        </template>
                    </span>
                </div>
                <div
                    class="mt-3 flex gap-1 overflow-x-auto"
                    role="tablist"
                    aria-label="Filter by status"
                >
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        type="button"
                        role="tab"
                        :aria-selected="status === tab.value"
                        class="-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-xs font-medium transition-colors"
                        :class="
                            status === tab.value
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="status = tab.value"
                    >
                        {{ tab.label }}
                        <span
                            class="rounded-full bg-muted px-1.5 text-[10px] tabular-nums"
                            >{{ tab.count }}</span
                        >
                    </button>
                </div>
            </header>

            <fieldset
                class="grid gap-3 border-b border-border/50 px-5 py-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr]"
            >
                <legend class="sr-only">Filter requisitions</legend>
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    Search
                    <Input
                        v-model="search"
                        type="search"
                        name="requisition_search"
                        autocomplete="off"
                        placeholder="Number, requester, product, or SKU"
                    />
                </label>
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    Submitted from
                    <Input
                        v-model="dateFrom"
                        name="requisition_date_from"
                        type="date"
                    />
                </label>
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    Submitted to
                    <Input
                        v-model="dateTo"
                        name="requisition_date_to"
                        type="date"
                        :min="dateFrom || undefined"
                    />
                </label>
            </fieldset>

            <div
                v-if="
                    (props.can.bulkApprove || props.can.bulkIssue) &&
                    hasSelection
                "
                class="flex flex-wrap items-center gap-2 border-b border-primary/20 bg-primary/5 px-5 py-2 text-sm"
            >
                <span class="font-medium text-primary">
                    {{ selectedIds.size }} selected
                    <span
                        v-if="
                            selectedApprovableRows.length > 0 &&
                            selectedIssuableRows.length > 0
                        "
                        class="text-xs font-normal text-muted-foreground"
                    >
                        ({{ selectedApprovableRows.length }} submitted,
                        {{ selectedIssuableRows.length }} to issue)
                    </span>
                </span>
                <div class="ml-auto flex flex-wrap gap-2">
                    <Button
                        v-if="props.can.bulkApprove"
                        variant="outline"
                        size="sm"
                        class="h-7 rounded-lg text-xs"
                        :disabled="selectedApprovableRows.length === 0"
                        :title="
                            selectedApprovableRows.length === 0
                                ? 'None of the selected requisitions are eligible for approval (must be in Submitted status)'
                                : undefined
                        "
                        @click="runBulkApprove"
                    >
                        Approve{{
                            selectedApprovableRows.length > 0
                                ? ` (${selectedApprovableRows.length})`
                                : ''
                        }}
                    </Button>
                    <Button
                        v-if="props.can.bulkIssue"
                        variant="outline"
                        size="sm"
                        class="h-7 rounded-lg text-xs"
                        :disabled="selectedIssuableRows.length === 0"
                        :title="
                            selectedIssuableRows.length === 0
                                ? 'None of the selected requisitions are eligible for issuing (must be Approved, Partially Issued, or Backordered)'
                                : undefined
                        "
                        @click="runBulkIssue"
                    >
                        Issue{{
                            selectedIssuableRows.length > 0
                                ? ` (${selectedIssuableRows.length})`
                                : ''
                        }}
                    </Button>
                </div>
            </div>

            <div
                v-if="requisitions.data.length === 0"
                class="px-4 py-12 text-center"
            >
                <p class="text-sm font-medium">No requisitions found</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Try another status, search term, or date range.
                </p>
            </div>

            <ul v-else class="divide-y divide-border/50 md:hidden">
                <li
                    v-for="r in requisitions.data"
                    :key="r.id"
                    class="px-4 py-3 text-sm"
                    :class="{ 'bg-primary/5': selectedIds.has(r.id) }"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-if="
                                    props.can.bulkApprove || props.can.bulkIssue
                                "
                                :model-value="selectedIds.has(r.id)"
                                :disabled="!isRowActionable(r)"
                                :title="getRowDisabledReason(r) ?? undefined"
                                @update:model-value="
                                    () =>
                                        isRowActionable(r) && toggleSelect(r.id)
                                "
                                :aria-label="
                                    isRowActionable(r)
                                        ? `Select requisition #${r.id}`
                                        : `Requisition #${r.id} cannot be selected: ${getRowDisabledReason(r)}`
                                "
                            />
                            <span class="font-semibold">#{{ r.id }}</span>
                        </div>
                        <span
                            class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                            :class="statusBadgeClass(r.status)"
                            >{{ formatStatusLabel(r.status) }}</span
                        >
                    </div>
                    <p class="mt-1 text-xs text-foreground/85">
                        {{ itemsSummary(r) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ r.requester.name ?? r.requester.email ?? '—' }} ·
                        {{ formatDateTime(r.created_at) }}
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 rounded-lg text-xs"
                            as-child
                            data-testid="open-requisition-button"
                        >
                            <Link :href="requisitionsShow(r.id)"
                                >View details</Link
                            >
                        </Button>
                        <Button
                            v-if="r.can_delete"
                            variant="ghost"
                            size="sm"
                            class="h-7 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                            @click="openDeleteDialog(r)"
                        >
                            Move to trash
                        </Button>
                    </div>
                </li>
            </ul>

            <div
                v-if="requisitions.data.length"
                class="hidden overflow-x-auto md:block"
            >
                <table class="w-full text-sm">
                    <caption class="sr-only">
                        Requisitions with items, requester, submission date,
                        status, issue progress, and actions
                    </caption>
                    <thead
                        class="bg-muted/40 text-left text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr class="[&>th]:px-4 [&>th]:py-2.5">
                            <th
                                v-if="
                                    props.can.bulkApprove || props.can.bulkIssue
                                "
                                class="w-8"
                            >
                                <Checkbox
                                    :model-value="
                                        allSelected
                                            ? true
                                            : someSelected
                                              ? 'indeterminate'
                                              : false
                                    "
                                    :disabled="
                                        actionableRequisitions.length === 0
                                    "
                                    :title="
                                        actionableRequisitions.length === 0
                                            ? 'No actionable requisitions on this page'
                                            : 'Select all actionable requisitions on this page'
                                    "
                                    @update:model-value="toggleSelectAll"
                                    aria-label="Select all actionable requisitions on this page"
                                />
                            </th>
                            <th scope="col">Requisition</th>
                            <th scope="col">Requester</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="w-36">Issued</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr
                            v-for="r in requisitions.data"
                            :key="r.id"
                            class="align-top transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3"
                            :class="{ 'bg-primary/5': selectedIds.has(r.id) }"
                        >
                            <td
                                v-if="
                                    props.can.bulkApprove || props.can.bulkIssue
                                "
                            >
                                <Checkbox
                                    :model-value="selectedIds.has(r.id)"
                                    :disabled="!isRowActionable(r)"
                                    :title="
                                        getRowDisabledReason(r) ?? undefined
                                    "
                                    @update:model-value="
                                        () =>
                                            isRowActionable(r) &&
                                            toggleSelect(r.id)
                                    "
                                    :aria-label="
                                        isRowActionable(r)
                                            ? `Select requisition #${r.id}`
                                            : `Requisition #${r.id} cannot be selected: ${getRowDisabledReason(r)}`
                                    "
                                />
                            </td>
                            <td class="max-w-xs">
                                <div class="font-semibold">#{{ r.id }}</div>
                                <div
                                    class="truncate text-xs text-muted-foreground"
                                    :title="itemsSummary(r)"
                                >
                                    {{ itemsSummary(r) }}
                                </div>
                            </td>
                            <td>
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
                            <td
                                class="text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(r.created_at) }}
                            </td>
                            <td>
                                <span
                                    class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
                                    :class="statusBadgeClass(r.status)"
                                    >{{ formatStatusLabel(r.status) }}</span
                                >
                            </td>
                            <td>
                                <template v-if="r.totals">
                                    <div
                                        class="h-1.5 overflow-hidden rounded-full bg-muted"
                                        role="progressbar"
                                        :aria-valuenow="issuedPercent(r)"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        :aria-label="`Requisition ${r.id} issued`"
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="{
                                                width: `${issuedPercent(r)}%`,
                                            }"
                                        />
                                    </div>
                                    <div
                                        class="mt-1 text-[11px] text-muted-foreground tabular-nums"
                                    >
                                        {{ r.totals.qty_issued }} /
                                        {{ r.totals.qty_requested }} units
                                    </div>
                                </template>
                            </td>
                            <td class="text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 rounded-lg text-xs"
                                        as-child
                                        data-testid="open-requisition-button"
                                    >
                                        <Link :href="requisitionsShow(r.id)"
                                            >View details</Link
                                        >
                                    </Button>
                                    <Button
                                        v-if="r.can_delete"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                        @click="openDeleteDialog(r)"
                                    >
                                        Move to trash
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="requisitions.links?.length" class="px-4 py-3">
                <AdminPagination :links="requisitions.links" />
            </div>
        </section>

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
                    <DialogTitle>Move requisition to trash?</DialogTitle>
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
                                <SelectValue placeholder="Select a reason…" />
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
                            placeholder="Enter your reason…"
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
                        >Move to Trash</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bulkActionDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{
                            pendingBulkAction === 'approve'
                                ? `Approve ${selectedApprovableRows.length} requisition(s)?`
                                : `Issue ${selectedIssuableRows.length} requisition(s)?`
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="pendingBulkAction === 'approve'">
                            This will approve
                            <strong
                                >{{ selectedApprovableRows.length }} submitted
                                requisition(s)</strong
                            >
                            and transition them to Approved status for inventory
                            fulfillment.
                            <span
                                v-if="
                                    selectedIds.size >
                                    selectedApprovableRows.length
                                "
                                class="mt-2 block text-xs text-muted-foreground"
                            >
                                Note:
                                {{
                                    selectedIds.size -
                                    selectedApprovableRows.length
                                }}
                                selected requisition(s) are not in submitted
                                status and will be excluded.
                            </span>
                        </template>
                        <template v-else>
                            This will issue inventory for
                            <strong
                                >{{ selectedIssuableRows.length }} approved
                                requisition(s)</strong
                            >
                            based on available stock.
                            <span
                                v-if="
                                    selectedIds.size >
                                    selectedIssuableRows.length
                                "
                                class="mt-2 block text-xs text-muted-foreground"
                            >
                                Note:
                                {{
                                    selectedIds.size -
                                    selectedIssuableRows.length
                                }}
                                selected requisition(s) cannot be issued and
                                will be excluded.
                            </span>
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            variant="secondary"
                            :disabled="bulkActionProcessing"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        :disabled="
                            bulkActionProcessing ||
                            (pendingBulkAction === 'approve'
                                ? selectedApprovableRows.length === 0
                                : selectedIssuableRows.length === 0)
                        "
                        @click="confirmBulkAction"
                    >
                        {{
                            bulkActionProcessing
                                ? 'Processing…'
                                : pendingBulkAction === 'approve'
                                  ? `Approve Requisitions (${selectedApprovableRows.length})`
                                  : `Issue Requisitions (${selectedIssuableRows.length})`
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ProductSelectDialog
            v-model:open="catalogDialogOpen"
            title="Browse products for requisition"
            description="Select products from the catalog. Requested items are automatically added or consolidated into your request draft."
            type-filter="consumable"
            :exclude-skus="
                requisitionForm.lines.map((l) => l.sku).filter(Boolean)
            "
            @select="onProductSelectedFromCatalog"
        />
    </div>
</template>
