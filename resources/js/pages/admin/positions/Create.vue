<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PositionController from '@/actions/App/Http/Controllers/Admin/PositionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/admin/positions';

defineProps<{ departments: { id: number; name: string }[] }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Admin', href: index() }, { title: 'Positions', href: index() }, { title: 'New', href: create() }] } });

const form = useForm({ department_id: '', title: '', code: '', is_active: true });
</script>

<template>
    <Head title="New position" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading variant="small" title="New position" description="Create a position under an active department." />
        <form class="grid max-w-xl gap-4" @submit.prevent="form.post(PositionController.store().url)">
            <div class="grid gap-2"><Label for="department_id">Department</Label>
                <select id="department_id" v-model="form.department_id" class="h-10 rounded-lg border border-input bg-background px-3 text-sm" required>
                    <option value="">Select department</option><option v-for="dept in departments" :key="dept.id" :value="String(dept.id)">{{ dept.name }}</option>
                </select><InputError :message="form.errors.department_id" /></div>
            <div class="grid gap-2"><Label for="title">Title</Label><Input id="title" v-model="form.title" required /><InputError :message="form.errors.title" /></div>
            <div class="grid gap-2"><Label for="code">Code</Label><Input id="code" v-model="form.code" required /><InputError :message="form.errors.code" /></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-input" /> Active</label>
            <div class="flex justify-end gap-2"><Button variant="ghost" as-child><Link :href="index()">Cancel</Link></Button><Button :disabled="form.processing">Create</Button></div>
        </form>
    </div>
</template>
