<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
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
};

const props = defineProps<{
    filters: {
        search: string;
        role: string;
        active: boolean | null;
    };
    users: Paginated<UserRow>;
    roles: string[];
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

let refreshTimer: number | undefined;

watch([search, role, active], () => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.get(
            usersIndex().url,
            {
                search: search.value || undefined,
                role: role.value || undefined,
                active: active.value === '' ? undefined : active.value === '1',
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

function formatProvisionedAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                title="Users"
                description="Provision and manage internal user accounts."
            />

            <Button v-if="can.create" as-child size="sm" class="rounded-lg">
                <Link :href="usersCreate()">New user</Link>
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Input
                v-model="search"
                placeholder="Search name, email, or role..."
                class="h-10 rounded-lg lg:col-span-2"
            />

            <select
                v-model="role"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
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
                v-model="active"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
            >
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr
                        class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase [&>th]:px-4 [&>th]:py-3"
                    >
                        <th>User</th>
                        <th>Role</th>
                        <th>Position</th>
                        <th>Provisioned</th>
                        <th class="text-right">Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            No users found.
                        </td>
                    </tr>

                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3"
                    >
                        <td>
                            <div class="font-medium">{{ user.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </div>
                        </td>
                        <td>{{ user.role ?? '—' }}</td>
                        <td class="text-muted-foreground">
                            {{ user.position ?? 'No assigned position' }}
                        </td>
                        <td class="text-muted-foreground">
                            {{ formatProvisionedAt(user.invited_at) }}
                        </td>
                        <td class="text-right">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="
                                    user.is_active
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="usersEdit(user.id)">Edit</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="users.links.length"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <Button
                v-for="(link, index) in users.links"
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
