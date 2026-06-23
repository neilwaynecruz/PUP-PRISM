<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    name: 'AuthLogin',
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300"
    >
        <CheckCircle2 class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        data-testid="login-page"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label
                    for="email"
                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                >
                    Email address
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    data-testid="login-email-input"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    inputmode="email"
                    placeholder="Enter your email address"
                    class="h-12 rounded-lg border-slate-300 bg-white px-3.5 text-base text-slate-950 caret-blue-600 shadow-none transition-[border-color,box-shadow,background-color] placeholder:text-slate-500 focus-visible:border-blue-500 focus-visible:ring-blue-500/25 sm:text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50 dark:caret-blue-400 dark:placeholder:text-slate-400"
                    :aria-invalid="Boolean(errors.email)"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                />
                <div id="email-error">
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-4">
                    <Label
                        for="password"
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        Password
                    </Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="rounded-sm text-sm font-semibold text-blue-700 underline-offset-4 hover:text-blue-800 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none dark:text-blue-400 dark:hover:text-blue-300"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    data-testid="login-password-input"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="h-12 rounded-lg border-slate-300 bg-white px-3.5 text-base text-slate-950 caret-blue-600 shadow-none transition-[border-color,box-shadow,background-color] placeholder:text-slate-500 focus-visible:border-blue-500 focus-visible:ring-blue-500/25 sm:text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50 dark:caret-blue-400 dark:placeholder:text-slate-400"
                    :aria-invalid="Boolean(errors.password)"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                />
                <div id="password-error">
                    <InputError :message="errors.password" />
                </div>
            </div>

            <Label
                for="remember"
                class="flex w-fit cursor-pointer items-center gap-3 rounded-md text-sm font-medium text-slate-700 focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 dark:text-slate-300"
            >
                <Checkbox
                    id="remember"
                    name="remember"
                    :tabindex="3"
                    class="size-[1.125rem] rounded-[0.3rem] border-slate-400 data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600"
                />
                <span>Remember me on this device</span>
            </Label>

            <Button
                type="submit"
                class="mt-1 h-12 w-full cursor-pointer rounded-lg bg-blue-600 text-sm font-semibold text-white shadow-none transition-colors hover:bg-blue-700 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
                data-testid="login-button"
            >
                <Spinner v-if="processing" />
                <span>{{ processing ? 'Signing in...' : 'Sign in' }}</span>
            </Button>
        </div>

        <p
            v-if="canRegister"
            class="text-center text-sm text-slate-600 dark:text-slate-400"
        >
            Do not have an account?
            <TextLink
                :href="register()"
                class="font-semibold text-blue-700 underline-offset-4 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                :tabindex="6"
            >
                Create an account
            </TextLink>
        </p>
    </Form>
</template>
