<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index, create } from '@/routes/platform/tenants';
import { account, logout } from '@/routes/platform';
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:block focus:p-4"
            >Preskoči na sadržaj</a
        >
        <header class="border-b bg-muted/30">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5"
            >
                <Link :href="index()" class="text-lg font-semibold"
                    >Surya
                    <span class="text-sm font-normal text-muted-foreground"
                        >/ Platforma</span
                    ></Link
                >
                <nav
                    aria-label="Platform administracija"
                    class="flex flex-wrap items-center gap-5 text-sm"
                >
                    <Link
                        :href="index()"
                        class="underline-offset-4 hover:underline"
                        >Studiji</Link
                    >
                    <Link
                        :href="create()"
                        class="underline-offset-4 hover:underline"
                        >Novi studio</Link
                    >
                    <Link
                        :href="account()"
                        class="underline-offset-4 hover:underline"
                        >Moj račun</Link
                    >
                    <Link
                        :href="logout()"
                        as="button"
                        class="underline-offset-4 hover:underline"
                        >Odjava</Link
                    >
                </nav>
            </div>
        </header>
        <main id="main-content" class="mx-auto max-w-6xl px-5 py-8 md:py-12">
            <slot />
        </main>
    </div>
</template>
