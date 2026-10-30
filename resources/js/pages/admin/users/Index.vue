<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Plus, ShieldCheck } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTableShell from '@/components/admin/AdminTableShell.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create as usersCreate,
    edit as usersEdit,
    index as usersIndex,
} from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    position: string | null;
    is_active: boolean;
    invited_at: string | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    total: number;
};

type RoleDescription = { summary: string; abilities: string[] };

const props = defineProps<{
    filters: {
        search: string;
        role: string;
        active: boolean | null;
        department_id: number;
    };
    users: Paginated<UserRow>;
    roles: string[];
    roleDescriptions: Record<string, RoleDescription>;
    departments: { id: number; name: string }[];
    can: {
        create: boolean;
    };
}>();

defineOptions({
    name: 'AdminUserIndexPage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: usersIndex() },
            { title: 'Users', href: usersIndex() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const active = ref(
    props.filters.active === null ? '' : props.filters.active ? '1' : '0',
);
const departmentId = ref(
    props.filters.department_id > 0 ? String(props.filters.department_id) : '',
);
const showRoleGuide = ref(false);

const selectClass =
    'h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground shadow-xs transition-colors focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/20';

const hasFilters = computed(() =>
    Boolean(search.value || role.value || active.value || departmentId.value),
);

let refreshTimer: number | undefined;

watch([search, role, active, departmentId], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            usersIndex().url,
            {
                search: search.value || undefined,
                role: role.value || undefined,
                active: active.value === '' ? undefined : active.value === '1',
                department_id: departmentId.value || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }, 250);
});

onBeforeUnmount(() => {
    window.clearTimeout(refreshTimer);
});

function clearFilters(): void {
    search.value = '';
    role.value = '';
    active.value = '';
    departmentId.value = '';
}

function formatProvisionedAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const roleBadgeClass: Record<string, string> = {
    Admin: 'border-primary/25 bg-primary/10 text-primary',
    'Supply Head':
        'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300',
    'Property Custodian':
        'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300',
};
</script>

<template>
    <Head title="Users" />

    <div class="mx-auto flex w-full max-w-400 flex-col gap-5 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Users"
                description="Create accounts, assign roles and positions, and control who can sign in."
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-lg"
                    :aria-expanded="showRoleGuide"
                    aria-controls="role-guide"
                    @click="showRoleGuide = !showRoleGuide"
                >
                    <ShieldCheck class="mr-1.5 h-4 w-4" />
                    What can each role do?
                    <ChevronDown
                        class="ml-1 h-3.5 w-3.5 transition-transform"
                        :class="{ 'rotate-180': showRoleGuide }"
                    />
                </Button>
                <Button
                    v-if="can.create"
                    as-child
                    size="sm"
                    class="rounded-lg shadow-sm"
                >
                    <Link :href="usersCreate()">
                        <Plus class="mr-1.5 h-4 w-4" />New user
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="showRoleGuide"
            id="role-guide"
            class="grid gap-3 md:grid-cols-3"
        >
            <div
                v-for="(description, roleName) in roleDescriptions"
                :key="roleName"
                class="rounded-xl border border-border/60 bg-card p-4 shadow-xs"
            >
                <span
                    class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                    :class="roleBadgeClass[roleName] ?? ''"
                    >{{ roleName }}</span
                >
                <p class="mt-2 text-sm font-medium">
                    {{ description.summary }}
                </p>
                <ul
                    class="mt-1.5 list-disc space-y-0.5 pl-4 text-xs text-muted-foreground"
                >
                    <li v-for="ability in description.abilities" :key="ability">
                        {{ ability }}
                    </li>
                </ul>
            </div>
        </div>

        <div
            class="grid gap-3 rounded-xl border border-border/60 bg-card/70 p-3 shadow-xs sm:grid-cols-2 sm:p-4 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]"
        >
            <Input
                v-model="search"
                type="search"
                aria-label="Search users"
                placeholder="Search name, email, or role"
                class="h-10 rounded-lg shadow-xs sm:col-span-2 lg:col-span-1"
            />
            <select v-model="role" aria-label="Role" :class="selectClass">
                <option value="">All roles</option>
                <option
                    v-for="roleOption in roles"
                    :key="roleOption"
                    :value="roleOption"
                >
                    {{ roleOption }}
                </option>
            </select>
            <select
                v-model="departmentId"
                aria-label="Department"
                :class="selectClass"
            >
                <option value="">All departments</option>
                <option
                    v-for="department in departments"
                    :key="department.id"
                    :value="String(department.id)"
                >
                    {{ department.name }}
                </option>
            </select>
            <select v-model="active" aria-label="Status" :class="selectClass">
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <Button
                v-if="hasFilters"
                variant="ghost"
                class="h-10"
                @click="clearFilters"
            >
                Clear
            </Button>
        </div>

        <section
            class="grid gap-3 md:hidden"
            aria-label="User records"
            data-testid="users-mobile-list"
        >
            <div
                v-if="users.data.length === 0"
                class="rounded-xl border border-dashed border-border bg-card px-4 py-10 text-center text-sm text-muted-foreground"
            >
                {{
                    hasFilters
                        ? 'No users match these filters.'
                        : 'No users yet.'
                }}
            </div>

            <article
                v-for="user in users.data"
                :key="user.id"
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
                :class="{ 'opacity-75': !user.is_active }"
            >
                <div class="flex min-w-0 items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-semibold">
                            {{ user.name }}
                        </h2>
                        <p
                            class="mt-0.5 text-xs break-all text-muted-foreground"
                        >
                            {{ user.email }}
                        </p>
                    </div>
                    <AdminStatusBadge
                        class="shrink-0"
                        :active="user.is_active"
                    />
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                    <div class="min-w-0">
                        <dt class="text-muted-foreground">Role</dt>
                        <dd class="mt-1">
                            <span
                                v-if="user.role"
                                class="inline-flex max-w-full rounded-full border px-2.5 py-1 font-semibold whitespace-normal"
                                :class="roleBadgeClass[user.role] ?? ''"
                            >
                                {{ user.role }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-muted-foreground">Created</dt>
                        <dd class="mt-1 font-medium">
                            {{ formatProvisionedAt(user.invited_at) }}
                        </dd>
                    </div>
                    <div class="col-span-2 min-w-0">
                        <dt class="text-muted-foreground">Position</dt>
                        <dd class="mt-1 font-medium break-words">
                            {{ user.position ?? 'No assigned position' }}
                        </dd>
                    </div>
                </dl>

                <Button
                    variant="outline"
                    class="mt-4 min-h-11 w-full rounded-lg font-semibold"
                    as-child
                >
                    <Link
                        :href="usersEdit(user.id)"
                        :aria-label="`Edit ${user.name}`"
                    >
                        Edit user
                    </Link>
                </Button>
            </article>
        </section>

        <div class="hidden md:block">
            <AdminTableShell min-width="860px">
                <colgroup>
                    <col class="w-[30%]" />
                    <col class="w-[16%]" />
                    <col class="w-[28%]" />
                    <col class="w-[11%]" />
                    <col class="w-[9%]" />
                    <col class="w-[6%]" />
                </colgroup>
                <thead
                    class="border-b border-border/80 bg-muted/55 text-left text-xs tracking-wider text-muted-foreground uppercase"
                >
                    <tr class="[&>th]:px-5 [&>th]:py-3.5 [&>th]:font-bold">
                        <th>User</th>
                        <th>Role</th>
                        <th>Position</th>
                        <th>Created</th>
                        <th>Status</th>
                        <th class="text-right">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="6"
                            class="px-5 py-10 text-center text-sm text-muted-foreground"
                        >
                            {{
                                hasFilters
                                    ? 'No users match these filters.'
                                    : 'No users yet.'
                            }}
                        </td>
                    </tr>

                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="transition-colors hover:bg-primary/[0.035] dark:hover:bg-primary/6 [&>td]:px-5 [&>td]:py-3.5 [&>td]:align-middle"
                        :class="{ 'opacity-75': !user.is_active }"
                    >
                        <td>
                            <div class="truncate font-medium">
                                {{ user.name }}
                            </div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ user.email }}
                            </div>
                        </td>
                        <td>
                            <span
                                v-if="user.role"
                                class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
                                :class="roleBadgeClass[user.role] ?? ''"
                                >{{ user.role }}</span
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td
                            class="truncate text-muted-foreground"
                            :title="user.position ?? undefined"
                        >
                            {{ user.position ?? 'No assigned position' }}
                        </td>
                        <td class="text-xs text-muted-foreground">
                            {{ formatProvisionedAt(user.invited_at) }}
                        </td>
                        <td>
                            <AdminStatusBadge :active="user.is_active" />
                        </td>
                        <td class="text-right">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 rounded-lg px-3 text-xs font-semibold text-muted-foreground hover:bg-primary/10 hover:text-primary"
                                as-child
                            >
                                <Link
                                    :href="usersEdit(user.id)"
                                    :aria-label="`Edit ${user.name}`"
                                    >Edit</Link
                                >
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </AdminTableShell>
        </div>

        <AdminPagination :links="users.links" />
    </div>
</template>
