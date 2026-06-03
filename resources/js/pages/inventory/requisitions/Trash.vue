<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

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
}>();

function restoreRequisition(id: number): void {
    if (! confirm('Restore this requisition?')) {
        return;
    }

    router.put(`/inventory/requisitions/${id}/restore`);
}
</script>

<template>
    <Head title="Trash — Requisitions" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Trash"
                description="Deleted requisitions can be restored here."
            />
            <Button variant="outline" size="sm" as-child class="rounded-lg">
                <Link href="/inventory/requisitions">Back to requisitions</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">
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
                        <td class="px-4 py-8 text-center text-sm text-muted-foreground" colspan="8">
                            Trash is empty.
                        </td>
                    </tr>
                    <tr
                        v-for="r in requisitions.data"
                        :key="r.id"
                        class="[&>td]:px-4 [&>td]:py-3"
                    >
                        <td class="font-mono text-xs text-muted-foreground">#{{ r.id }}</td>
                        <td>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide capitalize"
                                :class="r.status === 'approved' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : r.status === 'rejected' ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'"
                            >
                                {{ r.status }}
                            </span>
                        </td>
                        <td class="text-muted-foreground">{{ r.requester?.name ?? '—' }}</td>
                        <td class="font-mono text-xs text-muted-foreground">{{ r.created_at }}</td>
                        <td class="font-mono text-xs text-muted-foreground">{{ r.deleted_at }}</td>
                        <td class="text-muted-foreground">{{ r.deleted_by?.name ?? '—' }}</td>
                        <td class="max-w-[200px] truncate text-muted-foreground" :title="r.deletion_reason ?? undefined">{{ r.deletion_reason ?? '—' }}</td>
                        <td class="text-right">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 rounded-lg text-xs"
                                @click="restoreRequisition(r.id)"
                            >
                                Restore
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

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
