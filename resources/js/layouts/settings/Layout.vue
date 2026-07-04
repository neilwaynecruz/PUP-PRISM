<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { index as apiTokensIndex } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editNotificationPreferences } from '@/routes/notification-preferences';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const page = usePage();

const canManageApiTokens = computed(() =>
    page.props.auth.roles.some((role) =>
        ['Admin', 'Supply Head'].includes(role),
    ),
);

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Profile',
            href: editProfile(),
        },
        {
            title: 'Security',
            href: editSecurity(),
        },
        {
            title: 'Appearance',
            href: editAppearance(),
        },
        {
            title: 'Notifications',
            href: editNotificationPreferences(),
        },
    ];

    if (canManageApiTokens.value) {
        items.splice(2, 0, {
            title: 'API Tokens',
            href: apiTokensIndex(),
        });
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
const { closeOverlays, pendingPath } = useAppNavigation();

function isPendingItem(item: NavItem): boolean {
    return pendingPath.value === toUrl(item.href);
}
</script>

<template>
    <div class="px-4 py-6" data-testid="settings-layout-page">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link
                            :href="item.href"
                            class="flex items-center gap-2"
                            @click="closeOverlays()"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                            <LoaderCircle
                                v-if="isPendingItem(item)"
                                class="ml-auto h-3.5 w-3.5 animate-spin text-primary"
                            />
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
