<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/admin/categories';

defineOptions({
    name: 'CategoryCreatePage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: index() },
            { title: 'Categories', href: index() },
            { title: 'New', href: create() },
        ],
    },
});
const form = useForm({ name: '', is_active: true });
</script>
<template>
    <Head title="New category" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading variant="small" title="New category" />
        <form
            class="grid max-w-xl gap-4 rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            @submit.prevent="form.post(CategoryController.store().url)"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label
                ><Input
                    id="name"
                    v-model="form.name"
                    required
                    class="min-h-11 sm:min-h-9"
                /><InputError :message="form.errors.name" />
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
