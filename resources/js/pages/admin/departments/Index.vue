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
import { create, edit, index } from '@/routes/admin/departments';

type Row = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
    positions_count: number;
};
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; active: boolean | null };
    departments: { data: Row[]; links: PaginationLink[] };
    can: { create: boolean };
}>();

defineOptions({
    name: 'AdminDepartmentsIndex',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: index() },
            { title: 'Departments', href: index() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const active = ref(
    props.filters.active === null ? '' : props.filters.active ? '1' : '0',
);
let timer: number | undefined;
const selectClass =
    'h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground shadow-xs transition-colors focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/20';

watch([search, active], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        router.get(
            index().url,
            {
                search: search.value || undefined,
                active: active.value === '' ? undefined : active.value === '1',
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 250);
});
onBeforeUnmount(() => window.clearTimeout(timer));
</script>

<template>
    <Head title="Departments" />
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-5 p-4 sm:p-6 lg:p-8"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Departments"
                description="Organizational units for positions and accountability."
            />
            <Button
                v-if="can.create"
                as-child
                size="sm"
                class="rounded-lg shadow-sm"
            >
                <Link :href="create()">
                    <Plus class="mr-1.5 h-4 w-4" />New department
                </Link>
            </Button>
        </div>
        <div
            class="grid gap-3 rounded-xl border border-border/60 bg-card/70 p-3 shadow-xs sm:grid-cols-3 sm:p-4"
        >
            <Input
                v-model="search"
                placeholder="Search name or code..."
                class="h-10 rounded-lg shadow-xs sm:col-span-2"
            />
            <select v-model="active" :class="selectClass">
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
        <AdminTableShell min-width="920px">
            <colgroup>
                <col class="w-[44%]" />
                <col class="w-[16%]" />
                <col class="w-[14%]" />
                <col class="w-[16%]" />
                <col class="w-[10%]" />
            </colgroup>
            <thead
                class="border-b border-border/80 bg-muted/55 text-left text-xs tracking-wider text-muted-foreground uppercase"
            >
                <tr
                    class="[&>th]:px-5 [&>th]:py-4 [&>th]:align-middle [&>th]:font-bold"
                >
                    <th>Name</th>
                    <th>Code</th>
                    <th class="text-center">Positions</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/60">
                <tr
                    v-for="row in departments.data"
                    :key="row.id"
                    class="transition-colors hover:bg-primary/[0.035] dark:hover:bg-primary/[0.06] [&>td]:px-5 [&>td]:py-4 [&>td]:align-middle"
                >
                    <td class="font-medium text-foreground">{{ row.name }}</td>
                    <td>
                        <AdminCodeBadge :value="row.code" />
                    </td>
                    <td class="text-center">
                        <AdminCountBadge :value="row.positions_count" />
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
                <tr v-if="departments.data.length === 0">
                    <td
                        colspan="5"
                        class="px-5 py-10 text-center text-sm text-muted-foreground"
                    >
                        No departments found.
                    </td>
                </tr>
            </tbody>
        </AdminTableShell>
        <AdminPagination :links="departments.links" />
    </div>
</template>
