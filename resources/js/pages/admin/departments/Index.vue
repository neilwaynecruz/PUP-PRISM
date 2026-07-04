<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/admin/departments';

type Row = { id: number; name: string; code: string | null; is_active: boolean; positions_count: number };
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; active: boolean | null };
    departments: { data: Row[]; links: PaginationLink[] };
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Admin', href: index() }, { title: 'Departments', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const active = ref(props.filters.active === null ? '' : props.filters.active ? '1' : '0');
let timer: number | undefined;

watch([search, active], () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        router.get(index().url, {
            search: search.value || undefined,
            active: active.value === '' ? undefined : active.value === '1',
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 250);
});
onBeforeUnmount(() => window.clearTimeout(timer));
</script>

<template>
    <Head title="Departments" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <Heading variant="small" title="Departments" description="Organizational units for positions and accountability." />
            <Button v-if="can.create" as-child size="sm"><Link :href="create()">New department</Link></Button>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <Input v-model="search" placeholder="Search name or code..." class="h-10 rounded-lg sm:col-span-2" />
            <select v-model="active" class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left text-xs uppercase text-muted-foreground [&>th]:px-4 [&>th]:py-3">
                    <tr><th>Name</th><th>Code</th><th>Positions</th><th>Status</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-for="row in departments.data" :key="row.id" class="[&>td]:px-4 [&>td]:py-3">
                        <td class="font-medium">{{ row.name }}</td>
                        <td>{{ row.code ?? '—' }}</td>
                        <td>{{ row.positions_count }}</td>
                        <td><span class="rounded-full px-2 py-0.5 text-xs" :class="row.is_active ? 'bg-emerald-500/10 text-emerald-700' : 'bg-muted'">{{ row.is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-right"><Button variant="ghost" size="sm" as-child><Link :href="edit(row.id)">Edit</Link></Button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="departments.links.length" class="flex flex-wrap justify-center gap-1">
            <Button v-for="(link, i) in departments.links" :key="i" variant="ghost" size="sm" as-child :disabled="!link.url" :class="link.active ? 'bg-primary/10 text-primary' : ''">
                <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state><span v-html="link.label" /></Link>
                <span v-else v-html="link.label" />
            </Button>
        </div>
    </div>
</template>
