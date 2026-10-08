<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicStudioContact from '@/components/PublicStudioContact.vue';
import PublicStudioHead from '@/components/PublicStudioHead.vue';
import { about, home, login } from '@/routes';
import type {
    PublicStudioMetadata,
    PublicStudioProfile,
} from '@/types/public-studio';
import botanical from './assets/botanical.webp';
import landscape from './assets/landscape.webp';
import practice from './assets/practice.webp';
import { useLotusScene } from './useLotusScene';

defineProps<{ profile: PublicStudioProfile; seo: PublicStudioMetadata }>();
const root = ref<HTMLElement | null>(null);
const statement =
    'Ne moraš postati netko drugi. Samo napravi malo prostora za sebe.'.split(
        ' ',
    );
useLotusScene(root);
</script>

<template>
    <div ref="root" class="lotus-site lotus-experience">
        <PublicStudioHead :seo="seo" />
        <svg
            class="symbol-library"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <defs>
                <symbol id="lotus-flower" viewBox="0 0 80 64">
                    <path
                        d="M40 56C19 42 25 17 40 5c15 12 21 37 0 51ZM40 56C17 57 5 39 8 20c21 1 34 15 32 36Zm0 0c23 1 35-17 32-36-21 1-34 15-32 36ZM40 56C23 66 5 57 2 41c17-4 30 3 38 15Zm0 0c17 10 35 1 38-15-17-4-30 3-38 15Z"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                    />
                </symbol>
            </defs>
        </svg>
        <a class="skip-link" href="#public-content">Preskoči na sadržaj</a>
        <main id="public-content" tabindex="-1">
            <section class="landscape-story" aria-labelledby="lotus-title">
                <div class="landscape-stage">
                    <img
                        class="landscape-plane"
                        :src="landscape"
                        alt=""
                        width="2000"
                        height="1111"
                        fetchpriority="high"
                    />
                    <div class="sky-wash" aria-hidden="true"></div>
                    <div class="sun-plane" aria-hidden="true"></div>
                    <header class="masthead">
                        <Link
                            :href="home()"
                            class="wordmark"
                            :aria-label="`${profile.name} · naslovnica`"
                            aria-current="page"
                            ><svg aria-hidden="true">
                                <use href="#lotus-flower" /></svg
                            ><span
                                >lotus<span class="brand-descriptor"
                                    >YOGA &amp; SLOW LIVING</span
                                ></span
                            ></Link
                        >
                        <nav
                            aria-label="Javna navigacija"
                            class="main-navigation"
                        >
                            <a href="#praksa">Naša praksa</a
                            ><Link :href="about()">O studiju</Link
                            ><a href="#kontakt">Kontakt</a>
                        </nav>
                        <Link :href="login()" class="member-link"
                            >Prijava <span aria-hidden="true">↗</span></Link
                        >
                    </header>
                    <div class="hero-title-plane">
                        <p class="eyebrow">UMJETNOST USPORAVANJA</p>
                        <h1 id="lotus-title">Malo bliže<br /><em>sebi.</em></h1>
                        <p class="hero-description">
                            Snaga u pokretu. Mir u sebi.<br />Prostor za sve što
                            jesi.
                        </p>
                        <a href="#praksa" class="pill-link"
                            >Pronađi svoj ritam
                            <span aria-hidden="true">↗</span></a
                        >
                    </div>
                    <div
                        class="botanical-plane botanical-left"
                        aria-hidden="true"
                    >
                        <img
                            :src="botanical"
                            alt=""
                            width="1536"
                            height="1024"
                        />
                    </div>
                    <div
                        class="botanical-plane botanical-right"
                        aria-hidden="true"
                    >
                        <img
                            :src="botanical"
                            alt=""
                            width="1536"
                            height="1024"
                        />
                    </div>
                    <div class="hero-bottom" aria-hidden="true">
                        <span>POKRET · DAH · PRISUTNOST</span
                        ><span>U VLASTITOM RITMU</span>
                    </div>
                </div>
            </section>
            <section
                class="manifesto section-shell"
                aria-labelledby="manifesto-title"
            >
                <div class="manifesto-margin">
                    <svg aria-hidden="true"><use href="#lotus-flower" /></svg
                    ><span>MALO PROSTORA.<br />PUNO MOGUĆNOSTI.</span>
                </div>
                <div class="manifesto-copy">
                    <h2 id="manifesto-title">
                        <span
                            v-for="(word, index) in statement"
                            :key="index"
                            data-word
                            >{{ word
                            }}{{
                                index < statement.length - 1 ? ' ' : ''
                            }}</span
                        >
                    </h2>
                    <div class="manifesto-bottom" data-enter>
                        <p>
                            Između svega što moraš i svega što želiš, postoji
                            trenutak koji pripada samo tebi. Na prostirci
                            počinje upravo taj prostor.
                        </p>
                        <Link :href="about()" class="text-link"
                            >Upoznaj studio
                            <span aria-hidden="true">↗</span></Link
                        >
                    </div>
                </div>
            </section>
            <section
                id="praksa"
                class="practice-section section-shell"
                aria-labelledby="practice-title"
            >
                <div class="section-heading" data-enter>
                    <div>
                        <p class="eyebrow">PRAKSA KOJA PRATI TVOJ DAN</p>
                        <h2 id="practice-title">
                            Svaki dan.<br /><em>Drugačiji ritam.</em>
                        </h2>
                    </div>
                    <p>
                        Nekad trebaš pokret.<br />Nekad samo predah.<br />Poslušaj
                        što ti danas odgovara.
                    </p>
                </div>
                <div class="practice-stack">
                    <article
                        class="practice-panel hatha-panel"
                        aria-labelledby="hatha-title"
                    >
                        <div class="practice-meta">
                            <span>TEMELJI &amp; RAVNOTEŽA</span
                            ><span>HATHA YOGA</span>
                        </div>
                        <div class="practice-content">
                            <div>
                                <h3 id="hatha-title">
                                    Vrati se<br /><em>osnovama.</em>
                                </h3>
                                <p>
                                    Promišljeni pokreti. Jasno vodstvo. Vrijeme
                                    da upoznaš svaki položaj i pronađeš
                                    stabilnost koja ostaje s tobom i izvan
                                    prostirke.
                                </p>
                                <span class="practice-tag"
                                    >Za početak. I svaki novi početak.</span
                                >
                            </div>
                            <svg
                                class="practice-art hatha-art"
                                viewBox="0 0 360 300"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle cx="180" cy="150" r="114" />
                                <ellipse cx="180" cy="150" rx="68" ry="114" />
                                <ellipse cx="180" cy="150" rx="25" ry="114" />
                                <path d="M66 150h228M180 36v228" />
                                <circle
                                    cx="180"
                                    cy="150"
                                    r="6"
                                    fill="currentColor"
                                />
                            </svg>
                        </div>
                    </article>
                    <article
                        class="practice-panel vinyasa-panel"
                        aria-labelledby="vinyasa-title"
                    >
                        <div class="practice-meta">
                            <span>POKRET &amp; ENERGIJA</span
                            ><span>VINYASA YOGA</span>
                        </div>
                        <div class="practice-content">
                            <div>
                                <h3 id="vinyasa-title">
                                    Pronađi<br /><em>svoj tok.</em>
                                </h3>
                                <p>
                                    Dah vodi, tijelo prati. Tečne sekvence
                                    povezuju snagu, ravnotežu i lakoću, od prvog
                                    zagrijavanja do zasluženog završnog odmora.
                                </p>
                                <span class="practice-tag"
                                    >Za dane kada želiš istraživati.</span
                                >
                            </div>
                            <svg
                                class="practice-art flow-art"
                                viewBox="0 0 360 300"
                                fill="none"
                                aria-hidden="true"
                            >
                                <ellipse
                                    v-for="angle in [
                                        0, 25, 50, 75, 100, 125, 150,
                                    ]"
                                    :key="angle"
                                    cx="180"
                                    cy="150"
                                    rx="115"
                                    ry="46"
                                    :transform="`rotate(${angle} 180 150)`"
                                />
                                <circle
                                    cx="180"
                                    cy="150"
                                    r="7"
                                    fill="currentColor"
                                />
                            </svg>
                        </div>
                    </article>
                    <article
                        class="practice-panel yin-panel"
                        aria-labelledby="yin-title"
                    >
                        <div class="practice-meta">
                            <span>TIŠINA &amp; OBNOVA</span
                            ><span>YIN YOGA</span>
                        </div>
                        <div class="practice-content">
                            <div>
                                <h3 id="yin-title">
                                    Dopusti si<br /><em>usporiti.</em>
                                </h3>
                                <p>
                                    Oslonac jastuka, mekoća deke i dovoljno
                                    vremena. Mirnija praksa s duljim
                                    zadržavanjem u položajima na podu. Manje
                                    napora, više prisutnosti.
                                </p>
                                <span class="practice-tag"
                                    >Za trenutke kada je manje dovoljno.</span
                                >
                            </div>
                            <svg
                                class="practice-art yin-art"
                                viewBox="0 0 360 300"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle
                                    v-for="radius in [30, 50, 70, 90, 110]"
                                    :key="radius"
                                    cx="180"
                                    cy="150"
                                    :r="radius"
                                />
                                <path d="M70 150h220" />
                                <circle
                                    cx="180"
                                    cy="150"
                                    r="6"
                                    fill="currentColor"
                                />
                            </svg>
                        </div>
                    </article>
                </div>
                <p class="practice-footnote">
                    Tri zamišljena smjera prakse. Jedna ideja: biti dobro u
                    vlastitom ritmu.
                </p>
            </section>
            <section
                class="studio-spread section-shell"
                aria-labelledby="studio-title"
            >
                <figure class="studio-photograph">
                    <img
                        :src="practice"
                        width="1100"
                        height="1467"
                        alt="Vizualizacija mirne yoga prakse u svijetlom studiju s drvenim podom."
                        loading="lazy"
                    />
                    <figcaption>SVJETLO. TIŠINA. TRENUTAK ZA TEBE.</figcaption>
                    <span class="photo-seal" aria-hidden="true"
                        >dođi<br /><em>kakav jesi.</em></span
                    >
                </figure>
                <div class="studio-story" data-enter>
                    <p class="eyebrow">{{ profile.name }}</p>
                    <h2 id="studio-title">
                        Mir nije<br />negdje daleko.<br /><em
                            >Počinje ovdje.</em
                        >
                    </h2>
                    <p
                        v-if="profile.short_description"
                        class="profile-description"
                    >
                        {{ profile.short_description }}
                    </p>
                    <p>
                        Meko jutarnje svjetlo. Drveni pod pod bosim stopalima.
                        Nekoliko tihih trenutaka prije prvog pokreta. Zamišljamo
                        studio kao mali gradski predah.
                    </p>
                    <p>
                        Vježbamo u malim grupama, uz pažljivo vodstvo i prostor
                        za pitanja. Nudimo prilagodbe, poštujemo tvoje granice i
                        ostavljamo vrijeme za šalicu čaja. Ili još malo tišine.
                    </p>
                    <Link :href="about()" class="text-link"
                        >Upoznaj studio <span aria-hidden="true">↗</span></Link
                    >
                </div>
            </section>
            <section class="breathing-space" aria-labelledby="breath-title">
                <div class="breath-orbits" aria-hidden="true">
                    <i></i><i></i><i></i><i></i>
                </div>
                <p>NE MORAŠ NIKAMO ŽURITI.</p>
                <h2 id="breath-title">
                    I ovaj trenutak<br /><em>može biti tvoj.</em>
                </h2>
                <svg aria-hidden="true"><use href="#lotus-flower" /></svg>
            </section>
            <section
                class="first-visit section-shell"
                aria-labelledby="visit-title"
            >
                <div data-enter>
                    <p class="eyebrow">TVOJ PRVI DOLAZAK</p>
                    <h2 id="visit-title">
                        Sve počinje<br /><em>znatiželjom.</em>
                    </h2>
                    <p>
                        Ne trebaš biti fleksibilan.<br />Ne trebaš znati
                        položaje.<br />Dovoljno je doći kakav jesi.
                    </p>
                </div>
                <div class="visit-questions">
                    <details open>
                        <summary>
                            Što ponijeti?<span aria-hidden="true">+</span>
                        </summary>
                        <p>
                            Udobnu odjeću i bočicu vode. U našem zamišljenom
                            studiju čekaju te prostirke, blokovi, trake i deke.
                            Svoju prostirku slobodno donesi ako je voliš imati
                            uz sebe.
                        </p>
                    </details>
                    <details>
                        <summary>
                            Kako izgleda prvi sat?<span aria-hidden="true"
                                >+</span
                            >
                        </summary>
                        <p>
                            Dođi desetak minuta ranije da upoznaš prostor i
                            učitelja. Počinjemo pažnjom na dah, nastavljamo
                            vođenim pokretom i završavamo odmorom. Pauzu možeš
                            uzeti u svakom trenutku.
                        </p>
                    </details>
                    <details>
                        <summary>
                            Koju praksu odabrati?<span aria-hidden="true"
                                >+</span
                            >
                        </summary>
                        <p>
                            Hatha nudi sporije upoznavanje osnova, Vinyasa više
                            dinamike, a Yin vrijeme za mirnije položaje.
                            Pročitaj opise praksi i javi nam što želiš pronaći u
                            svojem prvom dolasku.
                        </p>
                    </details>
                    <details>
                        <summary>
                            Moram li imati iskustva?<span aria-hidden="true"
                                >+</span
                            >
                        </summary>
                        <p>
                            Ne. Svaki početak je dobrodošao. Učitelj nudi
                            različite varijante položaja, a ti biraš onu koja ti
                            odgovara. Fizičke prilagodbe nudimo samo uz tvoju
                            suglasnost.
                        </p>
                    </details>
                </div>
            </section>
            <footer id="kontakt" class="invitation">
                <div class="invitation-top section-shell">
                    <p class="eyebrow">NAPRAVI PROSTOR ZA SEBE</p>
                    <a href="#public-content" class="back-top"
                        >Na početak <span aria-hidden="true">↑</span></a
                    >
                </div>
                <div class="invitation-title section-shell" data-enter>
                    <h2>Tvoj mir.<br /><em>Tvoj ritam.</em></h2>
                    <svg aria-hidden="true"><use href="#lotus-flower" /></svg>
                </div>
                <div class="invitation-bottom section-shell">
                    <div>
                        <p class="contact-intro">Počnimo upoznavanjem.</p>
                        <p>
                            Imaš pitanje o studiju ili prvom dolasku?<br />Veselimo
                            se tvojim riječima.
                        </p>
                        <Link :href="about()" class="pill-link light-pill"
                            >Upoznaj studio
                            <span aria-hidden="true">↗</span></Link
                        >
                    </div>
                    <PublicStudioContact :profile="profile" />
                    <div class="footer-links">
                        <Link :href="login()">Prijava u studio ↗</Link
                        ><a href="#praksa">Istraži praksu ↗</a
                        ><span>{{ profile.name }}</span>
                    </div>
                </div>
                <div class="colophon section-shell">
                    <span>lotus · MALO PROSTORA ZA SEBE.</span>
                    <p>
                        Demo koncept. Priča, programi i AI vizuali prikazuju
                        zamišljeni studio.
                    </p>
                </div>
            </footer>
        </main>
    </div>
</template>
<style scoped src="./lotus-home.css"></style>
