<script setup lang="ts">
defineOptions({ name: 'HandoverRecoveryPage' });

import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { performAccountSwitch } from '@/lib/logout';
import { dashboard } from '@/routes';
import { mine } from '@/routes/inventory/handover';

defineProps<{ title: string; message: string; can_switch: boolean }>();
</script>

<template>
    <main
        class="mx-auto w-full max-w-2xl p-4 sm:p-8"
        data-testid="handover-recovery-page"
    >
        <Head :title="title" />
        <section
            class="space-y-5 rounded-xl border bg-card p-5 sm:p-8"
            aria-labelledby="recovery-title"
        >
            <p class="text-sm font-medium text-primary">Handover access</p>
            <h1
                id="recovery-title"
                class="text-2xl font-semibold tracking-tight"
            >
                {{ title }}
            </h1>
            <p class="text-sm leading-relaxed text-muted-foreground">
                {{ message }}
            </p>
            <p v-if="can_switch" class="text-sm text-muted-foreground">
                Switching signs out this account. The next person must sign in
                with their own credentials. No handover will be accepted
                automatically.
            </p>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <Button
                    v-if="can_switch"
                    type="button"
                    class="min-h-11"
                    @click="performAccountSwitch(true)"
                    >Switch account</Button
                >
                <Button
                    as-child
                    :variant="can_switch ? 'outline' : 'default'"
                    class="min-h-11"
                    ><Link :href="mine()">My Pending Handovers</Link></Button
                >
                <Button as-child variant="outline" class="min-h-11"
                    ><Link :href="dashboard()"
                        >Return to dashboard</Link
                    ></Button
                >
            </div>
        </section>
    </main>
</template>
