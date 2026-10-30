<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import MasterDataImpactPanel from '@/components/admin/MasterDataImpactPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { deactivate, destroy, edit, index } from '@/routes/admin/categories';

const props = defineProps<{
    category: { id: number; name: string; is_active: boolean };
    usage: Record<string, number>;
    impactWarnings: string[];
    can: { delete: boolean; deactivate: boolean };
}>();
defineOptions({
    name: 'CategoryEditPage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: index() },
            { title: 'Categories', href: index() },
            { title: 'Edit', href: edit(0) },
        ],
    },
});
const form = useForm({
    name: props.category.name,
    is_active: props.category.is_active,
});
</script>
<template>
    <Head :title="category.name" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading variant="small" :title="category.name" />
        <MasterDataImpactPanel :warnings="impactWarnings" :usage="usage" />
        <form
            class="grid max-w-xl gap-4 rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            @submit.prevent="
                form.put(CategoryController.update(category.id).url)
            "
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
            <div class="flex flex-wrap justify-between gap-2">
                <div class="flex gap-2">
                    <Button
                        v-if="can.deactivate"
                        type="button"
                        variant="outline"
                        class="min-h-11 sm:min-h-9"
                        @click="router.patch(deactivate(category.id).url)"
                        >Deactivate</Button
                    ><Button
                        v-if="can.delete"
                        type="button"
                        variant="destructive"
                        class="min-h-11 sm:min-h-9"
                        @click="router.delete(destroy(category.id).url)"
                        >Delete</Button
                    >
                </div>
                <div class="flex gap-2">
                    <Button variant="ghost" class="min-h-11 sm:min-h-9" as-child
                        ><Link :href="index()">Back</Link></Button
                    ><Button
                        class="min-h-11 sm:min-h-9"
                        :disabled="form.processing"
                        >Save</Button
                    >
                </div>
            </div>
        </form>
    </div>
</template>
