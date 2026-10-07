<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login } from '@/routes';
defineProps<{
    profile: {
        name: string;
        short_description: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
}>();
</script>

<template>
    <main
        class="flex min-h-screen items-center justify-center bg-background px-6 py-16 text-foreground"
    >
        <Head title="Surya" />
        <section class="flex w-full max-w-xl flex-col gap-6">
            <p
                class="text-sm font-medium tracking-widest text-muted-foreground uppercase"
            >
                Surya
            </p>
            <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                {{ profile?.name ?? 'Prostor za vaš studio.' }}
            </h1>
            <p class="text-lg leading-relaxed text-muted-foreground">
                {{
                    profile?.short_description ??
                    'Platforma za yoga studije je u pripremi.'
                }}
            </p>
            <div v-if="profile" class="grid gap-3 wrap-anywhere">
                <p v-if="profile.phone">{{ profile.phone }}</p>
                <p v-if="profile.email">{{ profile.email }}</p>
                <p v-if="profile.address">{{ profile.address }}</p>
                <Link :href="login()" class="underline underline-offset-4"
                    >Prijava u studio</Link
                >
            </div>
        </section>
    </main>
</template>
