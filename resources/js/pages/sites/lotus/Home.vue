<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LotusLayout from '@/layouts/sites/LotusLayout.vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import PublicStudioHead from '@/components/PublicStudioHead.vue';
import type {
    PublicStudioMetadata,
    PublicStudioProfile,
} from '@/types/public-studio';
import { about } from '@/routes';
import stillness from './stillness.svg';
defineProps<{ profile: PublicStudioProfile; seo: PublicStudioMetadata }>();
</script>
<template>
    <LotusLayout :name="profile.name" current="home">
        <PublicStudioHead :seo="seo" />
        <section class="lotus-section hero">
            <div class="hero-copy">
                <p class="lotus-eyebrow">Yoga · prostor za predah</p>
                <h1 class="lotus-heading">
                    Udahni.<br />Uspori.<br /><em>Budi ovdje.</em>
                </h1>
                <p class="studio-name">{{ profile.name }}</p>
                <p v-if="profile.short_description" class="lotus-copy">
                    {{ profile.short_description }}
                </p>
                <Link :href="about()" class="lotus-button"
                    >Upoznajte studio <span aria-hidden="true">↗</span></Link
                >
            </div>
            <figure class="hero-art">
                <div class="art-frame">
                    <img
                        :src="stillness"
                        width="700"
                        height="820"
                        alt="Ilustracija kamenja u ravnoteži, zelenih brežuljaka i toplog sunca."
                        fetchpriority="high"
                    />
                </div>
                <span class="orbit" aria-hidden="true" data-parallax></span>
                <figcaption>01 / Trenutak tišine</figcaption>
            </figure>
        </section>
        <section class="lotus-section introduction" data-reveal>
            <p class="lotus-eyebrow">Prostor za vlastiti ritam</p>
            <div>
                <h2>Manje žurbe.<br />Više prisutnosti.</h2>
                <p class="lotus-copy">
                    Odvoji trenutak za dah, pokret i pažnju prema sebi. Ne moraš
                    nikamo stići. Za početak je dovoljno biti ovdje.
                </p>
                <Link :href="about()" class="story-link"
                    >O studiju <span aria-hidden="true">↗</span></Link
                >
            </div>
            <svg
                class="petal"
                viewBox="0 0 120 120"
                fill="none"
                aria-hidden="true"
                data-parallax
            >
                <path
                    d="M60 4v112M4 60h112M20 20l80 80M20 100l80-80"
                    stroke="currentColor"
                    stroke-width="2"
                />
                <circle cx="60" cy="60" r="42" stroke="currentColor" />
            </svg>
        </section>
        <div
            v-if="profile.email || profile.phone || profile.address"
            class="lotus-contact"
        >
            <div class="lotus-section lotus-contact-inner" data-reveal>
                <div class="grid content-start gap-5">
                    <p class="lotus-eyebrow">Ostanimo u kontaktu</p>
                    <h2>Svaki susret počinje<br />jednim pozdravom.</h2>
                    <p class="lotus-copy">{{ profile.name }}</p>
                </div>
                <PublicStudioContact :profile="profile" />
            </div>
        </div>
    </LotusLayout>
</template>
<style scoped>
.hero {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 80px;
    align-items: center;
}
.hero-copy {
    display: grid;
    justify-items: start;
    gap: 26px;
    min-width: 0;
}
.hero-copy h1 em {
    color: #68775a;
    font-weight: 400;
}
.studio-name {
    font-family: Georgia, serif;
    font-size: 24px;
    overflow-wrap: anywhere;
}
.hero-art {
    position: relative;
    padding: 20px 0;
    min-width: 0;
}
.art-frame {
    border-radius: 48% 48% 4px 4px;
    overflow: hidden;
}
.art-frame img {
    width: 100%;
    height: auto;
    display: block;
}
.orbit {
    position: absolute;
    width: 105px;
    height: 105px;
    border: 1px solid #758365;
    border-radius: 50%;
    left: -40px;
    bottom: 95px;
    pointer-events: none;
}
figcaption {
    padding-top: 20px;
    text-align: right;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.17em;
}
.introduction {
    border-top: 1px solid #c8cabc;
    display: grid;
    grid-template-columns: 1fr 2fr 0.6fr;
    align-items: start;
    gap: 32px;
}
.introduction > div {
    display: grid;
    gap: 28px;
}
.introduction h2 {
    font:
        400 clamp(36px, 4vw, 58px)/1.15 Georgia,
        serif;
    letter-spacing: -0.035em;
}
.story-link {
    justify-self: start;
    border-bottom: 1px solid #87947d;
    padding-bottom: 8px;
    font-size: 14px;
}
.petal {
    width: 100%;
    max-width: 130px;
    color: #acb495;
    justify-self: end;
}
@media (max-width: 767px) {
    .hero {
        grid-template-columns: 1fr;
        gap: 36px;
    }
    .hero-art {
        width: 85%;
        justify-self: center;
    }
    .orbit {
        width: 72px;
        height: 72px;
        left: -22px;
    }
    .introduction {
        grid-template-columns: 1fr;
    }
    .petal {
        display: none;
    }
}
</style>
