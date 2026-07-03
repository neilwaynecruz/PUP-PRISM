<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, KeyRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { index as apiTokensIndex } from '@/routes/api-tokens';

type AbilityOption = {
    value: string;
    label: string;
    description: string;
};

type ApiToken = {
    id: number;
    name: string;
    abilities: string[];
    ability_labels: string[];
    last_used_at: string | null;
    created_at: string | null;
    expires_at: string | null;
};

type NewToken = {
    name: string;
    plain_text_token: string;
    abilities: string[];
    ability_labels: string[];
    expires_at: string | null;
};

const props = defineProps<{
    abilityOptions: AbilityOption[];
    newToken?: NewToken | null;
    tokens: ApiToken[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'API Tokens',
                href: apiTokensIndex(),
            },
        ],
    },
});

const createTokenForm = useForm({
    name: '',
    abilities: ['read'] as string[],
});

const revokeTokenForm = useForm({});
const revokeDialogOpen = ref(false);
const selectedToken = ref<ApiToken | null>(null);

const { copy, copied } = useClipboard();

const selectedAbilitySummary = computed(() => {
    const selectedLabels = props.abilityOptions
        .filter((option) => createTokenForm.abilities.includes(option.value))
        .map((option) => option.label);

    return selectedLabels.length > 0
        ? selectedLabels.join(', ')
        : 'Select at least one ability.';
});

function formatDate(value: string | null, fallback = 'Never'): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function hasAbility(ability: string): boolean {
    return createTokenForm.abilities.includes(ability);
}

function toggleAbility(ability: string): void {
    if (hasAbility(ability)) {
        createTokenForm.abilities = createTokenForm.abilities.filter(
            (value) => value !== ability,
        );

        return;
    }

    createTokenForm.abilities = [...createTokenForm.abilities, ability];
}

function submit(): void {
    createTokenForm.post(ApiTokenController.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            createTokenForm.reset('name');
            createTokenForm.abilities = ['read'];
        },
    });
}

function openRevokeDialog(token: ApiToken): void {
    selectedToken.value = token;
    revokeDialogOpen.value = true;
}

function closeRevokeDialog(): void {
    revokeDialogOpen.value = false;
    selectedToken.value = null;
}

function revokeSelectedToken(): void {
    if (!selectedToken.value) {
        return;
    }

    revokeTokenForm.delete(ApiTokenController.destroy(selectedToken.value.id).url, {
        preserveScroll: true,
        onSuccess: () => closeRevokeDialog(),
    });
}
</script>

<template>
    <Head title="API Tokens" />

    <h1 class="sr-only">API token settings</h1>

    <div class="space-y-6" data-testid="api-tokens-settings-page">
        <Heading
            variant="small"
            title="Create API token"
            description="Generate scoped personal access tokens for integrations and scripts."
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="api-token-name">Token name</Label>
                <Input
                    id="api-token-name"
                    v-model="createTokenForm.name"
                    autocomplete="off"
                    placeholder="Inventory integration"
                />
                <InputError :message="createTokenForm.errors.name" />
            </div>

            <div class="grid gap-3">
                <div class="space-y-1">
                    <Label>Abilities</Label>
                    <p class="text-sm text-muted-foreground">
                        {{ selectedAbilitySummary }}
                    </p>
                </div>

                <div class="grid gap-3 rounded-xl border border-border/60 bg-card p-4">
                    <label
                        v-for="option in abilityOptions"
                        :key="option.value"
                        class="flex items-start gap-3"
                    >
                        <Checkbox
                            :model-value="hasAbility(option.value)"
                            :aria-label="option.label"
                            class="mt-0.5"
                            @update:model-value="toggleAbility(option.value)"
                        />

                        <div class="space-y-1">
                            <div class="text-sm font-medium">
                                {{ option.label }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ option.description }}
                            </p>
                        </div>
                    </label>
                </div>

                <InputError
                    :message="
                        createTokenForm.errors.abilities ??
                        createTokenForm.errors['abilities.0']
                    "
                />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="createTokenForm.processing"
                    data-testid="create-api-token-button"
                >
                    Create token
                </Button>
            </div>
        </form>
    </div>

    <div
        v-if="newToken"
        class="space-y-6 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-5"
    >
        <Heading
            variant="small"
            title="Copy your new token now"
            description="This plaintext token is only shown once. Store it securely before leaving this page."
        />

        <div class="grid gap-2">
            <Label for="plain-text-token">Plaintext token</Label>
            <div
                class="flex items-stretch overflow-hidden rounded-xl border border-border bg-background"
            >
                <Input
                    id="plain-text-token"
                    readonly
                    :model-value="newToken.plain_text_token"
                    class="rounded-none border-0 shadow-none"
                />
                <button
                    type="button"
                    class="border-l border-border px-3 transition-colors hover:bg-muted"
                    @click="copy(newToken.plain_text_token)"
                >
                    <Check v-if="copied" class="size-4 text-emerald-600" />
                    <Copy v-else class="size-4" />
                </button>
            </div>
        </div>

        <div class="grid gap-2 text-sm text-muted-foreground">
            <p><span class="font-medium text-foreground">Name:</span> {{ newToken.name }}</p>
            <p>
                <span class="font-medium text-foreground">Abilities:</span>
                {{ newToken.ability_labels.join(', ') }}
            </p>
            <p>
                <span class="font-medium text-foreground">Expires:</span>
                {{ formatDate(newToken.expires_at, 'Does not expire') }}
            </p>
        </div>
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Issued tokens"
            description="Review your active API tokens and revoke any that are no longer needed."
        />

        <div
            v-if="tokens.length === 0"
            class="rounded-xl border border-dashed border-border/60 p-6 text-sm text-muted-foreground"
        >
            No API tokens issued yet.
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="token in tokens"
                :key="token.id"
                class="space-y-4 rounded-xl border border-border/60 bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <KeyRound class="size-4 text-muted-foreground" />
                            <h2 class="font-medium">{{ token.name }}</h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="label in token.ability_labels"
                                :key="`${token.id}-${label}`"
                                class="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                            >
                                {{ label }}
                            </span>
                        </div>
                    </div>

                    <Button
                        variant="destructive"
                        size="sm"
                        type="button"
                        @click="openRevokeDialog(token)"
                    >
                        Revoke
                    </Button>
                </div>

                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">Created</dt>
                        <dd>{{ formatDate(token.created_at) }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">Last used</dt>
                        <dd>{{ formatDate(token.last_used_at) }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">Expires</dt>
                        <dd>{{ formatDate(token.expires_at, 'Does not expire') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <Dialog :open="revokeDialogOpen" @update:open="revokeDialogOpen = $event">
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Revoke API token?</DialogTitle>
                <DialogDescription>
                    <span v-if="selectedToken">
                        {{ selectedToken.name }} will stop working immediately for any integration using it.
                    </span>
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" type="button" @click="closeRevokeDialog">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    type="button"
                    :disabled="revokeTokenForm.processing"
                    data-testid="confirm-revoke-api-token-button"
                    @click="revokeSelectedToken"
                >
                    Revoke token
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
