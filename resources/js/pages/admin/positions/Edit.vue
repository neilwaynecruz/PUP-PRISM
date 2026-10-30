<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PositionController from '@/actions/App/Http/Controllers/Admin/PositionController';
import MasterDataImpactPanel from '@/components/admin/MasterDataImpactPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { deactivate, destroy, edit, index } from '@/routes/admin/positions';

const props = defineProps<{
    position: {
        id: number;
        department_id: number;
        title: string;
        code: string;
        is_active: boolean;
        department: string | null;
    };
    departments: { id: number; name: string; is_active: boolean }[];
    usage: Record<string, number>;
    impactWarnings: string[];
    can: { delete: boolean; deactivate: boolean };
}>();

defineOptions({
    name: 'PositionEditPage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: index() },
            { title: 'Positions', href: index() },
            { title: 'Edit', href: edit(0) },
        ],
    },
});

const form = useForm({
    department_id: String(props.position.department_id),
    title: props.position.title,
    code: props.position.code,
    is_active: props.position.is_active,
});
</script>

<template>
    <Head :title="position.title" />
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            :title="position.title"
            :description="position.department ?? 'Position'"
        />
        <MasterDataImpactPanel :warnings="impactWarnings" :usage="usage" />
        <form
            class="grid max-w-xl gap-4 rounded-xl border border-border/70 bg-card p-5 shadow-xs"
            @submit.prevent="
                form.put(PositionController.update(position.id).url)
            "
        >
            <div class="grid gap-2">
                <Label for="department_id">Department</Label>
                <select
                    id="department_id"
                    v-model="form.department_id"
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm sm:min-h-10"
                    required
                >
                    <option
                        v-for="dept in departments"
                        :key="dept.id"
                        :value="String(dept.id)"
                    >
                        {{ dept.name
                        }}<template v-if="!dept.is_active">
                            [Inactive]</template
                        >
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
            <div class="flex flex-wrap justify-between gap-2">
                <div class="flex gap-2">
                    <Button
                        v-if="can.deactivate"
                        type="button"
                        variant="outline"
                        class="min-h-11 sm:min-h-9"
                        @click="router.patch(deactivate(position.id).url)"
                        >Deactivate</Button
                    >
                    <Button
                        v-if="can.delete"
                        type="button"
                        variant="destructive"
                        class="min-h-11 sm:min-h-9"
                        @click="router.delete(destroy(position.id).url)"
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
