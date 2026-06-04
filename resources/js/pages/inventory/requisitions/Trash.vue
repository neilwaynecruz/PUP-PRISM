<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type PaginationLink = { url: string | null; label: string; active: boolean };

type UserStub = { id: number; name: string; email: string };

type RequisitionRow = {
    id: number;
    status: string;
    requester: { name: string } | null;
    created_at: string;
    deleted_at: string;
    deleted_by: UserStub | null;
    deletion_reason: string | null;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const props = defineProps<{
    requisitions: Paginated<RequisitionRow>;
    filters: {
        search: string;
        date_from: string;
        date_to: string;
        deleted_by: number | null;
    };
    deleters: { id: number; name: string }[];
}>();

const search = ref(props.filters.search);
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const deletedBy = ref(props.filters.deleted_by ?? '');

const hasActiveFilters = computed(() => {
    return search.value || dateFrom.value || dateTo.value || deletedBy.value;
});

function applyFilters(): void {
    router.get('/inventory/requisitions/trash', {
        search: search.value || null,
        date_from: dateFrom.value || null,
        date_to: dateTo.value || null,
        deleted_by: deletedBy.value || null,
    }, { preserveState: true, preserveScroll: true });
}

function resetFilters(): void {
    search.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    deletedBy.value = '';
    applyFilters();
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
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

const selectedRequisition = ref<RequisitionRow | null>(null);
const restoreDialogOpen = ref(false);
const forceDeleteDialogOpen = ref(false);
const bulkRestoreDialogOpen = ref(false);
const bulkForceDeleteDialogOpen = ref(false);
const selectedIds = ref<Set<number>>(new Set());

const allSelected = computed(() => {
    return props.requisitions.data.length > 0 && props.requisitions.data.every(r => selectedIds.value.has(r.id));
});

const hasSelection = computed(() => selectedIds.value.size > 0);

function toggleSelectAll(): void {
    if (allSelected.value) {
        props.requisitions.data.forEach(r => selectedIds.value.delete(r.id));
    } else {
        props.requisitions.data.forEach(r => selectedIds.value.add(r.id));
    }
}

function toggleSelect(id: number): void {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
}

function openRestoreDialog(requisition: RequisitionRow): void {
    selectedRequisition.value = requisition;
    restoreDialogOpen.value = true;
}

function openForceDeleteDialog(requisition: RequisitionRow): void {
    selectedRequisition.value = requisition;
    forceDeleteDialogOpen.value = true;
}

function openBulkRestoreDialog(): void {
    bulkRestoreDialogOpen.value = true;
}

function openBulkForceDeleteDialog(): void {
    bulkForceDeleteDialogOpen.value = true;
}

function confirmRestore(): void {
    if (!selectedRequisition.value) return;
    router.put(`/inventory/requisitions/${selectedRequisition.value.id}/restore`, {}, {
        onSuccess: () => {
            restoreDialogOpen.value = false;
            selectedRequisition.value = null;
        },
    });
}

function confirmForceDelete(): void {
    if (!selectedRequisition.value) return;
    router.delete(`/inventory/requisitions/${selectedRequisition.value.id}/force`, {
        onSuccess: () => {
            forceDeleteDialogOpen.value = false;
            selectedRequisition.value = null;
        },
    });
}

function confirmBulkRestore(): void {
    router.post('/inventory/requisitions/bulk-restore', {
        ids: Array.from(selectedIds.value),
    }, {
        onSuccess: () => {
            bulkRestoreDialogOpen.value = false;
            selectedIds.value.clear();
        },
    });
}

function confirmBulkForceDelete(): void {
    router.post('/inventory/requisitions/bulk-force-delete', {
        ids: Array.from(selectedIds.value),
    }, {
        onSuccess: () => {
            bulkForceDeleteDialogOpen.value = false;
            selectedIds.value.clear();
        },
    });
}
</script>

<template>
    <Head title="Trash — Requisitions" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <!-- Filters -->
        <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="grid gap-2">
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="search" placeholder="Requester or ID..." @keyup.enter="applyFilters" />
                </div>
                <div class="grid gap-2">
                    <Label for="date_from">Deleted From</Label>
                    <Input id="date_from" v-model="dateFrom" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label for="date_to">Deleted To</Label>
                    <Input id="date_to" v-model="dateTo" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label for="deleted_by">Deleted By</Label>
                    <Select v-model="deletedBy">
                        <SelectTrigger id="deleted_by">
                            <SelectValue placeholder="All users" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All users</SelectItem>
                            <SelectItem v-for="user in deleters" :key="user.id" :value="String(user.id)">
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label class="invisible">Actions</Label>
                    <div class="flex gap-2">
                        <Button @click="applyFilters">Filter</Button>
                        <Button v-if="hasActiveFilters" variant="ghost" @click="resetFilters">Reset</Button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Trash"
                description="Deleted requisitions can be restored here."
            />
            <div class="flex items-center gap-2">
                <template v-if="hasSelection">
                    <span class="text-sm text-muted-foreground">{{ selectedIds.size }} selected</span>
                    <Button variant="outline" size="sm" @click="openBulkRestoreDialog">
                        Restore Selected
                    </Button>
                    <Button variant="destructive" size="sm" @click="openBulkForceDeleteDialog">
                        Delete Selected Forever
                    </Button>
                </template>
                <Button variant="outline" size="sm" as-child class="rounded-lg">
                    <Link href="/inventory/requisitions">Back to requisitions</Link>
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">
                        <th class="w-10">
                            <Checkbox
                                :checked="allSelected"
                                @update:checked="toggleSelectAll"
                                aria-label="Select all"
                            />
                        </th>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Requester</th>
                        <th>Created</th>
                        <th>Deleted at</th>
                        <th>Deleted by</th>
                        <th>Reason</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="requisitions.data.length === 0">
                        <td colspan="8" class="p-0">
                            <EmptyState
                                icon="requisition"
                                title="Trash is empty"
                                description="Deleted requisitions will appear here. You have 30 days to restore them before they are permanently removed."
                                action-label="Back to Requisitions"
                                action-href="/inventory/requisitions"
                            />
                        </td>
                    </tr>
                    <tr
                        v-for="r in requisitions.data"
                        :key="r.id"
                        class="[&>td]:px-4 [&>td]:py-3"
                    >
                        <td>
                            <Checkbox
                                :checked="selectedIds.has(r.id)"
                                @update:checked="() => toggleSelect(r.id)"
                                aria-label="Select requisition"
                            />
                        </td>
                        <td class="font-mono text-xs text-muted-foreground">#{{ r.id }}</td>
                        <td>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide capitalize"
                                :class="r.status === 'approved' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : r.status === 'rejected' ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'"
                            >
                                {{ r.status }}
                            </span>
                        </td>
                        <td class="text-muted-foreground">{{ r.requester?.name ?? '—' }}</td>
                        <td class="text-muted-foreground">{{ formatDateTime(r.created_at) }}</td>
                        <td class="text-muted-foreground">{{ formatDateTime(r.deleted_at) }}</td>
                        <td class="text-muted-foreground">{{ r.deleted_by?.name ?? '—' }}</td>
                        <td class="max-w-[200px] truncate text-muted-foreground" :title="r.deletion_reason ?? undefined">{{ r.deletion_reason ?? '—' }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 rounded-lg text-xs"
                                    @click="openRestoreDialog(r)"
                                >
                                    Restore
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 rounded-lg text-xs text-rose-600 hover:text-rose-700"
                                    @click="openForceDeleteDialog(r)"
                                >
                                    Delete Forever
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="restoreDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Restore requisition?</DialogTitle>
                    <DialogDescription>
                        This will restore requisition <strong>#{{ selectedRequisition?.id }}</strong> back to active requisitions.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button @click="confirmRestore">Restore</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="forceDeleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle class="text-rose-600">Permanently delete requisition?</DialogTitle>
                    <DialogDescription>
                        This will <strong class="text-rose-600">permanently delete</strong> requisition <strong>#{{ selectedRequisition?.id }}</strong>.
                        <br><br>
                        <span class="text-rose-600 font-medium">This action cannot be undone.</span>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmForceDelete">Permanently Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bulkRestoreDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Bulk restore requisitions?</DialogTitle>
                    <DialogDescription>
                        This will restore <strong>{{ selectedIds.size }} requisition(s)</strong> to the active requisitions list.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button @click="confirmBulkRestore">Restore All</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bulkForceDeleteDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle class="text-rose-600">Permanently delete {{ selectedIds.size }} requisitions?</DialogTitle>
                    <DialogDescription>
                        This will <strong class="text-rose-600">permanently delete</strong> <strong>{{ selectedIds.size }} requisition(s)</strong>.
                        <br><br>
                        <span class="text-rose-600 font-medium">This action cannot be undone.</span>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmBulkForceDelete">Permanently Delete All</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div v-if="requisitions.links.length" class="flex flex-wrap items-center justify-center gap-1">
            <Button
                v-for="(link, i) in requisitions.links"
                :key="i"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="link.active ? 'bg-primary/10 text-primary font-medium' : ''"
            >
                <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state>
                    <span v-html="link.label" />
                </Link>
                <span v-else v-html="link.label" />
            </Button>
        </div>
    </div>
</template>
