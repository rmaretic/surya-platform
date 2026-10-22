<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import OperationalDashboard from '@/components/OperationalDashboard.vue';
import type { OperationalDashboardData } from '@/types/operational-dashboard';
defineProps<OperationalDashboardData>();
</script>
<template>
    <div class="grid gap-8">
        <Head title="Operativni pregled platforme" />
        <div class="grid gap-3">
            <p class="text-sm text-muted-foreground">Platform administracija</p>
            <h1 class="text-3xl font-semibold">Operativni pregled</h1>
        </div>
        <OperationalDashboard
            :period="period"
            :summary="summary"
            :tenants="tenants"
        />
    </div>
</template>
