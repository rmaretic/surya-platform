<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OperationalDashboard from '@/components/OperationalDashboard.vue';
import { edit } from '@/routes/studio/profile';
import { index } from '@/routes/studio/staff';
import type { OperationalDashboardData } from '@/types/operational-dashboard';
defineProps<OperationalDashboardData & { role: string }>();
</script>
<template>
    <div class="grid gap-8">
        <Head title="Pregled studija" />
        <div class="grid gap-3">
            <p class="text-sm text-muted-foreground">Studio administracija</p>
            <h1 class="text-3xl font-semibold">Pregled studija</h1>
        </div>
        <OperationalDashboard
            :period="period"
            :summary="summary"
            :scope="scope"
            :sessions="sessions"
        />
        <nav
            v-if="role === 'owner'"
            aria-label="Upravljanje studijem"
            class="flex flex-wrap gap-5 text-sm"
        >
            <Link :href="edit()" class="underline">Uredi javni profil</Link>
            <Link :href="index()" class="underline">Upravljaj osobljem</Link>
        </nav>
    </div>
</template>
