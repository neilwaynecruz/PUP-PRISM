<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DepartmentController from '@/actions/App/Http/Controllers/Admin/DepartmentController';
import MasterDataImpactPanel from '@/components/admin/MasterDataImpactPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { deactivate, destroy, edit, index } from '@/routes/admin/departments';

const props = defineProps<{
    department: { id: number; name: string; code: string | null; is_active: boolean };
    usage: Record<string, number>;
    impactWarnings: string[];
    can: { delete: boolean; deactivate: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Admin', href: index() }, { title: 'Departments', href: index() }, { title: 'Edit', href: edit(0) }] },
});

const form = useForm({
    name: props.department.name,
    code: props.department.code ?? '',
    is_active: props.department.is_active,
});

function submit(): void {
    form.put(DepartmentController.update(props.department.id).url);
}
</script>

<template>
    <Head :title="department.name" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading variant="small" :title="department.name" description="Update department details or deactivate when no longer used." />
        <MasterDataImpactPanel :warnings="impactWarnings" :usage="usage" />
        <form class="grid max-w-xl gap-4" @submit.prevent="submit">
            <div class="grid gap-2"><Label for="name">Name</Label><Input id="name" v-model="form.name" required /><InputError :message="form.errors.name" /></div>
            <div class="grid gap-2"><Label for="code">Code</Label><Input id="code" v-model="form.code" /><InputError :message="form.errors.code" /></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-input" /> Active</label>
            <div class="flex flex-wrap justify-between gap-2">
                <div class="flex gap-2">
                    <Button v-if="can.deactivate" type="button" variant="outline" @click="router.patch(deactivate(department.id).url)">Deactivate</Button>
                    <Button v-if="can.delete" type="button" variant="destructive" @click="router.delete(destroy(department.id).url)">Delete</Button>
                </div>
                <div class="flex gap-2"><Button variant="ghost" as-child><Link :href="index()">Back</Link></Button><Button :disabled="form.processing">Save</Button></div>
            </div>
        </form>
    </div>
</template>
