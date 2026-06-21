import { router, usePage } from '@inertiajs/vue3';
import { echo, echoIsConfigured, useConnectionStatus } from '@laravel/echo-vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { read, readAll } from '@/routes/notifications';
import type {
    Auth,
    RealtimeNotificationItem,
    SharedNotifications,
} from '@/types';

type InventoryRealtimePayload = {
    entity?: string;
    action?: string;
    title?: string;
    message?: string;
    modules?: string[];
    url?: string | null;
    context?: Record<string, unknown>;
    occurred_at?: string;
};

type BroadcastNotificationPayload = Partial<RealtimeNotificationItem> & {
    type?: string;
};

const defaultNotifications: SharedNotifications = {
    unreadCount: 0,
    items: [],
};

const fallbackPollInterval = 60_000;

export function useRealtimeNotifications() {
    const page = usePage();
    const connectionStatus = useConnectionStatus();
    const notifications = ref<RealtimeNotificationItem[]>([]);
    const unreadCount = ref(0);
    const isSyncing = ref(false);

    let fallbackTimer: number | null = null;
    let notificationsReloadTimer: number | null = null;
    let pageReloadTimer: number | null = null;
    const cleanups: Array<() => void> = [];

    const auth = computed<Auth | undefined>(() => page.props.auth as Auth | undefined);
    const sharedNotifications = computed<SharedNotifications>(() => {
        return (page.props.notifications as SharedNotifications | undefined) ?? defaultNotifications;
    });

    const hasUnread = computed(() => unreadCount.value > 0);
    const hasNotifications = computed(() => notifications.value.length > 0);
    const connectionLabel = computed(() => {
        switch (connectionStatus.value) {
            case 'connected':
                return 'Live';
            case 'connecting':
                return 'Connecting';
            case 'failed':
                return 'Offline';
            default:
                return 'Standby';
        }
    });

    watch(
        sharedNotifications,
        (value) => {
            notifications.value = value.items;
            unreadCount.value = value.unreadCount;
        },
        { immediate: true, deep: true },
    );

    watch(
        connectionStatus,
        (status) => {
            if (status === 'connected' || status === 'connecting') {
                stopFallbackPolling();

                return;
            }

            startFallbackPolling();
        },
        { immediate: true },
    );

    function queueNotificationsReload(): void {
        if (notificationsReloadTimer !== null) {
            window.clearTimeout(notificationsReloadTimer);
        }

        notificationsReloadTimer = window.setTimeout(() => {
            reloadNotifications();
        }, 120);
    }

    function queuePageReload(): void {
        if (pageReloadTimer !== null) {
            window.clearTimeout(pageReloadTimer);
        }

        pageReloadTimer = window.setTimeout(() => {
            router.reload({
                preserveScroll: true,
                preserveState: true,
            });
        }, 180);
    }

    function reloadNotifications(): void {
        if (isSyncing.value) {
            return;
        }

        isSyncing.value = true;

        router.reload({
            only: ['notifications'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isSyncing.value = false;
            },
        });
    }

    function startFallbackPolling(): void {
        if (fallbackTimer !== null) {
            return;
        }

        fallbackTimer = window.setInterval(() => {
            reloadNotifications();
        }, fallbackPollInterval);
    }

    function stopFallbackPolling(): void {
        if (fallbackTimer === null) {
            return;
        }

        window.clearInterval(fallbackTimer);
        fallbackTimer = null;
    }

    function normalizeRole(role: string): string {
        return role.trim().toLowerCase().replace(/\s+/g, '-');
    }

    function currentModule(): string | null {
        const currentUrl = page.url.split('?')[0];

        if (currentUrl === '/dashboard') {
            return 'dashboard';
        }

        if (currentUrl.startsWith('/inventory/products')) {
            return 'products';
        }

        if (currentUrl.startsWith('/inventory/handover')) {
            return 'handover';
        }

        if (currentUrl.startsWith('/inventory/bookings')) {
            return 'bookings';
        }

        if (currentUrl.startsWith('/inventory/requisitions')) {
            return 'requisitions';
        }

        if (currentUrl.startsWith('/inventory/receiving')) {
            return 'receiving';
        }

        if (currentUrl.startsWith('/inventory/movements')) {
            return 'movements';
        }

        if (currentUrl.startsWith('/inventory/audit-logs')) {
            return 'audit-logs';
        }

        if (currentUrl.startsWith('/settings')) {
            return 'settings';
        }

        return null;
    }

    function modulesForNotification(notification: BroadcastNotificationPayload): string[] {
        switch (notification.category) {
            case 'booking':
                return ['dashboard', 'bookings'];
            case 'requisition':
                return ['dashboard', 'requisitions'];
            case 'inventory':
                return ['dashboard', 'products', 'receiving', 'movements'];
            case 'handover':
                return ['dashboard', 'handover'];
            default:
                return [];
        }
    }

    function shouldReloadCurrentPage(modules: string[]): boolean {
        const module = currentModule();

        if (module === null) {
            return false;
        }

        return modules.includes(module);
    }

    function showToast(notification: BroadcastNotificationPayload): void {
        const title = notification.title ?? 'New notification';
        const message = notification.message ?? '';
        const severity = notification.severity ?? 'info';

        toast[severity](title, {
            description: message,
        });
    }

    function markReadLocally(notificationId: string): void {
        notifications.value = notifications.value.map((notification) => {
            if (notification.id === notificationId) {
                return {
                    ...notification,
                    readAt: notification.readAt ?? new Date().toISOString(),
                };
            }

            return notification;
        });

        unreadCount.value = notifications.value.filter((item) => item.readAt === null).length;
    }

    function markAllReadLocally(): void {
        const timestamp = new Date().toISOString();

        notifications.value = notifications.value.map((notification) => ({
            ...notification,
            readAt: notification.readAt ?? timestamp,
        }));
        unreadCount.value = 0;
    }

    function openNotification(notification: RealtimeNotificationItem): void {
        const visit = () => {
            if (notification.url) {
                router.visit(notification.url, {
                    preserveScroll: true,
                });
            }
        };

        if (notification.readAt !== null) {
            visit();

            return;
        }

        markReadLocally(notification.id);

        router.put(
            read.url({ notification: notification.id }),
            {},
            {
                only: ['notifications'],
                preserveScroll: true,
                preserveState: true,
                onError: () => queueNotificationsReload(),
                onSuccess: visit,
            },
        );
    }

    function markAllAsRead(): void {
        if (!hasUnread.value) {
            return;
        }

        markAllReadLocally();

        router.put(
            readAll.url(),
            {},
            {
                only: ['notifications'],
                preserveScroll: true,
                preserveState: true,
                onError: () => queueNotificationsReload(),
            },
        );
    }

    function registerRealtimeChannels(): void {
        const user = auth.value?.user;

        if (!user || !echoIsConfigured()) {
            return;
        }

        const instance = echo<'reverb'>();
        const userChannelName = `App.Models.User.${user.id}`;
        const userChannel = instance.private(userChannelName);

        const onNotification = (payload: BroadcastNotificationPayload) => {
            showToast(payload);
            queueNotificationsReload();

            if (shouldReloadCurrentPage(modulesForNotification(payload))) {
                queuePageReload();
            }
        };

        userChannel.notification(onNotification);
        cleanups.push(() => {
            userChannel.stopListeningForNotification(onNotification);
            instance.leaveChannel(`private-${userChannelName}`);
        });

        for (const role of auth.value?.roles ?? []) {
            const channelName = `inventory.role.${normalizeRole(role)}`;
            const channel = instance.private(channelName);
            const onRoleUpdate = (payload: InventoryRealtimePayload) => {
                queueNotificationsReload();

                if (shouldReloadCurrentPage(payload.modules ?? [])) {
                    queuePageReload();
                }
            };

            channel.listen('.inventory.updated', onRoleUpdate);
            cleanups.push(() => {
                channel.stopListening('.inventory.updated', onRoleUpdate);
                instance.leaveChannel(`private-${channelName}`);
            });
        }
    }

    onMounted(() => {
        registerRealtimeChannels();
    });

    onUnmounted(() => {
        stopFallbackPolling();

        if (notificationsReloadTimer !== null) {
            window.clearTimeout(notificationsReloadTimer);
        }

        if (pageReloadTimer !== null) {
            window.clearTimeout(pageReloadTimer);
        }

        while (cleanups.length > 0) {
            cleanups.pop()?.();
        }
    });

    return {
        connectionLabel,
        connectionStatus,
        hasNotifications,
        hasUnread,
        isSyncing,
        markAllAsRead,
        notifications,
        openNotification,
        unreadCount,
    };
}
