<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LotusLayout from '@/layouts/sites/LotusLayout.vue';
import CustomerPortal from '@/components/CustomerPortal.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import type { PortalProps } from '@/types/customer-portal';
import type { PublicStudioProfile } from '@/types/public-studio';
defineProps<PortalProps & { profile: PublicStudioProfile }>();
</script>
<template>
    <LotusLayout :name="profile.name" current="portal">
        <Head title="Moj studio"
            ><meta name="robots" content="noindex, nofollow"
        /></Head>
        <CustomerPortal v-bind="$props" />
        <section class="lotus-section lotus-contact">
            <PublicStudioContact :profile="profile" />
        </section>
    </LotusLayout>
</template>
