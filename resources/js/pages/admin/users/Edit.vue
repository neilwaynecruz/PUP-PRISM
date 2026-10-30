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
import { edit as usersEdit, index as usersIndex } from '@/routes/admin/users';

type PositionOption = {
    id: number;
    title: string;
    code: string;
    department: string | null;
    is_active: boolean;
    label: string;
};

type RoleDescription = { summary: string; abilities: string[] };

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
    roleDescriptions: Record<string, RoleDescription>;
    positions: PositionOption[];
    can: {
        deactivate: boolean;
        reactivate: boolean;
        changeRole: boolean;
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
    position_id:
        props.user.position_id !== null ? String(props.user.position_id) : '',
});

const deactivateDialogOpen = ref(false);
const deactivating = ref(false);
const reactivating = ref(false);

const groupedPositions = computed(() => {
    const groups = new Map<string, PositionOption[]>();

    for (const position of props.positions) {
        const department = position.department ?? 'No department';
        groups.set(department, [...(groups.get(department) ?? []), position]);
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([department, positions]) => ({ department, positions }));
});

const deactivateHint = computed(() => {
    if (!props.user.is_active) {
        return 'This account is inactive. The person cannot sign in until it is reactivated.';
    }

    if (props.user.is_self) {
        return 'This is your own account. You cannot deactivate it or change your role.';
    }

    if (props.user.is_last_active_admin) {
        return 'This is the last active Admin account, so it must stay active and keep the Admin role.';
    }

    return null;
});

function submit(): void {
    form.put(UserManagementController.update(props.user.id).url);
}

function reactivateUser(): void {
    router.patch(
        UserManagementController.reactivate(props.user.id).url,
        undefined,
        {
            onStart: () => (reactivating.value = true),
            onFinish: () => (reactivating.value = false),
        },
    );
}

function deactivateUser(): void {
    deactivating.value = true;

    router.patch(
        UserManagementController.deactivate(props.user.id).url,
        undefined,
        {
            onFinish: () => {
                deactivating.value = false;
                deactivateDialogOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Edit user"
                :description="user.email"
            />
            <span
                class="inline-flex w-fit items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                :class="
                    user.is_active
                        ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                        : 'border-border bg-muted text-muted-foreground'
                "
            >
                {{ user.is_active ? 'Active account' : 'Inactive account' }}
            </span>
        </div>

        <div
            v-if="deactivateHint"
            class="flex flex-col gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:text-amber-200"
        >
            <span>{{ deactivateHint }}</span>
            <Button
                v-if="can.reactivate"
                type="button"
                size="sm"
                :disabled="reactivating"
                data-testid="reactivate-user-button"
                @click="reactivateUser"
            >
                {{ reactivating ? 'Reactivating…' : 'Reactivate account' }}
            </Button>
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
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <select
                        id="role"
                        v-model="form.role"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!can.changeRole"
                        required
                    >
                        <option value="">Select role</option>
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </select>
                    <p
                        v-if="!can.changeRole"
                        class="text-xs text-muted-foreground"
                    >
                        Another Admin must change your role.
                    </p>
                    <InputError :message="form.errors.role" />
                    <div
                        v-if="form.role && roleDescriptions[form.role]"
                        class="rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-xs"
                        data-testid="role-description"
                    >
                        <p class="font-medium text-foreground">
                            {{ roleDescriptions[form.role].summary }}
                        </p>
                        <ul
                            class="mt-1 list-disc space-y-0.5 pl-4 text-muted-foreground"
                        >
                            <li
                                v-for="ability in roleDescriptions[form.role]
                                    .abilities"
                                :key="ability"
                            >
                                {{ ability }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="position_id">Position</Label>
                    <select
                        id="position_id"
                        v-model="form.position_id"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                    >
                        <option value="">No assigned position</option>
                        <optgroup
                            v-for="group in groupedPositions"
                            :key="group.department"
                            :label="group.department"
                        >
                            <option
                                v-for="position in group.positions"
                                :key="position.id"
                                :value="String(position.id)"
                            >
                                {{ position.title }} ({{ position.code }}){{
                                    position.is_active ? '' : ' — inactive'
                                }}
                            </option>
                        </optgroup>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        The position decides which assets this person is
                        accountable for during handovers.
                    </p>
                    <InputError :message="form.errors.position_id" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="usersIndex()">Back</Link>
                </Button>

                <Button
                    v-if="user.is_active"
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
                    This keeps the user record for audit history, blocks future
                    sign-ins, and signs them out of every device and API token.
                    You can reactivate the account later.
                </DialogDescription>
            </DialogHeader>

            <div
                class="rounded-lg border border-border/60 bg-muted/20 px-4 py-3 text-sm"
            >
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
