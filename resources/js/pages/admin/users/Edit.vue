<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserManagementController from '@/actions/App/Http/Controllers/Admin/UserManagementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    edit as usersEdit,
    index as usersIndex,
} from '@/routes/admin/users';

type PositionOption = {
    id: number;
    label: string;
};

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    position_id: number | null;
    position: string | null;
    is_active: boolean;
    invited_at: string | null;
    is_self: boolean;
    is_last_active_admin: boolean;
};

const props = defineProps<{
    user: ManagedUser;
    roles: string[];
    positions: PositionOption[];
    can: {
        deactivate: boolean;
    };
}>();

defineOptions({
    name: 'AdminUserEditPage',
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: usersIndex() },
            { title: 'Users', href: usersIndex() },
            { title: 'Edit', href: usersEdit(0) },
        ],
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    role: props.user.role ?? '',
    position_id: props.user.position_id !== null ? String(props.user.position_id) : '',
});

const deactivateDialogOpen = ref(false);
const deactivating = ref(false);

const deactivateHint = computed(() => {
    if (props.user.is_self) {
        return 'You cannot deactivate your own account.';
    }

    if (props.user.is_last_active_admin) {
        return 'This is the last active Admin account and must remain active.';
    }

    if (!props.user.is_active) {
        return 'This account is already inactive.';
    }

    return null;
});

function submit(): void {
    form.put(UserManagementController.update(props.user.id).url);
}

function deactivateUser(): void {
    deactivating.value = true;

    router.patch(UserManagementController.deactivate(props.user.id).url, undefined, {
        onFinish: () => {
            deactivating.value = false;
            deactivateDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <Heading
            variant="small"
            title="Edit user"
            :description="user.email"
        />

        <div
            v-if="deactivateHint"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
        >
            {{ deactivateHint }}
        </div>

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
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="usersIndex()">Back</Link>
                </Button>

                <Button
                    type="button"
                    variant="destructive"
                    :disabled="!can.deactivate"
                    @click="deactivateDialogOpen = true"
                >
                    Deactivate
                </Button>

                <Button :disabled="form.processing">Save changes</Button>
            </div>
        </form>
    </div>

    <Dialog v-model:open="deactivateDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Deactivate user account?</DialogTitle>
                <DialogDescription>
                    This keeps the user record for audit history but blocks future logins.
                </DialogDescription>
            </DialogHeader>

            <div class="rounded-lg border border-border/60 bg-muted/20 px-4 py-3 text-sm">
                <div class="font-medium">{{ user.name }}</div>
                <div class="text-muted-foreground">{{ user.email }}</div>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="ghost">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="deactivating"
                    @click="deactivateUser"
                >
                    Confirm deactivation
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
