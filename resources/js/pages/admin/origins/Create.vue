<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import OriginController from '@/actions/App/Http/Controllers/Admin/OriginController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/admin/origins';

defineOptions({ layout: { breadcrumbs: [{ title: 'Admin', href: index() }, { title: 'Origins', href: index() }, { title: 'New', href: create() }] } });
const form = useForm({ name: '', is_active: true });
</script>
<template>
    <Head title="New origin" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading variant="small" title="New origin" />
        <form class="grid max-w-xl gap-4" @submit.prevent="form.post(OriginController.store().url)">
            <div class="grid gap-2"><Label for="name">Name</Label><Input id="name" v-model="form.name" required /><InputError :message="form.errors.name" /></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-input" /> Active</label>
            <div class="flex justify-end gap-2"><Button variant="ghost" as-child><Link :href="index()">Cancel</Link></Button><Button :disabled="form.processing">Create</Button></div>
        </form>
    </div>
</template>
