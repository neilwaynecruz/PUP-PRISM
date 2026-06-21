<script setup lang="ts">
import { Bell, CheckCheck, LoaderCircle, Radio } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications';

const {
    connectionLabel,
    connectionStatus,
    hasNotifications,
    hasUnread,
    isSyncing,
    markAllAsRead,
    notifications,
    openNotification,
    unreadCount,
} = useRealtimeNotifications();

const connectionTone = computed(() => {
    switch (connectionStatus.value) {
        case 'connected':
            return 'bg-emerald-500';
        case 'connecting':
            return 'bg-amber-500';
        case 'failed':
            return 'bg-rose-500';
        default:
            return 'bg-slate-400';
    }
});

function formatTimestamp(timestamp: string | null): string {
    if (!timestamp) {
        return 'Just now';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        month: 'short',
    }).format(new Date(timestamp));
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="relative rounded-lg border-dashed"
                aria-label="Open notifications"
            >
                <Bell class="h-4 w-4" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground shadow-sm"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :side-offset="10"
            class="w-[24rem] rounded-2xl border border-border/70 p-0 shadow-2xl"
        >
            <div class="flex items-center justify-between px-4 py-3">
                <div>
                    <DropdownMenuLabel class="px-0 text-sm font-semibold">
                        Notifications
                    </DropdownMenuLabel>
                    <p class="text-xs text-muted-foreground">
                        Live updates for approvals, stock, and handovers
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-muted/60 px-2.5 py-1 text-[11px] font-medium text-muted-foreground"
                    >
                        <Radio class="h-3 w-3" />
                        <span :class="connectionTone" class="h-2 w-2 rounded-full" />
                        {{ connectionLabel }}
                    </span>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 rounded-full px-3 text-xs"
                        :disabled="!hasUnread"
                        @click="markAllAsRead"
                    >
                        <CheckCheck class="mr-1.5 h-3.5 w-3.5" />
                        Read all
                    </Button>
                </div>
            </div>

            <DropdownMenuSeparator />

            <div v-if="hasNotifications" class="max-h-[26rem] overflow-y-auto p-2">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-xl px-3 py-3 text-left transition-colors hover:bg-muted/70"
                    @click="openNotification(notification)"
                >
                    <span
                        class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                        :class="notification.readAt ? 'bg-border' : 'bg-primary'"
                    />

                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-semibold text-foreground">
                                {{ notification.title }}
                            </span>
                            <span class="shrink-0 text-[11px] text-muted-foreground">
                                {{ formatTimestamp(notification.createdAt) }}
                            </span>
                        </span>
                        <span class="mt-1 line-clamp-2 block text-xs leading-5 text-muted-foreground">
                            {{ notification.message }}
                        </span>
                    </span>
                </button>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center"
            >
                <div class="rounded-full border border-dashed border-border/70 bg-muted/40 p-3">
                    <Bell class="h-5 w-5 text-muted-foreground" />
                </div>
                <div>
                    <p class="text-sm font-medium text-foreground">No notifications yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        New requests and workflow updates will appear here.
                    </p>
                </div>
            </div>

            <DropdownMenuSeparator />

            <div class="flex items-center justify-between px-4 py-2.5 text-[11px] text-muted-foreground">
                <span>
                    {{ hasUnread ? `${unreadCount} unread` : 'All caught up' }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <LoaderCircle v-if="isSyncing" class="h-3.5 w-3.5 animate-spin" />
                    <span>{{ isSyncing ? 'Syncing' : 'Listening' }}</span>
                </span>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
