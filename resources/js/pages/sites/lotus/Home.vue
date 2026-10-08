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
                <p class="lotus-eyebrow">Yoga studio · umjetnost usporavanja</p>
                <h1 class="lotus-heading">
                    Udahni mir.<br />Pronađi<br /><em>svoj ritam.</em>
                </h1>
                <p class="studio-name">{{ profile.name }}</p>
                <p class="lotus-copy">
                    Snaga u pokretu. Mir u sebi. Otkrij praksu koja ostavlja
                    prostor za sve što jesi, od prvog nesigurnog koraka do
                    trenutka kada prostirka postane tvoje omiljeno mjesto u
                    danu.
                </p>
                <p v-if="profile.short_description" class="lotus-copy">
                    {{ profile.short_description }}
                </p>
                <Link :href="about()" class="lotus-button"
                    >Upoznaj naš studio <span aria-hidden="true">↗</span></Link
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
                <figcaption>01 / Luksuz trenutka koji pripada tebi</figcaption>
            </figure>
        </section>
        <section class="lotus-section introduction" data-reveal>
            <p class="lotus-eyebrow">Prostor za vlastiti ritam</p>
            <div>
                <h2>Manje žurbe.<br />Više prisutnosti.</h2>
                <p class="lotus-copy">
                    Zamisli jutro uz meko svjetlo, drveni pod pod bosim
                    stopalima i nekoliko tihih trenutaka prije prvog pokreta.
                    Naš je studio zamišljen kao mali gradski predah: mjesto na
                    kojem možeš odložiti obaveze i posvetiti pažnju sebi.
                </p>
                <p class="lotus-copy">
                    Vježbamo u malim grupama, uz pažljivo vodstvo i vrijeme za
                    pitanja. Učitelji nude različite varijante položaja kako bi
                    svatko mogao istražiti praksu u vlastitom ritmu. Nakon sata
                    ostaje prostor za šalicu čaja, razgovor ili još malo tišine.
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
        <section class="lotus-section introduction" data-reveal>
            <p class="lotus-eyebrow">Praksa koja prati tvoj dan</p>
            <div>
                <h2>Pokret s namjerom.<br />Vrijeme sa smislom.</h2>
                <p class="lotus-copy">
                    Nekim danima želiš istražiti snagu i pokret. Drugima ti više
                    odgovara mirniji tempo. U našem zamišljenom programu svaki
                    stil ima svoj karakter, a svaka praksa počinje slušanjem
                    vlastitog tijela.
                </p>
                <h3>Hatha · temelji kojima se vraćaš</h3>
                <p class="lotus-copy">
                    Promišljen, sporiji sat s jasnim uputama za osnovne
                    položaje, disanje i prijelaze. Dobar izbor za upoznavanje s
                    jogom ili dane kada želiš više vremena za svaki pokret.
                </p>
                <h3>Vinyasa · pronađi lakoću u pokretu</h3>
                <p class="lotus-copy">
                    Povezujemo dah i pokret u tečne sekvence, od postupnog
                    zagrijavanja do završnog odmora. Dinamičnija praksa za one
                    koji vole istraživati ravnotežu, stabilnost i ritam.
                </p>
                <h3>Yin · dopusti si usporiti</h3>
                <p class="lotus-copy">
                    Mirnija praksa s duljim zadržavanjem u položajima na podu,
                    uz jastuke, blokove i deke. Poziv da pronađeš ugodan
                    položaj, primijetiš dah i ostaviš malo više prostora za
                    tišinu.
                </p>
            </div>
        </section>
        <section class="lotus-section introduction" data-reveal>
            <p class="lotus-eyebrow">Ljudi i pažnja</p>
            <div>
                <h2>Pažnja je naš<br />najljepši ritual.</h2>
                <p class="lotus-copy">
                    Naš tim okuplja učitelje različitih pristupa koje povezuje
                    ista ideja: dobar sat ostavlja dovoljno prostora za osobno
                    iskustvo. Vodimo jasno, nudimo prilagodbe i poštujemo tvoju
                    odluku da zastaneš kad god ti je to potrebno.
                </p>
                <p class="lotus-copy">
                    Prije sata možeš podijeliti svoje iskustvo i želje. Fizičke
                    prilagodbe nudimo samo uz tvoju suglasnost. Važno nam je da
                    se osjećaš dobrodošlo, bilo da prvi put odmotavaš prostirku
                    ili već godinama njeguješ vlastitu praksu.
                </p>
            </div>
        </section>
        <section class="lotus-section introduction" data-reveal>
            <p class="lotus-eyebrow">Tvoj prvi dolazak</p>
            <div>
                <h2>Dođi kakav jesi.<br />Počni odavde.</h2>
                <p class="lotus-copy">
                    Za prvi sat dovoljno je malo znatiželje. Ne trebaš prethodno
                    iskustvo ni određenu razinu fleksibilnosti; zajedno ćemo
                    pronaći početak koji ti odgovara.
                </p>
                <h3>Što ponijeti?</h3>
                <p class="lotus-copy">
                    Odjeni udobnu odjeću u kojoj se možeš slobodno kretati i
                    ponesi bočicu vode. U studiju te čekaju prostirke, blokovi,
                    trake i deke, a svoju prostirku slobodno donesi ako je voliš
                    imati uz sebe.
                </p>
                <h3>Kako izgleda prvi sat?</h3>
                <p class="lotus-copy">
                    Dođi desetak minuta ranije da upoznaš prostor i učitelja.
                    Sat otvaramo kratkim smirivanjem i pažnjom na dah,
                    nastavljamo vođenim pokretom te završavamo odmorom. Tijekom
                    cijele prakse možeš uzeti pauzu ili odabrati jednostavniju
                    varijantu.
                </p>
                <h3>Nisi siguran odakle krenuti?</h3>
                <p class="lotus-copy">
                    Hatha je nježan uvod u osnove, Vinyasa donosi više dinamike,
                    a Yin sporiji ritam. Javi nam što tražiš u svojoj praksi i
                    rado ćemo ti pomoći odabrati smjer za prvi dolazak.
                </p>
                <p class="lotus-copy">
                    Ovo je demo priča: opis studija, tima i programa je
                    izmišljen i služi predstavljanju mogućeg iskustva u studiju.
                </p>
            </div>
        </section>
        <div
            v-if="profile.email || profile.phone || profile.address"
            class="lotus-contact"
        >
            <div class="lotus-section lotus-contact-inner" data-reveal>
                <div class="grid content-start gap-5">
                    <p class="lotus-eyebrow">Napravi prostor za sebe</p>
                    <h2>Tvoj mir počinje<br />jednim malim korakom.</h2>
                    <p class="lotus-copy">
                        Imaš pitanje o studiju ili prvom dolasku? Pošalji nam
                        nekoliko riječi o sebi i onome što želiš pronaći u
                        svojoj praksi. Veselimo se upoznavanju.
                    </p>
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
