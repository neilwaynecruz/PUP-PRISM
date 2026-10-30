<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import AdminCodeBadge from '@/components/admin/AdminCodeBadge.vue';
import AdminCountBadge from '@/components/admin/AdminCountBadge.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTableShell from '@/components/admin/AdminTableShell.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/admin/positions';

type Dept = { id: number; name: string; is_active: boolean };
type Row = {
    id: number;
    title: string;
    code: string;
    is_active: boolean;
    department: string | null;
    users_count: number;
    assets_count: number;
};
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; department_id: number; active: boolean | null };
    positions: { data: Row[]; links: PaginationLink[] };
    departments: Dept[];
    can: { create: boolean };
}>();

defineOptions({
    name: 'AdminPositionsIndex',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: index() },
            { title: 'Positions', href: index() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const departmentId = ref(
    props.filters.department_id > 0 ? String(props.filters.department_id) : '',
);
const active = ref(
    props.filters.active === null ? '' : props.filters.active ? '1' : '0',
);
let timer: number | undefined;
const selectClass =
    'h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground shadow-xs transition-colors focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/20';

watch([search, departmentId, active], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        router.get(
            index().url,
            {
                search: search.value || undefined,
                department_id: departmentId.value || undefined,
                active: active.value === '' ? undefined : active.value === '1',
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 250);
});
onBeforeUnmount(() => window.clearTimeout(timer));
</script>

<template>
    <Head title="Positions" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-5 p-4 sm:p-6 lg:p-8"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Positions"
                description="Accountability roles linked to departments and users."
            />
            <Button
                v-if="can.create"
                as-child
                size="sm"
                class="rounded-lg shadow-sm"
            >
                <Link :href="create()">
                    <Plus class="mr-1.5 h-4 w-4" />New position
                </Link>
            </Button>
        </div>
        <div
            class="grid gap-3 rounded-xl border border-border/60 bg-card/70 p-3 shadow-xs sm:grid-cols-4 sm:p-4"
        >
            <label
                class="grid gap-1.5 text-xs font-medium text-muted-foreground sm:col-span-2"
            >
                Search positions
                <Input
                    v-model="search"
                    name="position_search"
                    autocomplete="off"
                    placeholder="Title, code, or department…"
                    class="min-h-11 rounded-lg shadow-xs sm:min-h-10"
                />
            </label>
            <label
                class="grid gap-1.5 text-xs font-medium text-muted-foreground"
            >
                Department
                <select
                    v-model="departmentId"
                    name="position_department"
                    autocomplete="off"
                    :class="[selectClass, 'min-h-11 sm:min-h-10']"
                >
                    <option value="">All departments</option>
                    <option
                        v-for="dept in departments"
                        :key="dept.id"
                        :value="String(dept.id)"
                    >
                        {{ dept.name }}
                    </option>
                </select>
            </label>
            <label
                class="grid gap-1.5 text-xs font-medium text-muted-foreground"
            >
                Status
                <select
                    v-model="active"
                    name="position_status"
                    autocomplete="off"
                    :class="[selectClass, 'min-h-11 sm:min-h-10']"
                >
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </label>
        </div>
        <div v-if="positions.data.length" class="grid gap-3 md:hidden">
            <article
                v-for="row in positions.data"
                :key="`mobile-${row.id}`"
                class="rounded-xl border border-border/70 bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold break-words">
                            {{ row.title }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ row.department ?? 'No department assigned' }}
                        </p>
                    </div>
                    <AdminStatusBadge :active="row.is_active" />
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Code</dt>
                        <dd class="mt-1">
                            <AdminCodeBadge :value="row.code" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Users</dt>
                        <dd class="mt-1">
                            <AdminCountBadge :value="row.users_count" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Assets</dt>
                        <dd class="mt-1">
                            <AdminCountBadge :value="row.assets_count" />
                        </dd>
                    </div>
                </dl>
                <Button variant="outline" class="mt-4 w-full" as-child>
                    <Link :href="edit(row.id)">Edit Position</Link>
                </Button>
            </article>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground md:hidden"
        >
            No positions found.
        </div>
        <AdminTableShell class="hidden md:block" min-width="1160px">
            <colgroup>
                <col class="w-[26%]" />
                <col class="w-[13%]" />
                <col class="w-[25%]" />
                <col class="w-[8%]" />
                <col class="w-[8%]" />
                <col class="w-[12%]" />
                <col class="w-[8%]" />
            </colgroup>
            <thead
                class="border-b border-border/80 bg-muted/55 text-left text-xs tracking-wider text-muted-foreground uppercase"
            >
                <tr
                    class="[&>th]:px-5 [&>th]:py-4 [&>th]:align-middle [&>th]:font-bold"
                >
                    <th>Title</th>
                    <th>Code</th>
                    <th>Department</th>
                    <th class="text-center">Users</th>
                    <th class="text-center">Assets</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/60">
                <tr
                    v-for="row in positions.data"
                    :key="row.id"
                    class="transition-colors hover:bg-primary/[0.035] dark:hover:bg-primary/[0.06] [&>td]:px-5 [&>td]:py-4 [&>td]:align-middle"
                >
                    <td class="font-medium text-foreground">{{ row.title }}</td>
                    <td>
                        <AdminCodeBadge :value="row.code" />
                    </td>
                    <td class="text-muted-foreground">
                        {{ row.department ?? '—' }}
                    </td>
                    <td class="text-center">
                        <AdminCountBadge :value="row.users_count" />
                    </td>
                    <td class="text-center">
                        <AdminCountBadge :value="row.assets_count" />
                    </td>
                    <td>
                        <AdminStatusBadge :active="row.is_active" />
                    </td>
                    <td class="text-right">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 rounded-lg px-3 text-xs font-semibold text-muted-foreground hover:bg-primary/10 hover:text-primary"
                            as-child
                        >
                            <Link :href="edit(row.id)">Edit</Link>
                        </Button>
                    </td>
                </tr>
                <tr v-if="positions.data.length === 0">
                    <td
                        colspan="7"
                        class="px-5 py-10 text-center text-sm text-muted-foreground"
                    >
                        No positions found.
                    </td>
                </tr>
            </tbody>
        </AdminTableShell>
        <AdminPagination :links="positions.links" />
    </div>
</template>
