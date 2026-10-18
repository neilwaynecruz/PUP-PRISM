<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { clearSessionClientState } from '@/lib/sessionGuard';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify email',
        description:
            'Open the verification link we sent to your email address to continue.',
    },
});

defineProps<{
    status?: string;
}>();

function handleLogout(): void {
    clearSessionClientState();
}
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        role="status"
        aria-live="polite"
        class="mb-4 text-center text-sm font-medium text-emerald-700 dark:text-emerald-300"
    >
        We sent a new verification link to your email address. Check your inbox
        and spam folder.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
        data-testid="email-verification-page"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{
                processing
                    ? 'Sending Verification Email…'
                    : 'Resend Verification Email'
            }}
        </Button>

        <TextLink
            :href="logout()"
            method="post"
            as="button"
            class="mx-auto block text-sm"
            data-testid="verify-email-logout-button"
            @click="handleLogout"
        >
            Log out
        </TextLink>
    </Form>
</template>
