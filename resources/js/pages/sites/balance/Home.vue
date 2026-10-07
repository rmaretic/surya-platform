<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BalanceLayout from '@/layouts/sites/BalanceLayout.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import PublicStudioHead from '@/components/PublicStudioHead.vue';
import type {
    PublicStudioMetadata,
    PublicStudioProfile,
} from '@/types/public-studio';
import { about } from '@/routes';
import movement from './movement.svg';
defineProps<{ profile: PublicStudioProfile; seo: PublicStudioMetadata }>();
</script>
<template>
    <BalanceLayout :name="profile.name" current="home">
        <PublicStudioHead :seo="seo" />
        <section class="balance-section hero">
            <div class="hero-top">
                <p class="balance-eyebrow">Yoga / pokret / ravnoteža</p>
                <span class="edition">Pronađi svoj ritam — 01</span>
            </div>
            <div class="hero-title">
                <h1 class="balance-heading">
                    Tvoj pokret.<br /><span>Tvoja ravnoteža.</span>
                </h1>
                <Link
                    :href="about()"
                    class="hero-arrow"
                    aria-label="Upoznajte studio"
                    ><span aria-hidden="true">↗</span></Link
                >
            </div>
            <figure>
                <img
                    :src="movement"
                    width="1100"
                    height="650"
                    alt="Geometrijska ilustracija osobe u yoga položaju s raširenim rukama."
                    fetchpriority="high"
                /><span class="image-label" aria-hidden="true" data-parallax
                    >POKRET<br />POČINJE<br />IZNUTRA.</span
                >
            </figure>
            <div class="hero-bottom">
                <h2>{{ profile.name }}</h2>
                <p v-if="profile.short_description" class="balance-copy">
                    {{ profile.short_description }}
                </p>
            </div>
        </section>
        <section class="balance-section manifesto" data-reveal>
            <p class="balance-eyebrow">01 — U svom ritmu</p>
            <div>
                <h2>Pronađi oslonac.<br />Napravi prostor za pokret.</h2>
                <div class="manifesto-bottom">
                    <p class="balance-copy">
                        Snaga i mir mogu dijeliti isti prostor. Zastani, osjeti
                        dah i istraži vlastiti ritam.
                    </p>
                    <Link :href="about()" class="balance-button"
                        >Upoznajte studio
                        <span aria-hidden="true">↗</span></Link
                    >
                </div>
            </div>
        </section>
        <div
            v-if="profile.email || profile.phone || profile.address"
            class="balance-contact"
        >
            <div class="balance-section balance-contact-inner" data-reveal>
                <div class="grid content-start gap-6">
                    <p class="contact-label">02 — Povežimo se</p>
                    <h2 class="contact-title">Vidimo se<br />u pokretu.</h2>
                    <p>{{ profile.name }}</p>
                </div>
                <PublicStudioContact :profile="profile" />
            </div>
        </div>
    </BalanceLayout>
</template>
<style scoped>
.hero-top {
    display: flex;
    justify-content: space-between;
    gap: 24px;
}
.edition {
    font-size: 11px;
    color: #c9d2c9;
}
.hero-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 40px;
    padding-block: 38px 48px;
}
.hero-title h1 {
    min-width: 0;
}
.hero-title h1 span {
    color: #c8dc86;
}
.hero-arrow {
    flex-shrink: 0;
    width: 100px;
    height: 100px;
    border: 1px solid #70806b;
    border-radius: 50%;
    display: grid;
    place-content: center;
    font-size: 55px;
}
figure {
    position: relative;
}
figure img {
    width: 100%;
    height: auto;
    max-height: 520px;
    object-fit: cover;
    display: block;
}
.image-label {
    position: absolute;
    top: 28px;
    left: 28px;
    font-size: 12px;
    line-height: 1.5;
    letter-spacing: 0.1em;
}
.hero-bottom {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    padding-top: 32px;
}
.hero-bottom h2 {
    font-size: 25px;
    font-weight: 500;
    letter-spacing: -0.04em;
    overflow-wrap: anywhere;
}
.manifesto {
    display: grid;
    grid-template-columns: 1fr 3fr;
    gap: 40px;
    border-top: 1px solid #4b605a;
}
.manifesto h2 {
    font-size: clamp(32px, 4.4vw, 64px);
    letter-spacing: -0.05em;
    line-height: 1.1;
}
.manifesto-bottom {
    margin-top: 36px;
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 40px;
}
.manifesto-bottom a {
    flex-shrink: 0;
}
.contact-label {
    font-size: 11px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.balance-contact .contact-title {
    font-size: clamp(40px, 5vw, 70px);
    line-height: 1;
    font-weight: 600;
}
@media (max-width: 767px) {
    .hero-top {
        flex-direction: column;
        gap: 12px;
    }
    .hero-title {
        padding-block: 28px 32px;
    }
    .hero-arrow {
        display: none;
    }
    figure img {
        min-height: 280px;
        object-position: 60% center;
    }
    .image-label {
        top: 15px;
        left: 15px;
        font-size: 9px;
    }
    .hero-bottom,
    .manifesto {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    .manifesto-bottom {
        flex-direction: column;
        gap: 24px;
    }
}
@media (min-width: 768px) and (max-width: 1100px) {
    .manifesto-bottom {
        flex-direction: column;
    }
    .hero-arrow {
        width: 70px;
        height: 70px;
        font-size: 40px;
    }
}
</style>
