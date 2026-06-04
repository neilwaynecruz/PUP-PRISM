<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <Input
                    v-model="search"
                    placeholder="Search deleted items..."
                    class="h-9 w-64 rounded-lg text-sm"
                />
                <select
                    v-model="type"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                >
                    <option value="">All types</option>
                    <option value="product">Products</option>
                    <option value="booking">Bookings</option>
                    <option value="requisition">Requisitions</option>
                </select>
            </div>

            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                class="h-8 rounded-lg text-xs"
                @click="
                    search = '';
                    type = '';
                "
            >
                Clear filters
            </Button>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm"
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

        <div
            v-if="items.links.length"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, i) in items.links"
                :key="i"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="
                    link.active ? 'bg-primary/10 font-medium text-primary' : ''
                "
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
