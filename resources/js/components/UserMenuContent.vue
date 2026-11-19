<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Bell,
    LogOut,
    Palette,
    Settings,
} from 'lucide-vue-next';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { performAccountSwitch, performLogout } from '@/lib/logout';
import { edit as editAppearance } from '@/routes/appearance';
import { mine as myHandovers } from '@/routes/inventory/handover';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="myHandovers()">
                <ArrowLeftRight class="mr-2 h-4 w-4" />
                My Pending Handovers
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="editAppearance()"
                prefetch
            >
                <Palette class="mr-2 h-4 w-4" />
                Appearance
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="notificationsIndex()"
                prefetch
            >
                <Bell class="mr-2 h-4 w-4" />
                Notifications
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem as-child>
        <button
            type="button"
            class="flex min-h-11 w-full items-center px-2 py-1.5 text-sm"
            @click="performAccountSwitch()"
        >
            <ArrowLeftRight class="mr-2 h-4 w-4" />
            Switch user
        </button>
    </DropdownMenuItem>
    <DropdownMenuItem as-child>
        <button
            type="button"
            class="relative flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm outline-hidden transition-colors select-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
            data-testid="logout-button"
            @click="performLogout"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </button>
    </DropdownMenuItem>
</template>
