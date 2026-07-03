<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as notificationsIndex, read as markNotificationRead, readAll } from '@/routes/notifications';

type NotificationRow = {
    id: string;
    type: string;
    category: string;
    severity: string;
    title: string;
    message: string;
    url: string | null;
    createdAt: string | null;
    readAt: string | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: {
        search: string;
        read: string;
        category: string;
        date_from: string;
        date_to: string;
    };
    notifications: {
        data: NotificationRow[];
        links: PaginationLink[];
    };
    categories: string[];
    unreadCount: number;
}>();

defineOptions({
    name: 'NotificationsIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Notifications', href: notificationsIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const readFilter = ref(props.filters.read ?? '');
const category = ref(props.filters.category ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let refreshTimer: number | undefined;

watch([search, readFilter, category, dateFrom, dateTo], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            notificationsIndex().url,
            {
                search: search.value || undefined,
                read: readFilter.value || undefined,
                category: category.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 250);
});

onBeforeUnmount(() => {
    window.clearTimeout(refreshTimer);
});

function formatTimestamp(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function markRead(notificationId: string): void {
    router.put(markNotificationRead(notificationId).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                title="Notification history"
                :description="`${unreadCount} unread notification(s)`"
            />

            <Button
                variant="outline"
                size="sm"
                class="rounded-lg"
                :disabled="unreadCount === 0"
                @click="router.put(readAll().url, {}, { preserveScroll: true })"
            >
                Mark all read
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <Input
                v-model="search"
                placeholder="Search title or message..."
                class="h-10 rounded-lg lg:col-span-2"
            />
            <select
                v-model="readFilter"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All</option>
                <option value="unread">Unread</option>
                <option value="read">Read</option>
            </select>
            <select
                v-model="category"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All categories</option>
                <option
                    v-for="categoryOption in categories"
                    :key="categoryOption"
                    :value="categoryOption"
                >
                    {{ categoryOption }}
                </option>
            </select>
            <Input v-model="dateFrom" type="date" class="h-10 rounded-lg" />
            <Input v-model="dateTo" type="date" class="h-10 rounded-lg" />
        </div>

        <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
            <div v-if="notifications.data.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                No notifications found.
            </div>
            <div
                v-for="notification in notifications.data"
                :key="notification.id"
                class="flex items-start gap-4 border-b border-border/60 px-4 py-4 last:border-b-0"
            >
                <span
                    class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                    :class="notification.readAt ? 'bg-border' : 'bg-primary'"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">{{ notification.title }}</h2>
                        <span class="text-xs text-muted-foreground">
                            {{ formatTimestamp(notification.createdAt) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">{{ notification.message }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                        <span class="rounded-full bg-muted px-2 py-0.5 capitalize">
                            {{ notification.category }}
                        </span>
                        <span class="rounded-full bg-muted px-2 py-0.5 capitalize">
                            {{ notification.severity }}
                        </span>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col gap-2">
                    <Button
                        v-if="!notification.readAt"
                        variant="outline"
                        size="sm"
                        class="rounded-lg"
                        @click="markRead(notification.id)"
                    >
                        Mark read
                    </Button>
                    <Button
                        v-if="notification.url"
                        variant="ghost"
                        size="sm"
                        as-child
                        class="rounded-lg"
                    >
                        <Link :href="notification.url">Open</Link>
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="notifications.links.length"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, index) in notifications.links"
                :key="index"
                variant="ghost"
                size="sm"
                :disabled="!link.url"
                as-child
                class="h-8 rounded-lg text-xs"
                :class="link.active ? 'bg-primary/10 text-primary' : ''"
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
