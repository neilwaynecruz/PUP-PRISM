<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

type PaginationLink = { url: string | null; label: string; active: boolean };

type UserStub = { id: number; name: string; email: string };

type BookingRow = {
    id: number;
    asset_label: string | null;
    status: string;
    start: string;
    end: string;
    requester: { name: string } | null;
    deleted_at: string;
    deleted_by: UserStub | null;
    deletion_reason: string | null;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const props = defineProps<{
    bookings: Paginated<BookingRow>;
}>();

function restoreBooking(id: number): void {
    if (! confirm('Restore this booking?')) {
        return;
    }

    router.put(`/inventory/bookings/${id}/restore`);
}
</script>

<template>
    <Head title="Trash — Bookings" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Trash"
                description="Deleted bookings can be restored here."
            />
            <Button variant="outline" size="sm" as-child class="rounded-lg">
                <Link href="/inventory/bookings">Back to bookings</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">
                        <th>Asset</th>
                        <th>Status</th>
                        <th>Requester</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Deleted at</th>
                        <th>Deleted by</th>
                        <th>Reason</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="bookings.data.length === 0">
                        <td class="px-4 py-8 text-center text-sm text-muted-foreground" colspan="9">
                            Trash is empty.
                        </td>
                    </tr>
                    <tr
                        v-for="b in bookings.data"
                        :key="b.id"
                        class="[&>td]:px-4 [&>td]:py-3"
                    >
                        <td class="font-medium">{{ b.asset_label ?? '—' }}</td>
                        <td>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide"
                                :class="b.status === 'approved' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'"
                            >
                                {{ b.status }}
                            </span>
                        </td>
                        <td class="text-muted-foreground">{{ b.requester?.name ?? '—' }}</td>
                        <td class="font-mono text-xs text-muted-foreground">{{ b.start }}</td>
                        <td class="font-mono text-xs text-muted-foreground">{{ b.end }}</td>
                        <td class="font-mono text-xs text-muted-foreground">{{ b.deleted_at }}</td>
                        <td class="text-muted-foreground">{{ b.deleted_by?.name ?? '—' }}</td>
                        <td class="max-w-[200px] truncate text-muted-foreground" :title="b.deletion_reason ?? undefined">{{ b.deletion_reason ?? '—' }}</td>
                        <td class="text-right">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 rounded-lg text-xs"
                                @click="restoreBooking(b.id)"
                            >
                                Restore
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="bookings.links.length" class="flex flex-wrap items-center justify-center gap-1">
            <Button
                v-for="(link, i) in bookings.links"
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
