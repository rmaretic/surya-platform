<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { account, dashboard, home, logout } from '@/routes';
import { edit } from '@/routes/studio/profile';
import { index } from '@/routes/studio/staff';
const page = usePage<{
    studioName: string;
    studioAccess: { administration: boolean; manage: boolean };
}>();
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
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-5 px-5 py-5"
            >
                <Link
                    :href="account()"
                    class="min-w-0 text-lg font-semibold wrap-anywhere"
                    >{{ page.props.studioName }}</Link
                >
                <nav
                    aria-label="Studio navigacija"
                    class="flex flex-wrap gap-5 text-sm"
                >
                    <Link
                        v-if="page.props.studioAccess.administration"
                        :href="dashboard()"
                        class="hover:underline"
                        >Pregled</Link
                    >
                    <Link
                        v-if="page.props.studioAccess.manage"
                        :href="edit()"
                        class="hover:underline"
                        >Javni profil</Link
                    >
                    <Link
                        v-if="page.props.studioAccess.manage"
                        :href="index()"
                        class="hover:underline"
                        >Osoblje</Link
                    >
                    <Link :href="account()" class="hover:underline"
                        >Moj račun</Link
                    >
                    <Link :href="home()" class="hover:underline"
                        >Javna stranica</Link
                    >
                    <Link :href="logout()" as="button" class="hover:underline"
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
