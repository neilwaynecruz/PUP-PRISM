<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Use a recovery code',
            description:
                'Enter one of your saved recovery codes to confirm your identity.',
            buttonText: 'Use an authentication code instead',
        };
    }

    return {
        title: 'Enter your authentication code',
        description: 'Enter the six-digit code from your authenticator app.',
        buttonText: 'Use a recovery code instead',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                replace
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            aria-label="Six-digit authentication code"
                            autocomplete="one-time-code"
                            inputmode="numeric"
                            autofocus
                            :aria-invalid="Boolean(errors.code)"
                            :aria-describedby="
                                errors.code
                                    ? 'authentication-code-error'
                                    : undefined
                            "
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError
                        id="authentication-code-error"
                        :message="errors.code"
                    />
                </div>
                <Button type="submit" class="w-full" :disabled="processing">{{
                    processing ? 'Verifying…' : 'Verify and Continue'
                }}</Button>
                <div class="text-center text-sm text-muted-foreground">
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                replace
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    id="recovery-code"
                    name="recovery_code"
                    type="text"
                    placeholder="Enter recovery code"
                    autocomplete="off"
                    aria-label="Recovery code"
                    :autofocus="showRecoveryInput"
                    :aria-invalid="Boolean(errors.recovery_code)"
                    :aria-describedby="
                        errors.recovery_code ? 'recovery-code-error' : undefined
                    "
                    required
                />
                <InputError
                    id="recovery-code-error"
                    :message="errors.recovery_code"
                />
                <Button type="submit" class="w-full" :disabled="processing">{{
                    processing ? 'Verifying…' : 'Verify and Continue'
                }}</Button>

                <div class="text-center text-sm text-muted-foreground">
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
