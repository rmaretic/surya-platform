<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PublicStudioMetadata } from '@/types/public-studio';

defineProps<{ seo: PublicStudioMetadata }>();
</script>

<template>
    <Head :title="seo.title">
        <meta
            head-key="description"
            name="description"
            :content="seo.description"
        />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link
            v-if="seo.canonical"
            head-key="canonical"
            rel="canonical"
            :href="seo.canonical"
        />
    </Head>
</template>
