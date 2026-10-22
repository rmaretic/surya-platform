<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BalanceLayout from '@/layouts/sites/BalanceLayout.vue';
import CustomerPortal from '@/components/CustomerPortal.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import type { PortalProps } from '@/types/customer-portal';
import type { PublicStudioProfile } from '@/types/public-studio';
defineProps<PortalProps & { profile: PublicStudioProfile }>();
</script>
<template>
    <BalanceLayout :name="profile.name" current="portal">
        <Head title="Moj studio"
            ><meta name="robots" content="noindex, nofollow"
        /></Head>
        <CustomerPortal v-bind="$props" />
        <section class="balance-section balance-contact">
            <PublicStudioContact :profile="profile" />
        </section>
    </BalanceLayout>
</template>
