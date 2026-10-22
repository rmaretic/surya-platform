<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LotusLayout from '@/layouts/sites/LotusLayout.vue';
import CustomerSchedule from '@/components/CustomerSchedule.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import type { ScheduleProps } from '@/types/customer-portal';
import type { PublicStudioProfile } from '@/types/public-studio';
defineProps<ScheduleProps & { profile: PublicStudioProfile }>();
</script>
<template>
    <LotusLayout :name="profile.name" current="schedule">
        <Head title="Raspored"
            ><meta name="robots" content="noindex, nofollow"
        /></Head>
        <CustomerSchedule v-bind="$props" />
        <section class="lotus-section lotus-contact">
            <PublicStudioContact :profile="profile" />
        </section>
    </LotusLayout>
</template>
