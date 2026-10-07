<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LotusLayout from '@/layouts/sites/LotusLayout.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import PublicStudioHead from '@/components/PublicStudioHead.vue';
import type {
    PublicStudioMetadata,
    PublicStudioProfile,
} from '@/types/public-studio';
import { login } from '@/routes';
import stillness from './stillness.svg';
defineProps<{ profile: PublicStudioProfile; seo: PublicStudioMetadata }>();
</script>
<template>
    <LotusLayout :name="profile.name" current="about">
        <PublicStudioHead :seo="seo" />
        <section class="lotus-section about">
            <div class="about-title">
                <p class="lotus-eyebrow">O studiju</p>
                <h1 class="lotus-heading">
                    Prostor za dah.<br /><em>I za tebe.</em>
                </h1>
            </div>
            <figure>
                <img
                    :src="stillness"
                    width="700"
                    height="820"
                    alt="Ilustracija mirnog krajolika i kamenja u ravnoteži."
                />
                <figcaption>Ravnoteža počinje malim trenutkom.</figcaption>
            </figure>
            <div class="about-copy" data-reveal>
                <h2>{{ profile.name }}</h2>
                <p v-if="profile.short_description" class="lotus-copy">
                    {{ profile.short_description }}
                </p>
                <div class="note">
                    <p class="lotus-eyebrow">Tvoj prostor na mreži</p>
                    <p class="lotus-copy">
                        Već imaš račun u studiju? Prijavi se za pristup svojem
                        profilu.
                    </p>
                    <Link :href="login()" class="lotus-button"
                        >Prijava u studio
                        <span aria-hidden="true">↗</span></Link
                    >
                </div>
            </div>
        </section>
        <div
            v-if="profile.email || profile.phone || profile.address"
            class="lotus-contact"
        >
            <div class="lotus-section lotus-contact-inner" data-reveal>
                <div class="grid content-start gap-5">
                    <p class="lotus-eyebrow">Tu smo za tvoja pitanja</p>
                    <h2>Javi nam se.</h2>
                    <p class="lotus-copy">
                        Podaci za kontakt sa studijem na jednom mjestu.
                    </p>
                </div>
                <PublicStudioContact :profile="profile" />
            </div>
        </div>
    </LotusLayout>
</template>
<style scoped>
.about {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 64px 88px;
    align-items: center;
}
.about-title {
    grid-column: 1 / -1;
    display: grid;
    gap: 28px;
    text-align: center;
}
.about-title em {
    color: #68775a;
}
figure {
    max-width: 430px;
}
figure img {
    width: 100%;
    height: auto;
    border-radius: 48% 48% 0 0;
}
figcaption {
    font:
        italic 16px Georgia,
        serif;
    padding-top: 20px;
}
.about-copy {
    display: grid;
    gap: 24px;
    min-width: 0;
}
.about-copy h2 {
    font:
        36px/1.15 Georgia,
        serif;
    overflow-wrap: anywhere;
}
.note {
    border-top: 1px solid #c8cabc;
    padding-top: 32px;
    margin-top: 12px;
    display: grid;
    justify-items: start;
    gap: 24px;
}
@media (max-width: 767px) {
    .about {
        grid-template-columns: 1fr;
        gap: 40px;
    }
    .about-title {
        text-align: left;
    }
    figure {
        width: 85%;
        justify-self: center;
    }
}
</style>
