<script setup lang="ts">
import type { PublicStudioProfile } from '@/types/public-studio';
defineProps<{ profile: PublicStudioProfile }>();
</script>
<template>
    <section
        v-if="profile.email || profile.phone || profile.address"
        aria-labelledby="studio-contact"
        class="grid gap-4 wrap-anywhere"
    >
        <h2 id="studio-contact" class="text-xl font-semibold">Kontakt</h2>
        <dl class="grid gap-4 text-sm">
            <div v-if="profile.email">
                <dt class="font-semibold">Email</dt>
                <dd>{{ profile.email }}</dd>
            </div>
            <div v-if="profile.phone">
                <dt class="font-semibold">Telefon</dt>
                <dd>{{ profile.phone }}</dd>
            </div>
            <div v-if="profile.address">
                <dt class="font-semibold">Adresa</dt>
                <dd class="whitespace-pre-line">{{ profile.address }}</dd>
            </div>
        </dl>
    </section>
</template>
