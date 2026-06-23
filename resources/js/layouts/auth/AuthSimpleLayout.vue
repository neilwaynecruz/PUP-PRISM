<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const isLoginPage = computed(() => page.component === 'auth/Login');
</script>

<template>
    <main
        class="min-h-svh bg-[#0a1730] text-slate-950 selection:bg-blue-200 selection:text-blue-950"
    >
        <div
            class="mx-auto grid min-h-svh max-w-[1600px] lg:grid-cols-[42%_58%]"
        >
            <section
                class="relative hidden overflow-hidden border-r border-white/10 px-12 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-16 xl:py-14"
            >
                <Link
                    :href="home()"
                    class="flex w-fit items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-blue-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#0a1730] focus-visible:outline-none"
                    aria-label="PRISM home"
                >
                    <span
                        class="grid size-10 place-items-center rounded-lg bg-blue-600"
                    >
                        <AppLogoIcon class="size-5 text-white" />
                    </span>
                    <span class="text-lg font-semibold tracking-[0.14em]">
                        PRISM
                    </span>
                </Link>

                <div class="max-w-md">
                    <p
                        class="mb-4 text-xs font-semibold tracking-[0.18em] text-blue-300 uppercase"
                    >
                        PUP Inventory System
                    </p>
                    <h1
                        class="font-[Georgia,serif] text-4xl leading-tight font-normal tracking-[-0.025em] text-balance xl:text-5xl"
                    >
                        Property and Resource Inventory
                    </h1>
                    <p class="mt-5 max-w-sm text-base leading-7 text-slate-300">
                        Manage inventory, receiving, bookings, handovers, and
                        procurement in one system.
                    </p>
                </div>

                <p class="text-xs text-slate-500">
                    Polytechnic University of the Philippines
                </p>

                <div
                    aria-hidden="true"
                    class="absolute right-[-11rem] bottom-[-11rem] size-80 rounded-full border border-white/8"
                />
                <div
                    aria-hidden="true"
                    class="absolute right-[-7rem] bottom-[-7rem] size-56 rounded-full border border-white/8"
                />
            </section>

            <section
                class="flex min-h-svh items-center justify-center bg-[#f8fafc] px-5 py-8 text-slate-950 sm:px-10 lg:px-14 dark:bg-[#0d1729] dark:text-slate-100"
            >
                <div class="w-full max-w-[25rem]">
                    <Link
                        :href="home()"
                        class="mb-10 flex w-fit items-center gap-3 rounded-lg text-slate-950 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-4 focus-visible:outline-none lg:hidden dark:text-white"
                        aria-label="PRISM home"
                    >
                        <span
                            class="grid size-10 place-items-center rounded-lg bg-blue-600 text-white"
                        >
                            <AppLogoIcon class="size-5" />
                        </span>
                        <span class="text-lg font-semibold tracking-[0.14em]">
                            PRISM
                        </span>
                    </Link>

                    <header class="mb-8">
                        <h2
                            v-if="title"
                            class="font-[Georgia,serif] text-[2rem] leading-tight font-semibold tracking-[-0.02em] text-slate-950 sm:text-[2.25rem] dark:text-white"
                        >
                            {{ isLoginPage ? 'Sign in to PRISM' : title }}
                        </h2>
                        <p
                            v-if="description"
                            class="mt-3 text-[0.95rem] leading-6 text-slate-600 dark:text-slate-400"
                        >
                            {{
                                isLoginPage
                                    ? 'Enter the email address and password assigned to your account.'
                                    : description
                            }}
                        </p>
                    </header>

                    <slot />

                    <p
                        class="mt-10 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500 dark:border-slate-700 dark:text-slate-500"
                    >
                        Authorized users only. Contact your administrator if you
                        need help accessing your account.
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
