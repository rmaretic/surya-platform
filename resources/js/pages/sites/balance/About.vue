<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BalanceLayout from '@/layouts/sites/BalanceLayout.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import PublicStudioHead from '@/components/PublicStudioHead.vue';
import type {
    PublicStudioMetadata,
    PublicStudioProfile,
} from '@/types/public-studio';
import { login } from '@/routes';
import movement from './movement.svg';
defineProps<{ profile: PublicStudioProfile; seo: PublicStudioMetadata }>();
</script>
<template>
    <BalanceLayout :name="profile.name" current="about">
        <PublicStudioHead :seo="seo" />
        <section class="balance-section about">
            <div class="about-heading">
                <p class="balance-eyebrow">O studiju / iznutra prema van</p>
                <h1 class="balance-heading">
                    Snaga.<br />Prisutnost.<br /><span>Ravnoteža.</span>
                </h1>
            </div>
            <div class="about-details">
                <span class="direction" aria-hidden="true" data-parallax
                    >↗</span
                >
                <h2>{{ profile.name }}</h2>
                <p v-if="profile.short_description" class="balance-copy">
                    {{ profile.short_description }}
                </p>
            </div>
            <figure>
                <img
                    :src="movement"
                    width="1100"
                    height="650"
                    alt="Geometrijska ilustracija yoga pokreta između kruga i planine."
                />
                <figcaption>Pokret i mir. U istom prostoru.</figcaption>
            </figure>
        </section>
        <section class="balance-section account" data-reveal>
            <p class="balance-eyebrow">Tvoj studio. Tvoj račun.</p>
            <div>
                <h2>Nastavi u svom prostoru.</h2>
                <p class="balance-copy">
                    Već imaš račun u studiju? Prijavi se za pristup svojem
                    profilu.
                </p>
                <Link :href="login()" class="balance-button"
                    >Prijava u studio <span aria-hidden="true">↗</span></Link
                >
            </div>
        </section>
        <div
            v-if="profile.email || profile.phone || profile.address"
            class="balance-contact"
        >
            <div class="balance-section balance-contact-inner" data-reveal>
                <div class="grid content-start gap-6">
                    <p class="contact-label">Razgovor je dobar početak</p>
                    <h2>Javi se.<br />Povežimo se.</h2>
                    <p>Kontakt podaci studija {{ profile.name }}.</p>
                </div>
                <PublicStudioContact :profile="profile" />
            </div>
        </div>
    </BalanceLayout>
</template>
<style scoped>
.about {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 56px;
}
.about-heading {
    display: grid;
    gap: 32px;
    align-content: start;
    min-width: 0;
}
.about-heading h1 span {
    color: #c8dc86;
}
.about-details {
    display: grid;
    align-content: end;
    justify-items: start;
    gap: 24px;
    min-width: 0;
}
.about-details h2 {
    font-size: 28px;
    overflow-wrap: anywhere;
}
.direction {
    font-size: 100px;
    line-height: 1;
    color: #c8dc86;
}
figure {
    grid-column: 1 / -1;
}
figure img {
    width: 100%;
    max-height: 480px;
    object-fit: cover;
}
figcaption {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.13em;
    padding-top: 20px;
}
.account {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 40px;
    border-top: 1px solid #4b605a;
}
.account > div {
    display: grid;
    justify-items: start;
    gap: 24px;
}
.account h2 {
    font-size: clamp(30px, 4vw, 54px);
    line-height: 1.1;
    letter-spacing: -0.04em;
}
.contact-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.13em;
}
.balance-contact h2 {
    font-size: 54px;
    line-height: 1;
}
@media (max-width: 767px) {
    .about,
    .account {
        grid-template-columns: 1fr;
        gap: 32px;
    }
    .direction {
        display: none;
    }
    figure img {
        min-height: 260px;
        object-position: 60% center;
    }
}
</style>
