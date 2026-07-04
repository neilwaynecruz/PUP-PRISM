<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/admin/positions';

type Dept = { id: number; name: string; is_active: boolean };
type Row = { id: number; title: string; code: string; is_active: boolean; department: string | null; users_count: number; assets_count: number };
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; department_id: number; active: boolean | null };
    positions: { data: Row[]; links: PaginationLink[] };
    departments: Dept[];
    can: { create: boolean };
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Admin', href: index() }, { title: 'Positions', href: index() }] } });

const search = ref(props.filters.search ?? '');
const departmentId = ref(props.filters.department_id > 0 ? String(props.filters.department_id) : '');
const active = ref(props.filters.active === null ? '' : props.filters.active ? '1' : '0');
let timer: number | undefined;

watch([search, departmentId, active], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        router.get(index().url, {
            search: search.value || undefined,
            department_id: departmentId.value || undefined,
            active: active.value === '' ? undefined : active.value === '1',
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 250);
});
onBeforeUnmount(() => window.clearTimeout(timer));
</script>

<template>
    <Head title="Positions" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <Heading variant="small" title="Positions" description="Accountability roles linked to departments and users." />
            <Button v-if="can.create" as-child size="sm"><Link :href="create()">New position</Link></Button>
        </div>
        <div class="grid gap-3 sm:grid-cols-4">
            <Input v-model="search" placeholder="Search title, code, department..." class="h-10 rounded-lg sm:col-span-2" />
            <select v-model="departmentId" class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
                <option value="">All departments</option>
                <option v-for="dept in departments" :key="dept.id" :value="String(dept.id)">{{ dept.name }}</option>
            </select>
            <select v-model="active" class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
                <option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option>
            </select>
        </div>
        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left text-xs uppercase text-muted-foreground [&>th]:px-4 [&>th]:py-3">
                    <tr><th>Title</th><th>Code</th><th>Department</th><th>Users</th><th>Assets</th><th>Status</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-for="row in positions.data" :key="row.id" class="[&>td]:px-4 [&>td]:py-3">
                        <td class="font-medium">{{ row.title }}</td><td>{{ row.code }}</td><td>{{ row.department ?? '—' }}</td>
                        <td>{{ row.users_count }}</td><td>{{ row.assets_count }}</td>
                        <td><span class="rounded-full px-2 py-0.5 text-xs" :class="row.is_active ? 'bg-emerald-500/10 text-emerald-700' : 'bg-muted'">{{ row.is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-right"><Button variant="ghost" size="sm" as-child><Link :href="edit(row.id)">Edit</Link></Button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
