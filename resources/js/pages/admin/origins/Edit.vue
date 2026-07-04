<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import OriginController from '@/actions/App/Http/Controllers/Admin/OriginController';
import MasterDataImpactPanel from '@/components/admin/MasterDataImpactPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { deactivate, destroy, edit, index } from '@/routes/admin/origins';

const props = defineProps<{ origin: { id: number; name: string; is_active: boolean }; usage: Record<string, number>; impactWarnings: string[]; can: { delete: boolean; deactivate: boolean } }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Admin', href: index() }, { title: 'Origins', href: index() }, { title: 'Edit', href: edit(0) }] } });
const form = useForm({ name: props.origin.name, is_active: props.origin.is_active });
</script>
<template>
    <Head :title="origin.name" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading variant="small" :title="origin.name" />
        <MasterDataImpactPanel :warnings="impactWarnings" :usage="usage" />
        <form class="grid max-w-xl gap-4" @submit.prevent="form.put(OriginController.update(origin.id).url)">
            <div class="grid gap-2"><Label for="name">Name</Label><Input id="name" v-model="form.name" required /><InputError :message="form.errors.name" /></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-input" /> Active</label>
            <div class="flex flex-wrap justify-between gap-2">
                <div class="flex gap-2"><Button v-if="can.deactivate" type="button" variant="outline" @click="router.patch(deactivate(origin.id).url)">Deactivate</Button><Button v-if="can.delete" type="button" variant="destructive" @click="router.delete(destroy(origin.id).url)">Delete</Button></div>
                <div class="flex gap-2"><Button variant="ghost" as-child><Link :href="index()">Back</Link></Button><Button :disabled="form.processing">Save</Button></div>
            </div>
        </form>
    </div>
</template>
