<script setup lang="ts">
defineOptions({ name: 'InventoryTrashPage' });

import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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

type PaginationLink = { url: string | null; label: string; active: boolean };

type UserStub = { id: number; name: string; email: string };

type TrashItem = {
    id: number;
    type: 'product' | 'booking' | 'requisition';
    label: string;
    meta: string | null;
    deleted_at: string;
    deleted_by: UserStub | null;
    deletion_reason: string | null;
    restore_url: string;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const props = defineProps<{
    filters: { search: string; type: string };
    items: Paginated<TrashItem>;
}>();

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');

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

const hasActiveFilters = computed(
    () => search.value !== '' || type.value !== '',
);

watch([search, type], () => {
    router.get(
        '/inventory/trash',
        { search: search.value, type: type.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const selectedItem = ref<TrashItem | null>(null);
const dialogOpen = ref(false);

function openRestoreDialog(item: TrashItem): void {
    selectedItem.value = item;
    dialogOpen.value = true;
}

function confirmRestore(): void {
    if (!selectedItem.value) {
        return;
    }

    router.put(
        selectedItem.value.restore_url,
        {},
        {
            onSuccess: () => {
                dialogOpen.value = false;
                selectedItem.value = null;
            },
        },
    );
}

function typeBadgeClass(itemType: string): string {
    switch (itemType) {
        case 'product':
            return 'bg-sky-500/10 text-sky-700 dark:text-sky-400';
        case 'booking':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
        case 'requisition':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
        default:
            return 'bg-muted text-muted-foreground';
    }
}
</script>

<template>
    <Head title="Trash" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Trash"
                description="All deleted items across inventory."
            />
        </div>

        <fieldset
            class="grid gap-3 rounded-xl border border-border/60 bg-card p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-3"
        >
            <legend class="sr-only">Filter deleted items</legend>
            <label
                class="grid gap-1.5 text-xs font-medium text-muted-foreground"
            >
                Search deleted items
                <Input
                    v-model="search"
                    name="trash_search"
                    autocomplete="off"
                    placeholder="Search deleted items..."
                    class="min-h-11 rounded-lg text-sm sm:min-h-9"
                />
            </label>
            <label
                class="grid gap-1.5 text-xs font-medium text-muted-foreground"
            >
                Item type
                <select
                    v-model="type"
                    name="trash_type"
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm sm:min-h-9"
                >
                    <option value="">All types</option>
                    <option value="product">Products</option>
                    <option value="booking">Bookings</option>
                    <option value="requisition">Requisitions</option>
                </select>
            </label>

            <div class="flex items-end">
                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    class="min-h-11 rounded-lg text-xs sm:min-h-9"
                    @click="
                        search = '';
                        type = '';
                    "
                >
                    Clear filters
                </Button>
            </div>
        </fieldset>

        <!-- Mobile Record Cards -->
        <div
            v-if="items.data.length"
            class="grid gap-3 md:hidden"
            data-testid="trash-mobile-list"
        >
            <article
                v-for="item in items.data"
                :key="`mobile-${item.type}-${item.id}`"
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold break-words text-foreground">
                            {{ item.label }}
                        </h2>
                        <p
                            v-if="item.meta"
                            class="mt-0.5 font-mono text-xs text-muted-foreground"
                        >
                            {{ item.meta }}
                        </p>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase"
                        :class="typeBadgeClass(item.type)"
                    >
                        {{ item.type }}
                    </span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Deleted at
                        </dt>
                        <dd class="mt-0.5 text-xs text-foreground">
                            {{ formatDateTime(item.deleted_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Deleted by
                        </dt>
                        <dd class="mt-0.5 text-xs text-foreground">
                            {{ item.deleted_by?.name ?? '—' }}
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-muted-foreground">Reason</dt>
                        <dd class="mt-0.5 text-xs text-foreground">
                            {{ item.deletion_reason ?? '—' }}
                        </dd>
                    </div>
                </dl>
                <Button
                    variant="outline"
                    class="mt-4 min-h-11 w-full"
                    @click="openRestoreDialog(item)"
                >
                    Restore
                </Button>
            </article>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground md:hidden"
        >
            Trash is empty.
        </div>

        <div
            class="hidden overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm md:block"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr
                        class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
                    >
                        <th>Type</th>
                        <th>Label</th>
                        <th>Meta</th>
                        <th>Deleted at</th>
                        <th>Deleted by</th>
                        <th>Reason</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="items.data.length === 0">
                        <td
                            class="px-4 py-8 text-center text-sm text-muted-foreground"
                            colspan="7"
                        >
                            Trash is empty.
                        </td>
                    </tr>
                    <tr
                        v-for="item in items.data"
                        :key="item.type + '-' + item.id"
                        class="[&>td]:px-4 [&>td]:py-3"
                    >
                        <td>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase"
                                :class="typeBadgeClass(item.type)"
                            >
                                {{ item.type }}
                            </span>
                        </td>
                        <td class="font-medium">{{ item.label }}</td>
                        <td class="font-mono text-[11px] text-muted-foreground">
                            {{ item.meta ?? '—' }}
                        </td>
                        <td class="text-muted-foreground">
                            {{ formatDateTime(item.deleted_at) }}
                        </td>
                        <td class="text-muted-foreground">
                            {{ item.deleted_by?.name ?? '—' }}
                        </td>
                        <td
                            class="max-w-[200px] truncate text-muted-foreground"
                            :title="item.deletion_reason ?? undefined"
                        >
                            {{ item.deletion_reason ?? '—' }}
                        </td>
                        <td class="text-right">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 rounded-lg text-xs"
                                @click="openRestoreDialog(item)"
                            >
                                Restore
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Restore {{ selectedItem?.type }}?</DialogTitle>
                    <DialogDescription>
                        This will restore
                        <strong>{{ selectedItem?.label }}</strong> back to the
                        active list.
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

        <AdminPagination :links="items.links" label="Trash pages" />
    </div>
</template>
