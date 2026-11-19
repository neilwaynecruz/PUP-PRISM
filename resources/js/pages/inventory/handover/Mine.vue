<script setup lang="ts">
defineOptions({ name: 'PersonalHandoverPage' });

import { Form, Head, Link } from '@inertiajs/vue3';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index, mine, receipt, review } from '@/routes/inventory/handover';

defineProps<{
    view: 'pending' | 'all';
    can_manage: boolean;
    handovers: {
        data: Array<{
            id: number;
            asset_name: string | null;
            tag_code: string | null;
            from_name: string | null;
            initiated_at: string;
            status: 'pending' | 'verified' | 'cancelled';
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const statusLabels = {
    pending: 'Pending',
    verified: 'Accepted',
    cancelled: 'Cancelled',
};
</script>

<template>
    <main
        class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6"
        data-testid="my-handovers-page"
    >
        <Head title="My Pending Handovers" />
        <Heading
            title="My Pending Handovers"
            description="Review assets assigned to you and sign to acknowledge accountability. No email link is needed."
        />
        <div class="flex flex-wrap items-center gap-3">
            <Button
                as-child
                :variant="view === 'pending' ? 'default' : 'outline'"
                class="min-h-11"
            >
                <Link
                    :href="mine()"
                    :aria-current="view === 'pending' ? 'page' : undefined"
                    >Pending</Link
                >
            </Button>
            <Button
                as-child
                :variant="view === 'all' ? 'default' : 'outline'"
                class="min-h-11"
            >
                <Link
                    :href="mine({ query: { view: 'all' } })"
                    :aria-current="view === 'all' ? 'page' : undefined"
                    >All my handovers</Link
                >
            </Button>
            <Button v-if="can_manage" as-child variant="ghost" class="min-h-11">
                <Link :href="index()">Manage handovers and resend emails</Link>
            </Button>
        </div>
        <p class="text-sm leading-relaxed text-muted-foreground">
            Only you can accept your assigned handovers. Review sessions expire
            after 15 minutes; if interrupted, return here to start again. Email
            sending status is separate from handover acceptance.
        </p>
        <section
            v-if="handovers.data.length === 0"
            class="rounded-xl border bg-card p-8 text-center"
            aria-label="No handovers"
        >
            <h2 class="font-semibold">
                {{
                    view === 'pending'
                        ? 'No pending handovers'
                        : 'No handovers yet'
                }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Handovers assigned to your signed-in account will appear here.
            </p>
        </section>
        <ul v-else class="grid gap-4" aria-label="Your handovers">
            <li
                v-for="handover in handovers.data"
                :key="handover.id"
                class="flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
            >
                <div class="min-w-0 space-y-2">
                    <span
                        class="inline-flex rounded-md border bg-muted px-2 py-1 text-xs font-medium"
                        >{{ statusLabels[handover.status] }}</span
                    >
                    <h2 class="font-semibold break-words">
                        {{ handover.asset_name ?? 'Asset' }}
                    </h2>
                    <p class="text-sm break-words text-muted-foreground">
                        {{ handover.tag_code ?? 'No tag' }} · From
                        {{ handover.from_name ?? 'Property office' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Handover #{{ handover.id }} ·
                        {{
                            new Date(handover.initiated_at).toLocaleDateString()
                        }}
                    </p>
                </div>
                <Form
                    v-if="handover.status === 'pending'"
                    v-bind="review.form(handover.id)"
                    v-slot="{ processing }"
                    class="shrink-0"
                >
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="min-h-11 w-full sm:w-auto"
                        >{{
                            processing ? 'Opening review…' : 'Review and Accept'
                        }}</Button
                    >
                </Form>
                <Button
                    v-else-if="handover.status === 'verified'"
                    as-child
                    variant="outline"
                    class="min-h-11 shrink-0"
                >
                    <a :href="receipt(handover.id).url"
                        >Download receipt (PDF)</a
                    >
                </Button>
            </li>
        </ul>
        <AdminPagination :links="handovers.links" />
    </main>
</template>
