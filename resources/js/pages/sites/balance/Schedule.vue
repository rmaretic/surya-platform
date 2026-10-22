<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BalanceLayout from '@/layouts/sites/BalanceLayout.vue';
import CustomerSchedule from '@/components/CustomerSchedule.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import type { ScheduleProps } from '@/types/customer-portal';
import type { PublicStudioProfile } from '@/types/public-studio';
defineProps<ScheduleProps & { profile: PublicStudioProfile }>();
</script>
<template>
    <BalanceLayout :name="profile.name" current="schedule">
        <Head title="Raspored"
            ><meta name="robots" content="noindex, nofollow"
        /></Head>
        <CustomerSchedule v-bind="$props" />
        <section class="balance-section balance-contact">
            <PublicStudioContact :profile="profile" />
        </section>
    </BalanceLayout>
</template>
