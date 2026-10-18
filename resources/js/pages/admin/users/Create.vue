<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import UserManagementController from '@/actions/App/Http/Controllers/Admin/UserManagementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    create as usersCreate,
    index as usersIndex,
} from '@/routes/admin/users';

type PositionOption = {
    id: number;
    label: string;
};

defineProps<{
    roles: string[];
    positions: PositionOption[];
}>();

defineOptions({
    name: 'AdminUserCreatePage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: usersIndex() },
            { title: 'Users', href: usersIndex() },
            { title: 'New', href: usersCreate() },
        ],
    },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    position_id: '',
});

function submit(): void {
    form.post(UserManagementController.store().url);
}
</script>

<template>
    <Head title="New user" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="New user"
            description="Create an internal account and assign its access role."
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Full name</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" v-model="form.email" type="email" required />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <select
                        id="role"
                        v-model="form.role"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                        required
                    >
                        <option value="">Select role</option>
                        <option
                            v-for="role in roles"
                            :key="role"
                            :value="role"
                        >
                            {{ role }}
                        </option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="grid gap-2">
                    <Label for="position_id">Position</Label>
                    <select
                        id="position_id"
                        v-model="form.position_id"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                    >
                        <option value="">No assigned position</option>
                        <option
                            v-for="position in positions"
                            :key="position.id"
                            :value="String(position.id)"
                        >
                            {{ position.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.position_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Temporary password</Label>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        name="password"
                        autocomplete="new-password"
                        required
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        required
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="usersIndex()">Cancel</Link>
                </Button>
                <Button :disabled="form.processing">Create user</Button>
            </div>
        </form>
    </div>
</template>
