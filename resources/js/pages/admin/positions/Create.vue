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
defineOptions({
    name: 'PositionCreatePage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: index() },
            { title: 'Positions', href: index() },
            { title: 'New', href: create() },
        ],
    },
});

const form = useForm({
    department_id: '',
    title: '',
    code: '',
    is_active: true,
});
</script>

<template>
    <Head title="New position" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="New position"
            description="Create a position under an active department."
        />
        <form
            class="grid max-w-xl gap-4 rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            @submit.prevent="form.post(PositionController.store().url)"
        >
            <div class="grid gap-2">
                <Label for="department_id">Department</Label>
                <select
                    id="department_id"
                    v-model="form.department_id"
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm sm:min-h-10"
                    required
                >
                    <option value="">Select department</option>
                    <option
                        v-for="dept in departments"
                        :key="dept.id"
                        :value="String(dept.id)"
                    >
                        {{ dept.name }}
                    </option></select
                ><InputError :message="form.errors.department_id" />
            </div>
            <div class="grid gap-2">
                <Label for="title">Title</Label
                ><Input
                    id="title"
                    v-model="form.title"
                    required
                    class="min-h-11 sm:min-h-9"
                /><InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="code">Code</Label
                ><Input
                    id="code"
                    v-model="form.code"
                    required
                    class="min-h-11 sm:min-h-9"
                /><InputError :message="form.errors.code" />
            </div>
            <label
                class="flex min-h-11 items-center gap-2.5 rounded-lg border border-border/60 bg-muted/20 px-3 text-sm font-medium sm:min-h-9"
                ><input
                    v-model="form.is_active"
                    type="checkbox"
                    class="size-4 rounded border-input accent-primary"
                />
                Active</label
            >
            <div class="flex justify-end gap-2">
                <Button variant="ghost" class="min-h-11 sm:min-h-9" as-child
                    ><Link :href="index()">Cancel</Link></Button
                ><Button class="min-h-11 sm:min-h-9" :disabled="form.processing"
                    >Create</Button
                >
            </div>
        </form>
    </div>
</template>
