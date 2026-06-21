import { usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BarChart3,
    BookOpen,
    FileText,
    History,
    LayoutGrid,
    Package,
    Settings,
    Truck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/inventory/audit-logs';
import { index as bookingsIndex } from '@/routes/inventory/bookings';
import { index as handoverIndex } from '@/routes/inventory/handover';
import { index as movementsIndex } from '@/routes/inventory/movements';
import {
    create as productsCreate,
    index as productsIndex,
} from '@/routes/inventory/products';
import { index as receivingIndex } from '@/routes/inventory/receiving';
import { index as requisitionsIndex } from '@/routes/inventory/requisitions';
import type { Auth, AuthPermissions, NavItem } from '@/types';

type InventoryNavItem = NavItem & {
    permission?: keyof AuthPermissions;
};

export type InventorySearchItem = InventoryNavItem & {
    description: string;
    keywords: string[];
};

const defaultPermissions: AuthPermissions = {
    viewProducts: false,
    createProducts: false,
    viewHandover: false,
    viewBookings: false,
    viewRequisitions: false,
    viewReceiving: false,
    viewMovements: false,
    viewAuditLogs: false,
};

const mainNavigationItems: InventoryNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Products',
        href: productsIndex(),
        icon: Package,
        permission: 'viewProducts',
    },
    {
        title: 'Handover',
        href: handoverIndex(),
        icon: ArrowLeftRight,
        permission: 'viewHandover',
    },
    {
        title: 'Bookings',
        href: bookingsIndex(),
        icon: BookOpen,
        permission: 'viewBookings',
    },
    {
        title: 'Requisitions',
        href: requisitionsIndex(),
        icon: FileText,
        permission: 'viewRequisitions',
    },
    {
        title: 'Receiving',
        href: receivingIndex(),
        icon: Truck,
        permission: 'viewReceiving',
    },
    {
        title: 'Stock movements',
        href: movementsIndex(),
        icon: BarChart3,
        permission: 'viewMovements',
    },
    {
        title: 'Audit logs',
        href: auditLogsIndex(),
        icon: History,
        permission: 'viewAuditLogs',
    },
];

const footerNavigationItems: NavItem[] = [
    {
        title: 'Settings',
        href: '/settings/profile',
        icon: Settings,
    },
];

const searchNavigationItems: InventorySearchItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        description: 'Overview, alerts, and operational widgets',
        keywords: ['dashboard', 'overview', 'alerts'],
    },
    {
        title: 'Products',
        href: productsIndex(),
        icon: Package,
        description: 'Browse inventory items and stock levels',
        keywords: ['products', 'inventory', 'stock', 'catalog'],
        permission: 'viewProducts',
    },
    {
        title: 'New Product',
        href: productsCreate(),
        icon: Package,
        description: 'Create a new product record',
        keywords: ['new', 'create', 'product'],
        permission: 'createProducts',
    },
    {
        title: 'Bookings',
        href: bookingsIndex(),
        icon: BookOpen,
        description: 'Request, review, and track asset bookings',
        keywords: ['bookings', 'calendar', 'schedule', 'reserve'],
        permission: 'viewBookings',
    },
    {
        title: 'Handover',
        href: handoverIndex(),
        icon: ArrowLeftRight,
        description: 'Transfer accountable assets between personnel',
        keywords: ['handover', 'transfer', 'asset', 'verification'],
        permission: 'viewHandover',
    },
    {
        title: 'Requisitions',
        href: requisitionsIndex(),
        icon: FileText,
        description: 'Submit and process issuance requests',
        keywords: ['requisitions', 'issue', 'requests'],
        permission: 'viewRequisitions',
    },
    {
        title: 'Receiving',
        href: receivingIndex(),
        icon: Truck,
        description: 'Receive new stock and assets',
        keywords: ['receiving', 'deliveries', 'stock in'],
        permission: 'viewReceiving',
    },
    {
        title: 'Stock movements',
        href: movementsIndex(),
        icon: BarChart3,
        description: 'Review stock movement history and audit trails',
        keywords: ['movements', 'stock', 'audit', 'history'],
        permission: 'viewMovements',
    },
    {
        title: 'Audit logs',
        href: auditLogsIndex(),
        icon: History,
        description: 'Review operational changes and approval history',
        keywords: ['audit', 'logs', 'history', 'changes'],
        permission: 'viewAuditLogs',
    },
    {
        title: 'Settings',
        href: '/settings/profile',
        icon: Settings,
        description: 'Profile, appearance, and account settings',
        keywords: ['settings', 'profile', 'security', 'appearance'],
    },
];

function filterByPermission<T extends { permission?: keyof AuthPermissions }>(
    items: T[],
    permissions: AuthPermissions,
): T[] {
    return items.filter((item) => {
        if (!item.permission) {
            return true;
        }

        return permissions[item.permission];
    });
}

export function useInventoryNavigation() {
    const page = usePage();

    const permissions = computed<AuthPermissions>(() => {
        const auth = page.props.auth as Auth | undefined;

        return auth?.permissions ?? defaultPermissions;
    });

    const mainNavItems = computed(() =>
        filterByPermission(mainNavigationItems, permissions.value),
    );

    const searchItems = computed(() =>
        filterByPermission(searchNavigationItems, permissions.value),
    );

    return {
        footerNavItems: footerNavigationItems,
        mainNavItems,
        permissions,
        searchItems,
    };
}
