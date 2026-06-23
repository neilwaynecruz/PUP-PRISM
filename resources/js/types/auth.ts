export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthPermissions = {
    viewProducts: boolean;
    createProducts: boolean;
    viewHandover: boolean;
    viewBookings: boolean;
    viewRequisitions: boolean;
    viewReceiving: boolean;
    viewSuppliers: boolean;
    createSuppliers: boolean;
    viewPurchaseOrders: boolean;
    createPurchaseOrders: boolean;
    viewMovements: boolean;
    viewAuditLogs: boolean;
};

export type Auth = {
    user: User;
    roles: string[];
    permissions: AuthPermissions;
};

export type SessionMeta = {
    lifetimeMinutes: number;
    warningMinutes: number;
    keepAliveUrl: string;
    loginUrl: string;
};

export type RealtimeNotificationItem = {
    id: string;
    type: string;
    category: string;
    severity: 'success' | 'info' | 'warning' | 'error';
    title: string;
    message: string;
    url: string | null;
    createdAt: string | null;
    readAt: string | null;
    data: Record<string, unknown>;
};

export type SharedNotifications = {
    unreadCount: number;
    items: RealtimeNotificationItem[];
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
