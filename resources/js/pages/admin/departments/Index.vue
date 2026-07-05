<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/admin/departments';
import { Plus } from 'lucide-vue-next';

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
            <Button v-if="can.create" as-child size="sm" class="rounded-lg shadow-sm">
                <Link :href="create()">
                    <Plus class="mr-1.5 h-4 w-4" />New department
                </Link>
            </Button>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <Input v-model="search" placeholder="Search name or code..." class="h-10 rounded-lg sm:col-span-2" />
            <select v-model="active" class="h-10 rounded-lg border border-input bg-background px-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring">
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
        <div class="overflow-x-auto rounded-xl border border-border/60 bg-card shadow-sm">
            <table class="w-full min-w-[900px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[45%]" />
                    <col class="w-[15%]" />
                    <col class="w-[15%]" />
                    <col class="w-[15%]" />
                    <col class="w-[10%]" />
                </colgroup>
                <thead class="bg-muted/60 border-b border-border/80 text-left text-xs uppercase tracking-wider [&>th]:px-4 [&>th]:py-3.5 [&>th]:text-slate-900 dark:[&>th]:text-slate-100 [&>th]:font-bold">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="text-center">Positions</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-for="row in departments.data" :key="row.id" class="transition-colors hover:bg-muted/30 [&>td]:px-4 [&>td]:py-3.5">
                        <td class="font-medium text-foreground">{{ row.name }}</td>
                        <td>
                            <span class="font-mono text-xs text-muted-foreground bg-muted/40 px-2 py-0.5 rounded border border-border/30">
                                {{ row.code ?? '—' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="font-mono text-xs font-medium text-muted-foreground bg-muted/30 rounded-md px-2.5 py-0.5">
                                {{ row.positions_count }}
                            </span>
                        </td>
                        <td>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="row.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-700 dark:bg-rose-400/10 dark:text-rose-400'">
                                <span class="h-1.5 w-1.5 rounded-full" :class="row.is_active ? 'bg-emerald-500 dark:bg-emerald-400' : 'bg-rose-500 dark:bg-rose-400'" />
                                {{ row.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <Button variant="ghost" size="sm" class="h-8 hover:bg-primary/10 hover:text-primary text-xs rounded-lg" as-child>
                                <Link :href="edit(row.id)">Edit</Link>
                            </Button>
                        </td>
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
