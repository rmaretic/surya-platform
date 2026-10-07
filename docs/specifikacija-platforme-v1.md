# Platforma za yoga studije u Hrvatskoj

## Objedinjena produktna, tehnička i operativna specifikacija

**Verzija:** 1.6 — 3. listopada 2026.  
**Namjena:** temelj za daljnji development, razradu zadataka i implementaciju uz Codex.  
**Status:** konsolidacija razgovora; aplikacija nije implementirana.  
**Zamjenjuje:** početnu specifikaciju 0.1 od 27. rujna 2026.

Dokument uključuje početni proizvod i sve razmatrane kasnije funkcionalnosti. Razlikuje potvrđeni smjer, preporuke i otvorene odluke. Faze su predloženi redoslijed, a ne rokovi ili obećanja kupcima.

Najvažnija promjena prema verziji 0.1: **individualni premium frontendovi unutar zajedničke aplikacije, uz ograničeni CMS za studio**, umjesto obvezne univerzalne teme.

## Sadržaj

1. Vizija i registar odluka
2. Poslovni model i cijene
3. Faze i potpuni katalog funkcionalnosti
4. Korisnici, uloge i ovlasti
5. Arhitektura i tenant izolacija
6. Individualni frontendovi, animacije i CMS
7. Javni site, jezici i SEO
8. Platform admin — administracija vlasnika platforme
9. Studio admin — administracija klijenta
10. Dashboard, metrike i izvještaji
11. Portal polaznika
12. Raspored, privatni i grupni treninzi
13. Rezervacije, otkazivanje i lista čekanja
14. Dolasci i QR skeniranje
15. Proizvodi, krediti i članarine
16. Stripe i novčani tokovi
17. Računi, hrvatska fiskalizacija i eRačuni
18. Email, domene i automatizacije
19. Google Meet i osobni kalendari
20. Videoteka i online programi
21. Prodaja, angažman i napredne funkcionalnosti
22. Domene, Nginx i HTTPS
23. Hosting, deployment, backup i monitoring
24. Onboarding, migracija i prestanak korištenja
25. Konceptualni model podataka
26. Backend ugovori i pozadinski poslovi
27. Sigurnost, privatnost i pristupačnost
28. Testiranje i kriteriji prihvata
29. Redoslijed implementacije i uputa za Codex
30. Otvorene odluke i izvori

## 1. Vizija i registar odluka

Platforma hrvatskim yoga studijima daje individualno dizajniranu web stranicu na vlastitoj domeni, raspored, rezervacije, naplatu i administraciju. Prošireni proizvod uključuje online treninge, videoteku, automatizacije i analitiku.

### 1.1. Potvrđeni smjer

| Tema | Smjer |
|---|---|
| Tržište | Početno Hrvatska i yoga studiji. |
| Stack | Laravel + Vue + Inertia, prema iskustvu vlasnika. |
| Multi-client | Jedna aplikacija i zajednička baza uz strogu izolaciju studija. |
| Diferencijacija | Premium individualni dizajn, slojevi, parallax, animacije i tranzicije. |
| Uređivanje | Vlasnik platforme priprema dizajn; studio uređuje poslovne podatke i dopuštena sadržajna polja. |
| Naplata treninga | Svaki studio ima svoj Stripe račun; Connect je preporučena implementacija. |
| Računi | Studio izdaje i fiskalizira vlastite račune preko vanjskog providera. |
| Hosting | Početno jedna Akamai instanca, resursi se povećavaju prema potrebi. |
| Domene | Vlastita domena studija, Nginx i zajednička Laravel aplikacija. |
| Postavljanje | Ručno dodavanje konfiguracija prihvatljivo; panel nije potreban samo radi novih klijenata. |
| Administracija | Zaseban platform admin i studio admin. |
| Email provider | Amazon SES — potvrđeno 3. listopada 2026. |
| Video provider | Bunny Stream (bunny.net) — potvrđeno 3. listopada 2026. |
| Web analitika | Fathom Analytics — odabrano 3. listopada 2026.; podaci pojedinačno po studiju i zbirno u platform adminu. |

### 1.2. Preporučeni početni izbori

EUR, Europe/Zagreb i hrvatski jezik; infrastruktura za EN i druge jezike. Modularni monolit; PostgreSQL kandidat, MySQL prihvatljiva alternativa. Jedan poslovni subjekt po tenantu u početku; više lokacija istog subjekta kasnije. Odvojeni računi polaznika po studiju. Stripe Connect direct charges i naknade koje Stripe naplaćuje studiju. Jedan fiskalni provider, zasebna organizacija/račun studija i certifikat kod providera. Jedan platformski email račun s verificiranim domenama studija. Vanjski video servis, object storage i backup izvan servera. Ručna evidencija dolazaka prije opcionalnog QR-a.

### 1.3. Otvoreno

Cijena, komercijalni paketi, naknada za individualnu izradu, opseg besplatnog postavljanja, ugovorna obveza, fiskalni provider, pravila paketa/članarina i redoslijed naprednih modula. Email, video i analitički provideri potvrđeni su u 1.1; njihovi planovi i kvote još se razrađuju. Primjeri nisu automatski produkcijske zadane vrijednosti. Opseg je dovoljno definiran za razvoj temelja aplikacije; preostale odluke zaključivati prije ovisnih modula prema 30.1.

### 1.4. Odgovori na 22 otvorene točke — 28. rujna 2026.

Brojevi prate pitanja vlasniku. Točke 1, 8 i 14 potvrđuju opseg; ostalo su preporuke za potvrdu, a ne odobrene produkcijske postavke. Ovaj registar zamjenjuje ranije alternativne preporuke gdje se razlikuju.

1. **Potvrđeni početni opseg:** uz stranicu, CMS, raspored, rezervacije, naplatu, fiskalizaciju i oba admina uključiti pakete, članarine, stalne grupe, automatski Meet i osnovnu videoteku. QR ostaje za kasnije, dolasci se evidentiraju ručno. Ostali napredni B/C moduli ne prelaze automatski u A.
2. **Paketi:** predložiti 5 dolazaka/45 dana i 10/90 dana od uspješne kupnje. Studio određuje broj, trajanje i dopuštene treninge. Datum treninga mora biti unutar valjanosti. Bez automatskog prijenosa i dijeljenja; administrativno produženje s razlogom i audit zapisom. Kredit se atomarno rezervira pri bookingu.
3. **Članarine:** mjesečna obnova od datuma kupnje, 4/8 dolazaka ili unlimited po izboru studija. Prava vrijede za plaćeno razdoblje, bez prijenosa. Otkazivanje zaustavlja sljedeću obnovu; neuspjela naplata ne daje nova prava. Zamrzavanje kasnije. Unlimited poštuje kapacitet i zabranu preklapanja.
4. **Stalne grupe:** automatske rezervacije unutar plaćenog razdoblja i raspoloživih prava. Upis nije potvrđena rezervacija svih budućih termina. Pri obnovi dati prioritet upisanim članovima prije objave preostalih mjesta; definirati rok obnove i oslobađanje mjesta. Neuspjela naplata ne smije beskonačno blokirati kapacitet. Podržati praznike i otkaz pojedinog termina.
5. **Otkazivanje:** predloženi rok 12 sati za grupne i 24 sata za privatne termine. Kasniji otkaz/nedolazak troši kredit ili plaćeni termin, bez dodatne automatske kaznene naplate. Spremiti verziju pravila uz rezervaciju, bez retroaktivnih izmjena.
6. **Povrati:** pravovremeni otkaz pojedinačno plaćenog termina vraća novac na izvorni način plaćanja, a booking kreditom vraća kredit s izvornom valjanošću. Otkaz studija daje puni povrat pojedinačne uplate ili vraćanje kredita s najmanje 7 dana preostale valjanosti. Zamjenski kredit umjesto novca samo uz izbor polaznika. Povrat iskorištenog paketa administrativni je postupak s pripadajućim fiskalnim dokumentima. Poslovna pravila ne nadjačavaju obvezna prava kupaca.
7. **Neplaćene rezervacije:** isključene kao zadana postavka samostalnog bookinga. Potvrda tek nakon provjerenog plaćanja ili rezervacije važećeg kredita/članarine. Privremeni checkout hold nije potvrđena rezervacija; rok uskladiti s providerom. Zakašnjelo plaćanje nakon gubitka mjesta riješiti povratom ili alternativom, bez overbookinga. Ručna iznimka djelatnika samo ako studio to omogući, uz razlog i rok naplate. Besplatni termini imaju eksplicitnu nultu cijenu.
8. **Potvrđena konfigurabilnost:** studio može mijenjati valjanost, rokove otkazivanja, pravila povrata i dopuštanje neplaćenih rezervacija. Platforma osigurava zadane vrijednosti, validaciju i verzioniranje; postavke ne mogu zaobići kapacitet, izolaciju i audit.
9. **Postojeća blagajna:** u A bez dvosmjerne POS integracije. Omogućiti unos vanjske prodaje i reference već izdanog računa te dodjelu paketa. Ne izdavati/fiskalizirati ponovno istu prodaju. Jasno označiti izvor prodaje i dokumenta.
10. **Fiskalni provider:** jedan adapter u pilotu, zaseban račun/organizacija svakog studija i njegov certifikat kod providera. FiskalAPI kandidat za mali volumen; e-racuni.hr kandidat za šire poslovne potrebe. Nema konačnog izbora: sandbox mora dokazati onboarding po OIB-u, dokumente, storna, idempotentnost i oporavak. Potvrditi ukupnu API cijenu po studiju i obračun ponovljenih zahtjeva.
11. **Kupnja za druge:** u A punoljetni polaznik kupuje za sebe. Administracija pomaže s upisom na njegov račun. Obitelji, maloljetnici, darovanje i korporativna kupnja kasnije, uz odvajanje platitelja i polaznika.
12. **Lokacije:** jedan tenant = jedan poslovni subjekt/OIB. Predvidjeti lokacije u modelu, pilot s jednom lokacijom. Drugi OIB je drugi tenant. Fizičku lokaciju ne izjednačavati automatski s fiskalnim poslovnim prostorom.
13. **Prijava:** email/lozinka, verifikacija adrese, reset i opcionalna dulja prijava na osobnom uređaju. Obvezni 2FA za platform admina i vlasnika studija, preporučen za djelatnike. Polaznički identitet odvojen po studiju. Magic link i društvena prijava kasnije.
14. **Potvrđene sve navedene vrste obavijesti:** kupnja, rezervacija, otkaz, promjena termina, računi, povrati, podsjetnici, istek i neuspjela naplata. Predloženi rokovi: trening 24 sata ranije, Meet uz potvrdu i 15 minuta ranije, istek paketa 7 i 1 dan ranije, obnova 3 dana ranije, neuspjela naplata odmah. Preskakati zastarjele i duplicirane poruke. Lista čekanja šalje poruke kada se isporuči taj modul. Marketing odvojen od operativnih poruka i uz odgovarajuće postavke/privole.
15. **Postavljanje:** do 6 glavnih stranica, jedan vizualni smjer i 2 kruga korekcija. Studio dostavlja tekstove, pravni sadržaj, logo i fotografije. Copywriting, dodatni jezici, složeni vizuali, redizajn i nove funkcionalnosti zasebno se procjenjuju. U ponudi definirati uvoz i obuku.
16. **Komercijalni prijedlog:** testirati 89 EUR/mjesečno i individualnu izradu 600–1.200 EUR prema opsegu. Pilot može biti 59 EUR/mjesečno prvih 6 mjeseci uz poznatu redovnu cijenu. To su hipoteze održivosti, ne potvrđeni tržišni iznosi; jasno navesti PDV status. Besplatno postavljanje ograničiti na prilagodbu ili opravdati ugovornom obvezom. Raniji cjenici iz odjeljka 2 ostaju alternative.
17. **Troškovi:** studio plaća Stripe, fiskalni servis, domenu i potreban Google plan. Platforma uključuje hosting, backup i definirane kvote emaila i analitike. Video zaseban dodatak s kvotama GB pohrane i isporuke usklađenima s Bunny obračunom; minute mogu biti dodatni produktni limit. Prikazati potrošnju i upozorenja; prekoračenja unaprijed ugovoriti.
18. **Podrška:** email/ticket radnim danom, cilj prvog odgovora sljedeći radni dan. Prekidi naplate/rezervacija prioritetni, bez obećanja 24/7 dežurstva. Studio rješava pitanja polaznika i raspored, platforma tehničke probleme. Dodatni dizajn i obuka posebno se naplaćuju.
19. **Dugovanje/izlazak:** podsjetnici prvih 7 dana; nakon 14 dana moguće zaustavljanje novih prodaja uz prethodnu obavijest i plan za postojeće obveze. Zadržati pristup nužan za račune i povrate. Nakon raskida 30 dana za izvoz; čuvanje/brisanje definirati po vrsti podataka. Ne brisati automatski financijske zapise i ne ukidati već plaćena prava bez plana ispunjenja ili povrata.
20. **Dizajn:** studio zadržava prava na dostavljene materijale; zajednički kod i komponente ostaju platformi. Individualan vizualni identitet bez kopiranja kompletnog dizajna drugom studiju. Formalnu ekskluzivnost ili prijenos prava zasebno ugovoriti i naplatiti.
21. **Oporavak:** predloženi RPO do 15 minuta i RTO do 4 sata zamjenjuju raniju hipotezu RPO 24 sata. Offsite backup, MySQL binlog/PITR, nadzor kopija i mjesečni restore test. Noćni backup sam nije dovoljan. Ciljevi nisu SLA dok nisu izmjereni i podržani operativnim pokrićem; RTO izvan radnog vremena ne obećavati bez dežurstva.
22. **Servisi — naknadno potvrđeno 3. listopada 2026.:** Amazon SES za email, Bunny Stream za video i Fathom Analytics za web analitiku. Time se zamjenjuju raniji prijedlozi Cloudflare Streama i Plausiblea/Umamija. Tajne ostaju na backendu, agregati se cacheiraju i izoliraju po studiju. Platform admin vidi pojedinačne i zbirne metrike prema 10.5. Izbor providera ne znači da su računi otvoreni ili integracije implementirane.

### 1.5. Provjereni cjenici i ograničenja kandidata

Provjera 28. rujna 2026.; cijene nisu ugovorna ponuda ni konačni izbor.

- [FiskalAPI](https://www.fiskalapi.hr/cijene): Starter 0 EUR/50 računa mjesečno s API-jem, Pro 29 EUR/1.000 računa. Starter blokira izdavanje nakon limita; potvrditi model više studija i ponovljene zahtjeve.
- [e-racuni.hr](https://www.e-racuni.hr/hrracuni/cjenik/): Premium 35 EUR mjesečno ili 29,75 EUR/mjesečno uz godišnju uplatu; API naveden u Premium funkcionalnostima. Ne pretpostavljati da najjeftiniji paket uključuje integraciju.
- [Plausible](https://plausible.io/docs/white-label): Business podržava Stats API i ugradnju; Sites API, managed proxy i više od 10 siteova zahtijevaju Enterprise.
- [Cloudflare Stream](https://developers.cloudflare.com/stream/pricing/): ranije razmatrana alternativa, nije odabrani provider. Povijesna provjera 28. rujna: 5 USD na 1.000 pohranjenih minuta mjesečno i 1 USD na 1.000 isporučenih minuta. Za odabrani Bunny Stream koristiti GB obračun iz odjeljka 20.

Prije produkcije potvrditi uvjete integracije, porezni tretman cijena, dokumente i obradu podataka. Provideri nisu kontaktirani niti su otvoreni računi.

## 2. Poslovni model i cijene

Prodaje se individualna web stranica i zajednička poslovna platforma. Vrijednost su dizajn, jednostavna rezervacija, ušteda administracije, onboarding i održavanje. SaaS naplata studiju odvojena je od naplate treninga polaznicima.

| Razmatrani model | Iznos | Status |
|---|---:|---|
| Osnovna pretplata | Oko 30 €/mj. | Ideja vlasnika, potrebna validacija ekonomike. |
| Start / Studio / Online | 29 / 59 / 89 €/mj. | Prijedlog, nije konačni cjenik. |
| Jedan glavni paket | 59 €/mj. | Alternativa za jednostavniju prodaju. |
| Pilot | 29 €/mj. prvih šest mjeseci za 3–5 studija | Prijedlog, bez trajnog obećanja. |

Cijene moraju jasno navesti uključuje li se primjenjivi PDV. Naknade Stripea, fiskalnog servisa, domene, Google plana i video prekoračenja imaju eksplicitnog platitelja.

Besplatno postavljanje može pokriti ograničenu prilagodbu postojećeg dizajna, dogovoren broj stranica i krugova izmjena, sadržaj koji studio dostavlja i ograničen uvoz. Potpuno individualan dizajn, priprema slojevitih vizuala i nove sekcije preporučeno se naplaćuju zasebno. Godišnji ugovor uz uključeno postavljanje je opcija, ne potvrđena odluka. Održavanje popravlja postojeću funkcionalnost; redizajn je dodatni rad.

Prije širenja razgovarati s 8–12 studija i pokušati dobiti 2–3 plaćena pilota. To su interni ciljevi, ne tržišne činjenice. Mjeriti izradu, podršku, potrošnju providera, odlaske studija i doprinos po pretplati. Primjer: 50 × 30 € daje 1.500 € prihoda; pola sata podrške po studiju daje 25 sati rada prije razvoja i prodaje.

## 3. Faze i potpuni katalog funkcionalnosti

**A:** pilot/MVP. **B:** proširena komercijalna verzija. **C:** kasnije prema potražnji. **X:** raspravljeno, ali nema plana implementacije. B/C funkcija može prijeći u A ako je nužna pilot studiju, uz eksplicitnu promjenu opsega.

| ID | Funkcionalnosti | Faza | Odjeljak |
|---|---|---|---|
| F01 | Tenant izolacija, uloge, domene, oba admina | A | 4–9 |
| F02 | Individualni dizajn, slojevi, parallax i tranzicije | A | 6 |
| F03 | Ograničeni CMS, mediji, preview, objava i revizije | A | 6–7 |
| F04 | Jezici, SEO, responzivnost i pristupačnost | A temelj | 7, 27 |
| F05 | Grupni raspored, ponavljanje, kapacitet | A | 12 |
| F06 | Privatni termini, dostupnost i buffer | A | 12 |
| F07 | Rezervacije, ručni upis, otkazivanje i povrat | A | 13 |
| F08 | Sigurna promjena termina | A | 13 |
| F09 | Jednokratni trening i paketi kredita | A | 15 |
| F10 | Stripe, ručne uplate, računi i fiskalizacija | A | 16–17 |
| F11 | Portal i kartica polaznika | A | 9, 11 |
| F12 | Dashboard, grafovi, dolasci/nedolasci | A | 10, 14 |
| F13 | Email potvrde/podsjetnici, vlastita domena, prvi dolazak | A | 18 |
| F14 | Osobni kalendar i ručni Meet link | A | 19 |
| F15 | Zamjena instruktora, praznici i masovne izmjene | A osnovno | 12 |
| F16 | Lista čekanja | B | 13 |
| F17 | Ponavljajuće članarine | A | 15 |
| F18 | Automatski Google Meet | A | 19 |
| F19 | Osnovna videoteka; programi i napredna video analitika kasnije | A / B | 20 |
| F20 | QR rezervacije skenira instruktor | B opcionalno | 14 |
| F21 | QR samostalni check-in | C alternativa | 14 |
| F22 | Probna ponuda, kuponi i podsjetnici na istek | B | 15, 18, 21 |
| F23 | Newsletter, reaktivacija i tjedni sažetak | B/C | 18, 21 |
| F24 | Stalno mjesto u grupi | A | 12 |
| F25 | Ciklusi, tečajevi i radionice | B/C | 12, 21 |
| F26 | Više lokacija i hibridni treninzi | C | 12 |
| F27 | Zamrzavanje članarine i proracija | C | 15 |
| F28 | Retreata, višednevni programi i obroci | C | 21 |
| F29 | Poklon bonovi i preporuka prijatelja | C | 21 |
| F30 | Interes za nove termine, feedback, izvori kupaca | C | 21 |
| F31 | Zahtjevi zamjene i izvještaji honorara | C | 12, 21 |
| F32 | Procjena profitabilnosti i zadržavanje | C | 10 |
| F33 | Oprema i dodatne usluge | C | 21 |
| F34 | PWA, SMS i push | C | 21 |
| F35 | AI pomoć za opise i prijevode | C | 21 |
| F36 | Uvoz/izvoz, arhiviranje, audit i upozorenja | A | 8–9, 24, 27 |
| F37 | Automatizacija infrastrukture, Forge/Caddy | C alternativa | 22 |
| F38 | Partnerski fiskalni račun | C alternativa | 17 |
| F39 | Marketplace, izvorne aplikacije, puni page builder | X | 21 |
| F40 | Vlastiti video/konferencije, medicinske preporuke, obračun plaća | X | 21 |
| F41 | Analitika javnog sitea, izvori posjeta i konverzijski događaji | B; osnovno mjerenje moguće u A | 10.4 |
| F42 | Platform admin: analitika pojedinog studija, usporedba i zbir svih studija | B, uz F41 | 8.4, 10.5 |

## 4. Korisnici, uloge i ovlasti

| Uloga | Ovlasti |
|---|---|
| Platform admin | Studiji, planovi, dizajni, domene, platform billing, integracije i operativni problemi. |
| Studio owner | Vlastiti studio, tim, financije, povrati, sadržaj i izvještaji. |
| Manager/reception | Raspored, polaznici, rezervacije i dolasci; financijske ovlasti zasebno. |
| Instructor | Vlastiti raspored, minimalni podaci polaznika, dolasci, zahtjevi za zamjenu. |
| Customer | Vlastiti profil, rezervacije, kupnje, računi i kupljeni online sadržaj. |

Laravel policies/services provjeravaju svaki zahtjev, izvoz i datoteku. Povrat novca, korekcija kredita, promjena ovlasti i ručna uplata zasebne su sposobnosti. Instruktor ih nema po zadanim postavkama. Predloženo: 2FA obvezan za platform admin i vlasnike studija; podržan za ostalo osoblje. Pozivnice su jednokratne, vremenski ograničene i vezane uz studio.

Support pristup platform admina mora biti auditiran, s razlogom i istekom. Eventualni impersonation ima vidljivu oznaku i ograničene osjetljive radnje. Deaktivacija zaposlenika ukida pristup i aktivne sesije prema politici.

## 5. Arhitektura i tenant izolacija

Jedan repozitorij, aplikacija, deployment i zajednička relacijska baza. Individualni frontendovi ne stvaraju zasebne backende. Moduli monolita: Tenancy, Identity, StudioSites, Content, Scheduling, Booking, Attendance, Commerce, Credits, Memberships, Fiscalization, Notifications, Video, Integrations, Reporting i PlatformBilling. Redis za queue/cache/locks prema potrebi; ne uvoditi mikroservise u MVP.

### 5.1. Odabir studija

Nginx prihvati poznati host i HTTPS; Laravel normalizira host i traži aktivnu verificiranu domenu; middleware postavlja tenant kontekst prije poslovnog route bindinga; backend autorizira korisnika i vraća pripadajuće podatke/dizajn. Nepoznat host se odbija. Klijentski `tenant_id` nije izvor konteksta. Proxy zaglavlja prihvaćati samo od pouzdanih proxyja.

Platform admin ima zasebnu platformsku domenu/rute i autentikacijski kontekst. Prilikom support rada odabrani tenant je eksplicitan.

### 5.2. Granice izolacije

- Poslovne tablice imaju `tenant_id`; iznimke i platformske tablice dokumentirane su.
- Koristiti scoped queries, route binding, policies i DB constraintove; global scope nije jedina zaštita.
- Relacije provjeravaju isti tenant; gdje je moguće koristiti složene foreign keyove.
- Cache, locks, datoteke, izvozi, webhookovi, tokeni i jobovi imaju tenant kontekst.
- Worker postavlja i čisti kontekst po jobu; nema curenja kroz dugovječne procese, SSR ili eventualni Octane.
- Email jedinstven po `(tenant_id, normalized_email)`; platform admin identitet odvojen.
- Host-only cookies, bez automatskog cross-domain SSO-a. Reset lozinke i verifikacija vezani uz studio.
- Javni DTO ne sadrži integracijske tajne ili cijeli model studija.

## 6. Individualni frontendovi, animacije i CMS

### 6.1. Struktura i dizajn

Podržati individualnu kompoziciju, višeslojni hero, parallax, otkrivanje sadržaja, povezane scroll sekvence i prijelaze. Raspored i kupnja moraju ostati brzi i jasni.

```text
resources/js/
  Pages/
    StudioSites/
      Lotus/{Home.vue,About.vue}
      Balance/{Home.vue,About.vue}
    PlatformAdmin/
    StudioAdmin/
    CustomerPortal/
  StudioSites/
    Lotus/{components,animations,styles}
    Balance/{components,animations,styles}
  Shared/{Booking,Schedule,Pricing,Motion}
```

`site_design` je dopušten ključ, npr. `lotus`, iz registra koji mapira stranice na Inertia komponente. Nema proizvoljnih putanja iz CMS-a. Aktivacija postojećeg dizajna je konfiguracija; novi kod zahtijeva build/deploy. Lazy import i odvojeni CSS sprječavaju preuzimanje svih dizajna. Zajedničke komponente ne dupliciraju provjeru naplate, cijena ili kapaciteta.

### 6.2. Tko što uređuje

| Područje | Studio | Platform/dizajner |
|---|---|---|
| Poslovni podaci | Raspored, cijene, paketi, instruktori | Podrška |
| Siguran sadržaj | Kontakt, FAQ, biografije, obavijesti | Definira shemu |
| Hero i istaknute slike/ponude | Samo ako shema dopušta, uz preview | Priprema osjetljive kompozicije |
| Fontovi/boje | Samo dopušteni presetovi | Glavna kontrola |
| Slojevi, layout i animacije | Bez uređivanja koda | Implementacija |

CMS je skup obrazaca po dizajnu, ne univerzalni page builder. Povezana animirana cjelina je jedan blok; shema određuje broj, varijante i dopušten redoslijed. Jednostavne sekcije mogu se premještati samo ako je dizajn to predvidio.

### 6.3. Mediji i objava

Nacrt, autorizirani preview, objava, povijest i povrat verzije. Shema sadržaja ima verziju; promjena dizajna traži mapiranje i pregled. Verzija zapisa sprječava prepisivanje paralelnih izmjena.

Mediji: tenant vlasništvo, alt tekst, format, dimenzije, fokus i opcionalna mobilna slika. Slojevita sekcija razlikuje široku pozadinu, transparentni prednji motiv i dekoraciju. Ako nema odgovarajućeg materijala, predvidjeti jednostavniju varijantu. Validirati duljine samo gdje kompozicija to zahtijeva. Sanitizirati rich text; zabraniti proizvoljan JS/PHP/Vue i neograničen CSS. CSS varijable koriste se za validirane vrijednosti brenda; stilovi sitea ne utječu na admin.

### 6.4. Pravila animacija

Prirodno scrollanje, mobilne pojednostavljene varijante, `prefers-reduced-motion`, sadržaj vidljiv ako inicijalizacija zakaže i kupnja ne čeka animaciju. Animacije reagiraju na dimenzije, fontove, prijevode i slike. Čistiti listenere/timere pri navigaciji. Preferirati transform/opacity gdje odgovara i mjeriti velike slike, video, blur i slojeve. Tipkovnica/fokus moraju ostati ispravni. Vizualni QA uključuje slabiji mobitel. Ne obećavati performanse bez mjerenja.

Izraditi prvi premium site i ponovno upotrebljive dijelove provjeriti na drugom demu. Drugi studio smije dobiti zaseban frontend; ista tema nije uvjet individualnosti.

## 7. Javni site, jezici i SEO

Početna, o studiju, instruktori, vrste treninga, raspored, cjenik, privatni termini, kontakt/lokacija, FAQ, obavijesti i pravila. Blog opcionalan; kasnije videoteka, programi, radionice i bonovi.

Javni početni HTML mora sadržavati tekst i SEO podatke. Kandidat je Inertia SSR; Blade je svjesna alternativa za javni dio. Tenant kontekst mora biti siguran i pri SSR-u. Naslovi, opisi, canonical, Open Graph, sitemap i hreflang samo za objavljene prijevode. Admin/portal nisu za indeksiranje; preview zahtijeva autorizaciju, ne samo `noindex`.

Odvojiti locale UI-ja, CMS prijevod i jezik emaila. HR početno; EN i dodatni jezici podržani modelom. Definirati fallback i ne prikazivati nepostojeći prijevod kao prevedenu SEO stranicu. Lokalizirati datum, vrijeme, broj i valutu.

## 8. Platform admin — administracija vlasnika platforme

### 8.1. Početni ekran

Broj aktivnih/pilot/suspendiranih studija, novi i ugašeni studiji, SaaS naplate i dospjele obveze, onboarding zadaci, integracijske greške, queue backlog, email/video potrošnja i operativne provjere. Uplate polaznika nisu prihod platforme.

### 8.2. Detalj studija

- Poslovni subjekt, OIB, odgovorna osoba, kontakti, jezik i zona.
- Status: draft, onboarding, ready, active, suspended, offboarding, archived.
- Domene, glavna domena, verifikacija, DNS/TLS status i certifikat kad je dostupno.
- Dizajn/verzija, CMS shema, preview i feature flagovi.
- Plan, ugovorena cijena, pilot, početna naknada, limiti i dodatni radovi.
- Stripe account ID i stanje naplate/isplate; tajne se ne prikazuju.
- Fiskalni provider/organizacija, API/certifikat status i neriješeni dokumenti.
- Email domena, dostava i potrošnja; Google autorizacija; video potrošnja.
- Auditiran support pristup, izvoz i povijest podrške kao opcionalna interna evidencija.

### 8.3. Globalni alati

Planovi, dostupni moduli, registar dizajna, verzije email predložaka, sistemske obavijesti i konfiguracije providera. Promjene planova ne mijenjaju prešutno postojeće ugovore. Pregled sanitiziranih integracijskih grešaka, pokušaja i dopuštenih retry akcija. Reconciliation i stanje backupa dostupni operateru. Ne izvršavati proizvoljni raw webhook ili shell iz UI-ja.

MVP ne upravlja Nginxom: operater ručno postavlja infrastrukturu i potvrđuje/provjerava status. Kasnija automatizacija je opcionalna.

### 8.4. Analitika klijenata — potvrđeni zahtjev verzije 1.2

Vlasnik platforme ima u svojem adminu odjeljak Analitika s prikazima: svi studiji, odabrani skup studija i pojedinačni studio. Pregled pojedinog studija dostupan je i iz njegova detalja, bez potrebe za impersonationom. Vidljivi su promet, izvori, popularne stranice, uređaji, kampanje i dostupni konverzijski događaji iz F41. Poslovni pokazatelji iz vlastite baze mogu biti prikazani uz njih kao jasno odvojena skupina, bez poistovjećivanja uplata studija s prihodom platforme.

Podržati odabir razdoblja, usporedbu s prethodnim razdobljem, filter studija i preglednu tablicu rezultata po studiju. Klik na redak otvara detalj uz očuvano razdoblje. Zbirni grafovi i CSV izvoz slijede pravila iz 10.5. Zadani ukupni pregled uključuje sve produkcijske studije kojima je analitika uključena; prikazuje broj uključenih studija, nedostupne izvore i vrijeme zadnjeg osvježavanja. Status studija dodatni je filter, ne razlog za tiho uklanjanje njegove povijesti iz izvještaja.

Platform owner ima eksplicitnu ovlast `platform.analytics.read`; eventualna support uloga dobiva je zasebno. Izvoz ima zasebnu ovlast i audit. Studio admin i dalje vidi samo vlastite podatke, bez pristupa globalnoj usporedbi ili drugim klijentima. Platformska autorizacija nije razlog za isključivanje tenant zaštite na ostalim rutama.

## 9. Studio admin — administracija klijenta

Zajedničko responzivno sučelje, logo i eventualna naglasna boja. Navigacija: Pregled, Raspored, Rezervacije/dolasci, Polaznici, Ponuda/članarine, Plaćanja/računi, Online sadržaj, Poruke, Sadržaj stranice, Izvještaji, Tim i Postavke.

### 9.1. Dnevni rad

Današnji treninzi, instruktor i broj mjesta; popis polaznika; ručni dolazak/QR; promjena instruktora; otkazivanje uz pregled posljedica. Brze akcije: novi termin, rezervacija za osobu, evidencija uplate, servisna obavijest i otvaranje računa.

Upozorenja: neuspjela naplata, greška fiskalizacije, nedostajući instruktor/Meet link, istek integracije i paketa. Svako upozorenje vodi na pogođeni zapis.

### 9.2. Kartica polaznika

Kontakt, preference, prvi dolazak, oznake, paketi/saldo/ledger, članarine, buduće i prošle rezervacije, dolasci/nedolasci, kupnje, računi, povrati i povijest poruka. Organizacijske bilješke imaju ograničen pristup; zdravstveni podaci nisu dio MVP-a. Ručne korekcije zahtijevaju ovlast i audit. Arhiviranje ne briše financijsku povijest.

### 9.3. Postavke

Pravila rezervacije/otkazivanja, lokacije, tim, ponuda, integracije, poruke, jezici, dopuštena CMS polja i limiti. Nema uređivanja serverskih postavki, proizvoljnih putanja dizajna ili drugih studija. Pretrage/izvozi uvijek su tenant-scoped.

## 10. Dashboard, metrike i izvještaji

### 10.1. Definicije

Svaka metrika ima izvor, vremensku osnovu, statuse i formulu. Razlikovati datum naplate, računa i treninga. Filtere iz zone studija pretvoriti u UTC intervale. Periodi: danas/tjedan/mjesec/godina/prilagođeno; tekući mjesec usporediti s istim brojem dana prethodnog mjeseca.

| Metrika | Definicija |
|---|---|
| Naplaćeno | Uspješne uplate po vremenu naplate; ručne uplate imaju oznaku izvora. |
| Povrati | Uspješno izvršeni povrati po vremenu izvršenja. |
| Naplaćeno nakon povrata | Naplate minus povrati u periodu; povrat može biti za raniju kupnju. |
| Naknade | Dostupne procesorske naknade; nepoznato nije nula. |
| Isplate | Payout na banku, nije nova prodaja. |
| Rezervacije | Potvrđene rezervacije za termine u periodu; datum kreiranja zaseban je filter. |
| Popunjenost | Zauzeta potvrđena mjesta / kapacitet neotkazanih termina; snapshot kapaciteta. |
| Posjećenost | Evidentirani dolasci / kapacitet održanih termina. |
| Aktivni polaznici | Jedinstvene osobe s barem jednim dolaskom u periodu. |
| Novi polaznici | Prvi evidentirani dolazak u periodu. |
| Nedolasci | Potvrđeni no-show / očekivani polaznici nakon primjene pravila otkazivanja; kasna otkazivanja zasebno. |
| Aktivne članarine | Važeća prava na odabrani datum, ne samo uključen auto-renew. |

Bez svih troškova ne koristiti naziv „dobit”. Paket od deset dolazaka nije deset održanih treninga na dan naplate. Operativni dashboard nije zamjena za računovodstvo.

### 10.2. Grafovi

Naplate kroz vrijeme i po proizvodu; popunjenost po vrsti, danu/satu i instruktoru; rezervirano naspram prisutnih; otkazivanja/nedolasci; novi/povratni polaznici; članarine pred istekom; neuspjele obnove; konverzija probnog dolaska. Klik otvara pripadajući filtrirani popis.

Izvoz za računovođu: računi, ispravci, uplate i reference za period, valuta i datum presjeka. CSV i izvorni računi prioritet; formatirani PDF izvještaj kasnija pogodnost.

### 10.3. Napredno — C

Kohorte prvog dolaska, povrat unutar definiranih 30/60/90 dana, izvori kupaca, napušteni checkout prema dostupnim događajima, video analitika i doprinos termina. Nezrele kohorte označiti.

Honorari i izravni troškovi daju procjenu doprinosa. Raspodjela prihoda paketa/neograničene članarine po dolasku mora biti eksplicitna, verzionirana i označena procjenom. Spriječiti dvostruko brojanje prihoda. Puni obračun plaća i računovodstvo nisu planirani.

### 10.4. Analitika javne web stranice — dodatak verzije 1.1

Modul dopunjuje poslovnu analitiku. Odabrani managed servis je Fathom Analytics; platforma ostaje autoritativni izvor rezervacija, naplata i povrata. Jedan platformski Fathom račun s odvojenim site ID-jem po studiju; Laravel preko API-ja dohvaća podatke za vlastite Vue dashboardove. Ne graditi vlastiti opći analytics engine u MVP-u i ne instalirati zaseban analytics stack na malu produkcijsku instancu.

Studio vidi posjete/pageviews, posjetitelje prema definiciji providera, najposjećenije stranice, izvore/referrere/UTM kampanje, uređaje i trendove. Uz to prikazati odabrane događaje: otvaranje rasporeda, klik rezervacije, početak checkouta i, gdje se ispravno može povezati, potvrđenu kupnju. Ne izjednačavati anonimnog posjetitelja s polaznikom niti različite browser događaje predstavljati kao precizan korisnički funnel bez podržane identifikacije. Broj posjeta ne mora odgovarati stvarnim kupnjama zbog blokatora, nedostatka pristanka, botova i različitih definicija.

Za svaki tenant odvojen provider site/property ID. Browser šalje događaje izravno provideru, asinkrono. Site ID nije tajni API ključ; provider kredencijali ostaju na backendu. Laravel dohvaća agregate za dopušteni tenant preko adaptera i cacheira ih, npr. 15–60 minuta. Ne zove provider na svako osvježavanje svakog grafa. Admin autorizacija određuje site ID; browser ne može zadati tuđi. Ne koristiti javni share/embed link za povjerljive podatke. Ako API ili privatno ugrađivanje zahtijeva skuplji plan, potvrditi cijenu prije obećanja funkcije.

Prikupljati samo dopuštene rute i svojstva. Isključiti platform/studio admin, staging, preview i privatne stranice. Ukloniti query tokene, emailove, imena, booking/payment identifikatore i druge osobne podatke iz URL-ova/payloadova. UTM parametre validirati i minimizirati. Ne uključivati session replay ili snimanje obrazaca. Procijeniti obveze privatnosti/pristanka prema stvarnoj konfiguraciji; oznaka cookieless sama nije pravna potvrda.

Kod Inertia navigacije mjeriti jedan pageview po stvarnoj navigaciji; ne brojati preload/prefetch ili dvostruko inicijaliziranje trackera. Kritični tok kupnje nikad ne čeka analytics odgovor. Ako se šalje serverski purchase događaj, pokrenuti ga tek nakon potvrđene naplate, idempotentno i samo s dopuštenim podacima; definirati atribuciju bez umetanja PII-ja u provider. Bez takvog mapiranja prikazati promet i poslovne konverzije kao odvojene pokazatelje.

Trošak pratiti po tenantima, ukupnom broju sites i događaja. Primjer za planiranje, ne benchmark: 20 studija × 5.000 pageviewa = 100.000 mjesečno; dva dodatna događaja po pageviewu daju 300.000 događaja ukupno, prosječno oko 0,12/s kroz 30 dana. Vršno opterećenje može biti višestruko veće. Direktno browser→provider slanje ne stvara dodatni Laravel zapis za svaki događaj. Vlastita implementacija bi dodala HTTP/DB upise, indekse, agregacije, retenciju i održavanje. RAM/CPU postotak nije moguće jamčiti bez mjerenja.

Ako provider zakaže, site/booking i dalje rade; dashboard prikazuje zadnje podatke i vrijeme osvježavanja. Rate limit i retry ne smiju blokirati financijske queueove. Self-hosted analitika kasnija je opcija na zasebnoj instanci uz plan backupa/retencije. Provjeriti broj domena, API pristup, retenciju, izvoz i trošak prekoračenja kod odabira providera.

Kriteriji prihvata: tenant ne može dohvatiti tuđe agregate; Inertia pageview nije dvostruk; staging i privatni podaci nisu poslani; nema PII-ja/tokena u događajima; adblock ili nedostupan provider ne blokira kupnju; ponovljeni webhook ne duplicira purchase događaj; dashboard razlikuje izmjereni promet i autoritativne poslovne podatke.

Fathom planiranje troška: početni budžet 15–25 USD mjesečno za cijelu platformu, ne po studiju. Prema provjeri 3. listopada 2026. plan od 15 USD uključuje 100.000 obračunskih jedinica i 50 siteova; 25 USD uključuje 200.000, a 45 USD 500.000 jedinica. Pageviewi, custom eventi i API zahtjevi troše kvotu. Uključeni API limit je 600 zahtjeva na sat i 5 istovremenih zahtjeva. Dohvate rasporediti kroz queue i cache; ne dohvaćati svaki graf pri svakom otvaranju admina. Konačan plan odrediti prema izmjerenoj potrošnji, bez automatskog uključivanja plaćenog API dodatka.

Reference: [Fathom cjenik](https://usefathom.com/pricing), [API](https://usefathom.com/api/v1), [izvještaji](https://usefathom.com/api/v1/reports) i [API limiti](https://usefathom.com/api/v1/rate-limits). Provider je odabran; pretplata nije kupljena. Plausible i Umami ostaju povijesno razmatrane alternative.

### 10.5. Agregiranje analitike za platform admin

Zbirni pregled je agregacija odvojenih site/property izvora, ne novi zajednički tracker. Jedan događaj ne slati dodatno u globalni property samo radi zbrajanja. Backend mapira dozvoljene tenant/site identifikatore i dohvaća ili unaprijed priprema njihove agregate. Provider ne mora podržavati jedan cross-site API poziv; adapter može postupno dohvatiti izvore uz rate limit, cache i pozadinsku obradu.

| Pokazatelj | Pravilo zbirnog prikaza |
|---|---|
| Pregledi i događaji | Zbroj istih metrika za isto razdoblje; izbjegavati dvostruke izvore za alias domene istog studija. |
| Posjeti/sesije | Zbroj sesija pojedinih siteova, uz jasnu oznaku da nema deduplikacije među studijima. |
| Jedinstveni posjetitelji | Prikazati kao „Zbroj posjetitelja po studijima”, ne kao jedinstvene osobe cijele platforme. Ista osoba može biti ubrojena kod više studija. Ne uvoditi cross-site praćenje radi deduplikacije. |
| Konverzijske i druge stope | Ponovno izračunati iz zbroja odgovarajućih brojnika i nazivnika, ako su usporedivi. Ne računati običan prosjek postotaka. Bez potrebnih podataka prikazati samo rezultate po studiju. |
| Prosječno trajanje | Ponderirati odgovarajućim brojem mjerenih posjeta ako provider daje potrebne podatke; inače ne izmišljati globalni prosjek. |
| Izvori/uređaji/kampanje | Grupirati samo usklađene dimenzije; nepoznate vrijednosti prikazati zasebno. |
| Popularne stranice | Zadržati studio i domenu uz putanju, tako da `/` različitih klijenata nije ista stranica. |
| Naplate i rezervacije | Autoritativni podaci iz baze; iznosi odvojeni po valuti ako se kasnije uvede više valuta. Nisu prihod platforme. |

Za zbir koristiti zajedničko razdoblje i izvještajnu vremensku zonu, početno Europe/Zagreb. Ako provider ne može uskladiti izvore različitih zona/definicija, ograničenje jasno prikazati ili rezultate razdvojiti. Prikazati obuhvat: uključeni/ukupni studiji, djelomično dostupni podaci i vrijeme osvježavanja. Neuspjeli dohvat nije nula. Usporedba razdoblja upozorava kad se promijeni broj mjerenih studija ili pokrivenost, kako rast onboardinga ne bi izgledao kao rast prometa postojećih klijenata.

Cache ključ uključuje opseg ovlasti, sortirani skup tenant ID-jeva, period, zonu i filtre. Globalni rezultati ne smiju završiti u tenant cacheu. Veći zbirni izvještaji/izvozi izvršavaju se u pozadini; dashboard prikazuje zadnji raspoloživi skup i ne blokira rezervacije. Podaci po studiju koriste se za drill-down bez ponovnog nekontroliranog API fan-outa.

Kriteriji prihvata: platform owner vidi pojedini i zbirni pregled; studio admin ne može pristupiti drugim studijima ni promjenom filtera; aditivni zbir odgovara redovima za isti opseg; jedinstveni posjetitelji nisu lažno deduplicirani; stope nisu prosjek postotaka; nedostupni izvori označeni su; CSV odgovara filtriranom pregledu; promjena broja studija u usporedbi vidljiva je.

## 11. Portal polaznika

Registracija/prijava/reset unutar studija; profil i preference; buduće/prošle rezervacije; promjena/otkazivanje; QR; paketi, saldo, članarine i obnova; kupnje/računi; Meet i videoteka. Jasna cijena i uvjeti prije kupnje, minimalan broj koraka na mobitelu.

Guest checkout nije potvrđen zahtjev; eventualno uvođenje traži sigurno povezivanje kupnje s identitetom. Čekanje webhooka prikazuje obradu, ne lažnu uspješnu rezervaciju. Statusi plaćanja, rezervacije i računa odvojeni su. Otkazivanje obnove ne ukida već plaćena prava bez eksplicitnog pravila.

## 12. Raspored, privatni i grupni treninzi

Razlikovati vrstu treninga, pravilo ponavljanja i konkretni `class_session`. Termin sadrži početak/kraj, instruktora, prostor/online način, kapacitet, pravila i status. Booking pripada konkretnom terminu.

### 12.1. Ponavljanje i izmjene

Idempotentno generirati termine za ograničen horizont. Pravila u lokalnom vremenu/IANA zoni, trenuci u UTC-u; ponedjeljak u 18:00 ostaje u 18:00 nakon DST-a. Definirati ponašanje za dvostruko/nepostojeće vrijeme.

Izmjena jednog, ovog i budućih ili cijele serije različite su operacije. Prije promjene rezerviranih termina prikazati posljedice, obavijesti i povrate. Kapacitet ne smanjivati ispod zauzetosti bez eksplicitnog postupka. Arhiviranje vrste ne briše povijest.

### 12.2. Privatni termini

Radno vrijeme, trajanje, buffer prije/poslije, iznimke, minimalna najava, horizont rezervacije i prostor. Provjera konflikta obuhvaća grupne i privatne termine. Vanjska Google zauzetost može biti kasnija nadogradnja; MVP je ne pretpostavlja.

### 12.3. Operacije

Zamjena instruktora, praznici, masovne izmjene i obavijesti. Kasniji zahtjev zamjene ima requested/assigned/approved/cancelled; voditelj potvrđuje prije obavijesti. Novi instruktor ne smije imati konflikt.

### 12.4. Stalne grupe i ciklusi

Stalno mjesto generira konkretne rezervacije u ograničenom horizontu, provjeravajući članarinu i kapacitet. Definirati istek/neuspjelu obnovu. Otkazivanje jednog dolaska ne ukida seriju; mjesto može prijeći listi čekanja.

Ciklus/tečaj prodaje skup povezanih termina. Upis potvrđuje sva mjesta ili odbija operaciju; djelomičan upis nije prešutan. Propuštanje, zamjenski dolasci i povrati imaju eksplicitna pravila.

### 12.5. Kasnije

Više lokacija istog subjekta s filtrima i resursima. Hibridni termin ima odvojene fizičke/online kvote; isti instruktor nije konflikt sam sa sobom. Radionice i retreata imaju odgovarajuće proizvode.

## 13. Rezervacije, otkazivanje i lista čekanja

### 13.1. Pravila

Backend provjerava cijenu i pravo. Jedan aktivan booking po osobi i terminu. Snapshot pravila/cijene pri kupnji. Hold i potvrđena mjesta dijele atomsku provjeru kapaciteta. Zaključavati inventar termina; za privatne termine zajednički resurs/vremenski raspon. Lock samo postojeće rezervacije ne štiti od dva nova preklapajuća upisa. Odrediti redoslijed lockova i retry za deadlock.

### 13.2. Kartični tok

1. Provjeri tenant, osobu, termin, kapacitet i cijenu.
2. U transakciji kreiraj hold s istekom i narudžbu.
3. Izvan dugotrajne DB transakcije kreiraj checkout uz idempotency key.
4. Potpisani webhook potvrđuje stvarni uspjeh uplate.
5. Atomski potvrdi booking ili obradi zakašnjelu uplatu.
6. Nakon commita pouzdani poslovi pokreću dokumente i obavijesti.

Hold uskladiti s istekom checkouta i metodama plaćanja. Za odgođenu naplatu potreban je definiran tok; MVP uključuje samo podržane metode. `checkout.session.completed` nije uvijek konačna naplata; provjeriti stvarni status.

Uplata nakon isteka holda ponovno atomski provjerava kapacitet. Ako ga nema: refund postupak i jasna poruka, bez prepunjavanja ili lažne potvrde. Vanjski API timeout nije dokaz da operacija nije uspjela.

### 13.3. Krediti i ručni upis

Kreditni booking u istoj transakciji provjerava saldo, valjanost na datum treninga i dopuštene usluge te upisuje potrošnju. Ručni upis podliježe istom kapacitetu. Ako studio dopušta plaćanje kasnije, rezervacija ima due/unpaid status i rok; nije paid po zadanim postavkama.

### 13.4. Otkazivanje i promjena termina

Konfigurabilan rok. Pravodobno otkazivanje vraća kredit; kasno/no-show troše ga prema pravilima. Refund novca zaseban je tok; ručna iznimka ima autora i razlog. Otkazivanje studija vraća pravo/novac i pokreće odgovarajuće dokumente.

Premještanje atomski osigurava novo mjesto i prenosi pravo. Ako novo nije dostupno, stari booking ostaje. MVP podržava ekvivalentne termine; razlika cijene/prava zahtijeva zaseban potvrđeni tok. Ažurirati podsjetnike, QR i kalendarske podatke.

### 13.5. Lista čekanja

FIFO, jedna vremenski ograničena ponuda po oslobođenom mjestu, uz hold. Pri prihvatu ponovno provjeriti pravo/naplatu. Istek aktivira sljedeću osobu. Ne naplaćivati automatski bez definiranog pristanka i toka. Ne slati ponude nakon roka rezervacije/početka.

## 14. Dolasci i QR skeniranje

### 14.1. Ručna evidencija — A

Popis polaznika s akcijom Prisutan, vremenom i autorom. Attendance je zaseban zapis od bookinga/plaćanja. Nakon termina neprijavljene osobe najprije označiti za pregled; instruktor potvrđuje no-show kako zaboravljeno skeniranje ne bi automatski uzrokovalo kaznu. Ispravak ima audit.

### 14.2. Instruktor skenira QR rezervacije — B

Polaznik u portalu otvara rezervaciju i QR. Instruktor se prijavi, otvori konkretni trening i skenira kamerom mobitela. QR sadrži nepredvidiv token, ne ime/email ili poslovne tajne. Backend provjerava studio, termin, status rezervacije, ovlast osoblja i vremenski prozor. Primjer prozora: 30 minuta prije početka do kraja; vrijednost je konfigurabilna.

Rezultati su dolazak evidentiran, već evidentiran, pogrešan termin, otkazana/nevažeća rezervacija ili problem veze. Pokazati ime radi provjere osobe. Screenshot QR-a može se podijeliti; to nije biometrijska potvrda identiteta. Dvostruko skeniranje je idempotentno i ne troši drugi kredit. Token se opoziva/rotira pri otkazivanju ili relevantnoj promjeni rezervacije.

Kamera traži HTTPS i dozvolu. Ručni popis je uvijek fallback. Početno ne obećavati offline check-in: bez veze ne prikazivati trajno potvrđen dolazak ako nije zapisan na serveru.

### 14.3. Polaznik skenira QR treninga — C alternativa

Studio prikaže kratkotrajni/rotirajući kod konkretnog treninga. Prijavljeni polaznik potvrđuje dolazak uz važeću rezervaciju i prozor. To ubrzava grupu, ali ne dokazuje fizičku prisutnost jer se kod može proslijediti. Uključivanje je svjesna postavka studija; ručna kontrola ostaje moguća. Statičan javni kod nije dovoljan za pouzdanu evidenciju.

Dolazak nikada drugi put ne naplaćuje paket. Dashboard odvojeno prikazuje rezervirano, prisutno, kasno otkazano i potvrđeni nedolazak.

## 15. Proizvodi, krediti i članarine

### 15.1. Ponuda i snapshotovi

Podržani proizvodi kroz faze: pojedinačni grupni trening, privatni termin, paket N dolazaka, mjesečna članarina, probni paket, ciklus, radionica, online trening, video pristup/program, poklon bon i dodatna usluga. Proizvod određuje prava i pravila, a cijena valutu i razdoblje valjanosti ponude.

Kupljena stavka čuva naziv, cijenu, porezni profil i pravila iz trenutka kupnje. Promjena cjenika ili arhiviranje proizvoda ne briše prava postojećih kupaca. Početno jedna prodajna valuta po studiju. Iznose spremati u najmanjoj valutnoj jedinici, ne floatom.

### 15.2. Kreditni ledger

Paket ima broj dolazaka, aktivaciju, istek, dopuštene vrste treninga i kasnije lokacije. Predloženo je provjeravati valjanost na datum treninga; druga politika zahtijeva eksplicitnu konfiguraciju. Definirati aktivira li se paket kupnjom ili prvim dolaskom prije prodaje.

Ledger bilježi dodjelu, rezervacijsku potrošnju, povrat, istek i korekciju. Saldo je izveden iz zapisa; eventualna projekcija salda usklađuje se s ledgerom. Zapisi se ne prepisuju; koriste se kompenzacijski unosi. Svaki potrošeni/povratni kredit povezan je s bookingom i jedinstvenom poslovnom operacijom. Pri više paketa definirati redoslijed, npr. najraniji istek koji zadovoljava termin.

### 15.3. Članarine — A

SaaS pretplata studija i članarina polaznika zasebni su sustavi. Početna članarina ima jednostavno razdoblje, broj prava ili neograničene dopuštene dolaske, cijenu i auto-renew.

Odvojiti payment/subscription status od entitlement statusa. Primjeri: pending, active, past_due, ended; `cancel_at_period_end` je zasebna zastavica, ne zamjena za active. Obnova prava slijedi potvrđenu naplatu uz eventualno unaprijed definirani grace period. Neuspjela obnova ima obavijest, ograničene pokušaje procesora i poveznicu za ažuriranje metode plaćanja.

Otkazivanje buduće obnove ostavlja već plaćeno razdoblje. Promjena cijene, datuma obnove i prava ne djeluje retroaktivno. Definirati smiju li se rezervirati termini izvan već plaćenog razdoblja; sigurni početni default je ne, osim eksplicitnog modela stalnih grupa.

### 15.4. Napredne članarine — C

Zamrzavanje ima početak/kraj, razlog, maksimalno trajanje i pravilo pomicanja isteka/naplate. Provjeriti postojeće rezervacije. Proracija, upgrade/downgrade, prijenos neiskorištenih kredita i obiteljski modeli nisu podrazumijevani; implementirati tek nakon specifikacije pojedinog pravila. Obiteljski modeli nisu bili potvrđena funkcija, pa ostaju otvoreni proširivi slučaj, ne obveza ove verzije.

## 16. Stripe i novčani tokovi

### 16.1. Odabrani smjer

Svaki studio ima svoj Stripe račun i prima svoje naplate. Preporuka je Connect s direct charges i modelom u kojem Stripe obračunava procesorske naknade studiju. Platforma zasebno naplaćuje SaaS pretplatu i eventualnu izradu. Ne prikupljati ručno tajne ključeve svih studija ako je povezivanje izvedivo kroz Connect. Konačnu konfiguraciju odgovornosti, država, naknada i onboardinga potvrditi prije implementacije. Direct charges odgovaraju SaaS modelu prema [Stripe dokumentaciji](https://docs.stripe.com/connect/direct-charges).

Studio admin ima Poveži Stripe, status onboardinga, potrebne radnje, sposobnost primanja naplata i isplate. Aplikacija pohranjuje provider account ID i potrebne šifrirane podatke. Hosted checkout je početna preporuka; platforma ne pohranjuje kartične podatke.

### 16.2. Transakcije i webhookovi

Odvojeni entiteti: order, order item, payment attempt, payment, refund, fiscal document i payout. Potpis webhooka provjeriti prije obrade; connected account mapirati na studio na serveru. Deduplikacija uključuje provider, account i event ID. Metadata ne može sama promijeniti tenant.

Događaji mogu doći više puta ili izvan redoslijeda. Poslovni prijelazi su idempotentni, terminalna stanja ne vraćaju se nekritično unatrag; po potrebi dohvatiti aktualni provider status. Privremene greške retry s backoffom, trajne u operativni pregled. API pozivi imaju stabilan idempotency key po logičkoj operaciji.

Periodično usklađivanje otkriva propuštene webhookove i nepoznate statuse. Uspješan povratak preglednika nije dokaz plaćanja. Pri sporu/chargebacku evidentirati slučaj i definirati posljedice za buduća prava bez brisanja povijesti.

### 16.3. Povrati i alternativne uplate

Refund ima iznos, razlog, stavke, autora i provider status; djelomični povrat ne smije prijeći preostali povrativi iznos. Uskladiti pravo, booking, kredit i fiskalni ispravak kao odvojene korake. Stripe refund ne zamjenjuje storno/ispravak dokumenta.

Ručno evidentirane gotovinske ili bankovne uplate imaju referencu, način, datum, iznos i ovlaštenog autora. Payout sa Stripea nije nova uplata polaznika i ne stvara novi račun za trening. Način plaćanja na računu mapira se prema stvarnom načinu plaćanja, uz potvrdu providera/računovođe.

## 17. Računi, hrvatska fiskalizacija i eRačuni

### 17.1. Granice odgovornosti

Studio je izdavatelj računa za treninge; platforma je izdavatelj računa za svoju SaaS uslugu. Odvojiti B2C račune polaznicima, eventualne B2B prodaje studija i B2B račune platforme studijima. Primjenjivost eRačuna ovisi o subjektu i transakciji; ne pretpostavljati jednake obveze za sve studije. Referentno: [Porezna uprava — eRačun](https://porezna.gov.hr/fiskalizacija/bezgotovinski-racuni/eracun) i [Zakon o fiskalizaciji](https://narodne-novine.nn.hr/clanci/sluzbeni/2025_06_89_1233.html).

Fina navodi da certifikat za fiskalizaciju mora biti izdan na obveznika fiskalizacije. Zajednički login kod providera ne zamjenjuje identitet izdavatelja. [Fina — certifikat i obveznik](https://www.fina.hr/poslovni-digitalni-certifikati/poslovni-certifikati-za-fiskalizaciju/najcesca-pitanja-i-odgovori-o-fiskalizaciji)

Ovaj odjeljak propisuje razvojne zahtjeve. Porezni tretman paketa, predujmova, članarina, bonova, storna, rokove i obvezne sadržaje potvrditi s računovođom i providerom prije produkcije; ne nagađati stope ili rokove u kodu.

### 17.2. Model računa kod providera

**Preporuka za početak:** zaseban račun ili zasebna ugovorena organizacija svakog studija; studio plaća provider prema odabranoj ponudi. Studio unosi svoj B2C certifikat kod providera. Platforma čuva šifrirani API pristup/organizacijski ID, ne certifikat i njegovu lozinku.

E-racuni.hr je kandidat, ne odabrani partner. Njihove upute opisuju unos vlastitog B2C certifikata u postavke tvrtke te razlikuju taj tok od ovlaštenja za B2B/B2G eFiskalizaciju. [E-racuni.hr — certifikati](https://www.e-racuni.hr/hrracuni/fiskalizacija-2-0-svi-odgovori-na-jednom-mjestu/)

**Kasnija alternativa:** partnerski platformski račun s više odvojenih organizacija/OIB-a. Provider mora izričito podržavati taj model, pravo korištenja, ugovaranje, API izolaciju, cijenu i odlazak klijenta. Za e-racuni.hr to nije potvrđeno u razgovoru. Jedna obična pretplata ne pretpostavlja pravo posluživanja svih klijenata. Svaki B2C studio i dalje ima odgovarajući vlastiti certifikat.

### 17.3. Obvezne provjere providera

- Hrvatska B2C fiskalizacija i potrebni B2B eRačuni; ne samo PDF fakturiranje.
- Sandbox/testni certifikati, dokumentiran API, identifikatori zahtjeva i provjera statusa.
- Jedinstvena vanjska referenca ili druga zaštita od duplikata.
- Podrška za predujmove/konačne račune, ispravke i djelomične povrate gdje su potrebni.
- Tko dodjeljuje broj računa, upravlja serijama i pohranjuje izvornik.
- Više odvojenih organizacija, pristup računovođi, eksport/arhiva i prestanak ugovora.
- Cijena po OIB-u, dokumentu, API pristupu i eventualnom partnerskom računu.
- Status certifikata/rok, webhook ili polling, dostupnost podrške i rate limit.
- Opseg delegirane fiskalizacije i ponašanje pri prekidu, uključujući postupak usklađenja.

### 17.4. Onboarding studija

1. Poslovni naziv, OIB, adresa, porezni status i odgovorna osoba.
2. Otvoren račun/organizacija kod providera i odgovarajući API paket.
3. B2C certifikat unesen kod providera; zabilježen status i istek ako provider to izlaže.
4. Poslovni prostori, oznake uređaja/operatora, numeriranje i porezni profili usklađeni s računovođom. Ako postoji drugi POS, numeriranje i tokovi moraju biti koordinirani.
5. Povezan API pristup i testiran bez izlaganja tajni.
6. Definirano koji prodajni događaj stvara koji dokument, uključujući avansnu prodaju paketa.
7. Sandbox test izdavanja, statusa, dostave i ispravka.
8. Produkcijska aktivacija tek nakon završene provjere.

### 17.5. Izdavanje dokumenta

Nakon potvrđenog poslovnog događaja servis `FiscalDocumentService` kreira zahtjev sa snapshotom izdavatelja, stavaka, iznosa, poreznog profila, načina plaćanja, datuma, potrebnih kupčevih podataka i jedinstvene vanjske reference. Trenutak i vrsta dokumenta slijede prethodno potvrđeno pravilo; nije univerzalno „svaka uplata = isti tip računa”.

Provider izdaje/potpisuje/šalje dokument prema svom ugovorenom opsegu. Platforma sprema ID, broj, status, JIR/ZKI kad su primjenjivi i dostupni, reference originala/ispravka i autoriziran pristup dokumentu. Odrediti jednog vlasnika numeriranja; preporuka je provider gdje to podržava. Ne imati dvije neusklađene numeracije u platformi i provideru.

Poslovni događaj i outbox zapis spremiti transakcijski kako pad između naplate i slanja posla ne bi izgubio račun. Provider poziv ide iz workera, a status dolazi webhookom ili pollingom. Dokument dostavlja jedan dogovoreni kanal; spriječiti da i provider i platforma nepotrebno pošalju istu potvrdu.

### 17.6. Statusi i greške

Odvojeno voditi status plaćanja, rezervacije, dokumenta i fiskalizacije. Primjer statusa zahtjeva: queued, submitting, unknown, succeeded, failed_requires_action. Dokument može biti izdan dok fiskalizacija još nije potvrđena; ne svoditi sve na jedno polje paid.

Pri timeoutu najprije tražiti postojeći dokument po referenci; ne stvarati novi naslijepo. Retry koristi istu referencu, backoff i ograničen broj pokušaja. Greška ne smije ponovno naplatiti polaznika ili automatski poništiti uspješnu rezervaciju. Potrebni su alert, odgovorna osoba i dogovoren postupak u zakonskim rokovima; ne ostavljati problem neograničeno u queueu.

Opozvan/istekao certifikat, krivi porezni profil ili nevažeći poslovni prostor nisu beskonačno ponovljive greške. Studio/platform admin vidi objašnjenje i radnju. Odluka o privremenoj obustavi novih prodaja mora biti konfigurirana prema vrsti incidenta i dogovorenom fiskalnom postupku.

### 17.7. Povrati, storna i arhiva

Originalni izdani dokument ne uređivati kao obični CMS zapis. Povrat/storno/ispravak povezuje se s originalom i poslovnim razlogom. Uskladiti djelomični povrat sa stavkama i već izvršenim povratima. Novčani i fiskalni korak mogu različito uspjeti; dashboard mora pokazati preostalu radnju.

Čuvati reference, audit i dokumente prema definiranoj politici; preuzimanja zahtijevaju tenant autorizaciju. Računovođa dobiva izvoz za period. Ne poistovjećivati Stripe receipt s hrvatskim fiskalnim računom niti PDF s razmjenom strukturiranog eRačuna.

## 18. Email, domene i automatizacije

### 18.1. Infrastruktura

Potvrđeni email servis je Amazon SES. Početni model je platformski AWS račun s verificiranim domenama studija. Prije produkcije odabrati regiju i plan, osigurati produkcijski pristup i slanje unutar odobrenih kvota. Obrađivati delivery/bounce/complaint događaje i povezati ih s tenantom i porukom; zaustaviti slanje na nedopuštene adrese. Resend i Postmark ostaju samo prethodno razmatrane alternative.

Primjer: From `Yoga Studio Lotus <rezervacije@obavijesti.studiolotus.hr>`, Reply-To `info@studiolotus.hr`. Slanje s poddomene ne zamjenjuje postojeći inbox studija. Postojeći Gmail/Microsoft/hosting mailbox ostaje kod svog providera. DNS onboarding dodaje potrebne DKIM/SPF ili custom Return-Path zapise i usklađuje DMARC; ne briše postojeći SPF/MX niti stvara dva konfliktna SPF zapisa na istom hostu.

Bez verifikacije koristiti platformsku verificiranu domenu s nazivom studija i njegovim Reply-To. Tek nakon verifikacije dozvoliti vlastiti From. Poddomene pomažu razdvajanju namjene, ali ne jamče potpunu reputacijsku izolaciju.

### 18.2. Poruke

| Kategorija | Događaji |
|---|---|
| Identitet | Pozivnica, verifikacija, reset i sigurnosna obavijest. |
| Rezervacije | Potvrda, podsjetnik, premještanje, zamjena instruktora, otkazivanje i lista čekanja. |
| Financije | Kupnja, dostupnost računa, refund, neuspjela naplata i obnova. |
| Onboarding polaznika | Upute za prvi dolazak, ulaz, parking, što ponijeti i kada doći. |
| Prava | Preostali dolasci, istek paketa/članarine, promjena obnove. |
| Online | Meet pristup i relevantne promjene online termina. |
| Studio | Tjedni poslovni sažetak i operativne greške. |
| Marketing | Newsletter, akcije, reaktivacija, preporuke i povremeni feedback. |

Transakcijske poruke i marketing odvojeni su po namjeni, pravima i limitima. Marketinške preference i odjava ne blokiraju nužne obavijesti o kupljenoj usluzi. Provjeriti primjenjivu osnovu za marketing; ne dodavati svaku kupljenu adresu automatski na newsletter.

### 18.3. Pouzdano slanje

Tenant, primatelj, locale, sender i verzija predloška određeni su na serveru. Poslovi se pokreću nakon commita. Prije slanja podsjetnika ponovno provjeriti booking i vrijeme termina. Dedup ključ poslovni događaj + primatelj + vrsta poruke. Retry mora obraditi neizvjestan provider odgovor bez nepotrebnih duplikata.

Bilježiti queued/submitted/delivered/bounced/complained/suppressed/failed i provider message ID. Delivered nije dokaz čitanja. Potpisane webhookove mapirati na tenant; hard bounce/spam complaint sprječava daljnje nedopušteno slanje. Open rate je nepouzdan signal i nije glavni KPI.

### 18.4. Kontrole, cijena i admin

Studio vidi status domene, sender/Reply-To, preview predložaka, potrošnju i nedostavljene poruke. Platform admin vidi globalne i tenant probleme, limite i reputaciju. Marketinšku kampanju ograničiti po studiju da ne ugrozi cijeli račun. Streamovi/subaccounti koriste se gdje provider podržava, bez pretpostavke potpune izolacije.

U pretplatu uključiti razuman opseg servisnih poruka, marketing zasebno limitirati. Alert prije provider limita i rezervni kapacitet za kritične poruke; marketinška kvota ne smije zaustaviti otkazivanje treninga ili reset. Trošak mjeriti po primateljima i broju domena, ne samo broju API poziva.

Ilustrativni volumen: 100 polaznika × 8 rezervacija × potvrda i podsjetnik = 1.600 poruka, okvirno 2.000 s ostalima. Ovo nije obećani limit plana. Aktualne cijene provjeriti prije ugovaranja na [SES](https://aws.amazon.com/ses/pricing/), [Resend](https://resend.com/pricing) i [Postmark](https://postmarkapp.com/pricing) cjenicima; ne hardkodirati ranije spomenute cijene.

### 18.5. Automatizacije — B/C

Zahvala nakon prvog dolaska, istek paketa, ažuriranje metode plaćanja, lista čekanja, tjedni sažetak i povrat neaktivnih polaznika. Studio može pregledati tekst, uključiti pravilo i vidjeti log. Frequency cap, quiet hours za opcionalne kanale i međusobna deduplikacija sprječavaju previše poruka. Poslovni timezone vrijedi za raspored. Marketing kampanja ima draft, audience preview/count, test poruku, schedule i cancel; ne obećavati povlačenje već poslanih poruka.

## 19. Google Meet i osobni kalendari

### 19.1. Početno

Ručni Meet link na online terminu, dostupan samo ovlaštenim rezerviranim polaznicima u definiranom prozoru. Dodavanje termina u Google/Apple/Outlook kalendar preko odgovarajućih linkova/ICS-a. Stabilan UID i verzija događaja pomažu ažuriranju, ali jednokratni ICS import nije zajamčena automatska sinkronizacija. Promjene uvijek vidljive u portalu i šalju se obavijesti. Privatni feed, ako se uvede, mora imati opoziv tokena i ne smije izložiti druge polaznike.

### 19.2. Automatizacija — A

Google OAuth povezivanje studija/instruktora, šifriran refresh token, minimalne ovlasti i reconnect. Organizator i ciljni kalendar eksplicitni. Provjeriti produkcijske OAuth zahtjeve i mogućnosti Google računa.

Calendar događaj kreira konferenciju pomoću `conferenceData.createRequest` i `conferenceDataVersion=1`; izrada može biti asinkrona. Spremiti event ID, organizatora, stabilnu referencu pokušaja, status i URL. Jedan termin ima jedan Meet za sve njegove rezervacije, novi termin novu konferenciju. Retry istog logičkog zahtjeva ne stvara duplikate. [Google Calendar reference](https://developers.google.com/workspace/calendar/api/v3/reference/events)

Izmjena/otkazivanje sinkronizira isti događaj. Prije svakog joba provjeriti verziju i status termina kako zastarjeli job ne bi ponovno kreirao otkazani sastanak. Ne otkrivati popis emailova kroz grupne pozivnice. Link nije javni CMS podatak. Meet ne provjerava automatski pravo kupnje; organizatorove postavke pristupa i kontrola ostaju potrebne.

Greške: pending obrada, istek autorizacije, nedostupni API ili nedopuštena konferencija. Ograničeni retry, alert i ručni fallback. Trajanje, broj sudionika i snimanje ovise o Google planu; automatsko snimanje nije dio ove funkcije.

## 20. Videoteka i online programi

Potvrđeni video provider je Bunny Stream (bunny.net). Predloženi model je jedan platformski račun i zasebna video biblioteka po studiju. Laravel provjerava tenant i ovlasti te izdaje privremene potpisane podatke za izravni TUS upload iz preglednika; API ključevi ostaju na backendu. Video ne prolazi kroz PHP i platforma ne radi vlastiti transcoding. Status obrade uskladiti preko provider događaja/API-ja; objava tek nakon spremnosti. Za reprodukciju provjeriti kupljena prava i izdati vremenski ograničen pristup, uz zaštitu playera i izravnih CDN poveznica. Standardna europska isporuka i rezolucije do 1080p početni su prijedlog za test, ne potvrđeni komercijalni plan. Volume mrežu, dodatnu replikaciju, Premium Encoding i Enterprise DRM ne uključivati automatski.

Video: tenant, provider ID, naslov/prijevodi, opis, instruktor, trajanje, razina, kategorije, thumbnail, titlovi kad su dostupni, kolekcija i pravilo pristupa. Status draft/uploading/processing/ready/published/failed/archived. Arhiviranje i brisanje ne smiju nenajavljeno prekinuti već prodani pristup; definirati zamjenu/povrat i pravila proizvoda.

Podržati prodaju pristupa biblioteci, kolekciji/programu ili pojedinačnom sadržaju kroz faze; u prvoj implementaciji odabrati jednu jednostavnu varijantu. Entitlement ima izvor kupnje, početak/kraj i opseg. Članarina može uključivati video pravo bez zasebne naplate.

Backend provjerava tenant i pravo prije izdavanja kratkotrajnog playback tokena. Javni trajni URL ne smije zaobići naplatu. Opoziv prava zaustavlja nove tokene; izdani token može vrijediti do isteka. Domain restriction je dopunska zaštita. Ne obećavati zaštitu od snimanja ekrana. [Primjer potpisanog pristupa](https://developers.cloudflare.com/stream/viewing-videos/securing-your-stream/)

Portal prikazuje kategorije, pretragu, trajanje/razinu i kasnije nastavak gledanja/programski napredak. Napredak ne koristiti kao dokaz fizičke aktivnosti ili zdravstvenog rezultata.

Analitika: započete/dovršene reprodukcije i najgledaniji sadržaji prema mogućnostima providera; definicija dovršenosti eksplicitna. Trajanje i gledane minute služe produktnoj analitici, a za Bunny trošak pratiti stvarne GB pohrane svih generiranih datoteka, GB isporuke, mrežu/regiju i eventualne dodatke po biblioteci/studiju. Minute ne pretvarati u trošak bez mjerenog bitratea. Plan ima limite, upozorenja i dogovoreno prekoračenje; bez neograničenog obećanja. Provider outage prikazuje jasnu grešku i retry, ne briše prava.

## 21. Prodaja, angažman i napredne funkcionalnosti

| Funkcija | Zahtjevi i rubni slučajevi |
|---|---|
| Probni paket | Broj dolazaka, rok, dopuštene usluge i provjera nove osobe; ne oslanjati se samo na novi email kao potpuno sprječavanje zlouporabe. |
| Kuponi | Fiksni/postotni popust, proizvodi, početak/kraj, ukupni i korisnički limit, kombiniranje; atomsko korištenje i snapshot. |
| Poklon bonovi | Nepredvidiv kod, vrijednost ili određena usluga, primatelj, saldo/iskorištenja i istek prema potvrđenim pravilima. Porezni tretman potvrditi prije lansiranja. |
| Preporuka prijatelja | Nagrada tek nakon definirane stvarne kupnje/dolaska; zaštita od samopreporuke i povrata kvalificirajuće kupnje. |
| Radionice i ciklusi | Poseban proizvod, kapacitet i upis u sve termine; ne poistovjećivati s običnim paketom slobodnih dolazaka. |
| Retreata/višednevno | Datumi, kapacitet, uključene usluge, polog/obroci, dospijeća i pravila otkazivanja; bez gradnje općeg hotelskog sustava. |
| Interes za novi termin | Prijava interesa za dan/sat/vrstu, bez naplate i bez jamstva mjesta; studio vidi agregat i može poslati poziv prema preferencijama. |
| Feedback | Povremeni kratki upit nakon stvarnog dolaska, limit učestalosti; privatno mišljenje nije automatska javna recenzija. |
| Izvori polaznika | UTM/source uz prvu kupnju i opcionalni odgovor „Kako ste saznali”; jasno pravilo atribucije, ne tvrditi potpunu točnost. |
| Oprema/dodaci | Nadoplata uz rezervaciju, količina, inventar i povrat; ograničena oprema zahtijeva atomsku rezervaciju. |
| Honorari instruktora | Izvještaj održanih termina i dogovorenih stopa; korekcije auditirane. Nije obračun plaća ni poreza. |
| Reaktivacija | Definiran prag neaktivnosti i publika; ne slati osobama bez odgovarajuće marketinške osnove ili odjavljenima. |
| PWA | Instalacija i mobilni pristup; cache ne smije izložiti privatne podatke drugog korisnika. Offline kupnje nisu početni cilj. |
| SMS/push | Vanjski provider, preference, trošak/kvota, statusi i odjava; kritični kanal i fallback unaprijed definirani. |
| AI pomoć | Prijedlog teksta/prijevoda/FAQ-a, pregled prije objave; ne mijenja cijene, uvjete ili raspored autonomno. Bez medicinskih tvrdnji i nepotrebnih osobnih podataka. |

Raspravljene mogućnosti izvan planiranog opsega: javni marketplace svih studija, izvorne iOS/Android aplikacije, potpuno slobodan page builder, vlastiti sustav videokonferencija i transcodinga, medicinske preporuke i puni sustav plaća. Ostaju zabilježene, ali Codex ih ne smije samoinicijativno implementirati. PWA nije isto što i izvorna mobilna aplikacija.

## 22. Domene, Nginx i HTTPS

### 22.1. Početna odluka

Ručno postavljanje domene i Nginx konfiguracije prihvatljivo je jer individualni dizajn ionako prolazi kroz vlasnika. Nema potrebe graditi control panel samo radi toga. Nginx odabire TLS certifikat prema SNI-u; Laravel potom određuje studio iz hosta. Ista IP adresa može služiti više domena. [Nginx HTTPS dokumentacija](https://nginx.org/en/docs/http/configuring_https_servers.html)

```text
/etc/nginx/
  sites-available/
    studio-lotus.conf
    studio-balance.conf
  snippets/
    yoga-platform-laravel.conf
```

Datoteka studija ima domene, certifikat i uključivanje zajedničkog Laravel routinga. Svi koriste isti `public` direktorij i aplikaciju. Zadani server odbija nepoznate hostove; HTTPS nepoznate hostove odbiti tijekom TLS-a gdje podržano. Ne prosljeđivati ih prvom studiju.

### 22.2. Postupak aktivacije

1. Kreirati tenant i pending domenu, normalizirati IDN/hostname i provjeriti jedinstvenost.
2. Potvrditi kontrolu domene putem dogovorenog DNS dokaza ili ručno dokumentiranog postupka; samo uneseni naziv nije dokaz.
3. Provjeriti A/AAAA/CNAME usmjeravanje i glavnu/www varijantu.
4. Privremena HTTP/ACME konfiguracija, izdavanje certifikata, zatim HTTPS konfiguracija.
5. `nginx -t`; tek nakon uspjeha reload. Ne aktivirati config koji referencira nepostojeći certifikat.
6. Vanjska provjera HTTPS-a, host-to-tenant mapiranja, redirecta i javnog sitea.
7. Aktivirati domenu i zabilježiti operatera/status. Alias preusmjerava samo na glavnu domenu istog studija.

Jedan certifikat po studiju može pokrivati glavnu domenu i www. Certifikat mora pokrivati i HTTPS alias s kojeg se radi redirect. Wildcard platforme ne pokriva tuđe domene. ACME HTTP-01 traži odgovarajući pristup na portu 80; DNS-01 je alternativa i potreban za wildcard. [Let's Encrypt provjere](https://letsencrypt.org/docs/challenge-types/)

Obnova automatska, uz reload hook i alert. Testiranje koristi staging CA; ponavljanje deploya ne izdaje sve certifikate iznova. Poštovati [limite izdavanja](https://letsencrypt.org/docs/rate-limits/). DNS promjena, CAA zabrana, pogrešan AAAA ili uklonjena ACME delegacija trebaju vidljivu grešku. Ručno postavljanje ne znači ručno pamćenje obnove.

### 22.3. Buduće alternative

Mala skripta iz provjerenog predloška može zamijeniti kopiranje konfiguracije. Ako admin pokreće postavljanje, odvojeni ograničeni proces obavlja privilegirane radnje; Laravel web proces nema opće root ovlasti. Validirati domenu, koristiti sigurne argumente, zaključati paralelne izmjene, sačuvati prethodnu konfiguraciju i testirati prije reloada.

Forge može upravljati domenama/certifikatima i API automatizacijom; opcionalan alat, ne dio početnog stacka. Ako se uvede, ne dopustiti da istu datoteku neovisno uređuju i panel i skripta. [Forge domene](https://laravel.com/forge/docs/sites/domains)

Caddy je alternativni web server s On-Demand TLS-om i internim autorizacijskim endpointom za prethodno odobrene domene. To je alternativna arhitektura, ne dodatni paralelni sloj koji se mora instalirati uz Nginx. [Caddy](https://caddyserver.com/on-demand-tls)

## 23. Hosting, deployment, backup i monitoring

### 23.1. Početna infrastruktura

Jedna Akamai instanca: Nginx, PHP-FPM, Laravel, baza, queue workeri, scheduler, Redis po potrebi i Node SSR ako je odabran. Vanjski email/fiskalni/Stripe/Google/video servisi i object storage. Jedna instanca je prihvatljiv početni kompromis, ali njezin kvar pogađa sve studije.

Naknadno odabrani smjer je zasebna manja Akamai instanca za staging. Isti repozitorij i postupak postavljanja, ali odvojene instalacije Laravela, PHP procesi, workeri, scheduler i Node SSR. Staging i produkcija imaju odvojene baze, APP_KEY, tajne, test/live provider račune i medije. Staging mailovi idu samo dopuštenim testnim primateljima. Demo podaci ne sadrže stvarne osobne podatke. Produkcijski backup ne kopirati nezaštićeno u razvoj. Staging služi za provjeru istog commita/artefakta koji se promovira u produkciju; njegova baza ne prenosi se u produkciju.

### 23.2. Deployment

CI build, testovi, zaključani paketi, provjera konfiguracije, sigurne migracije, statički asseti, cache konfiguracije, restart workera/SSR-a i health check. DB promjene izvoditi kompatibilno s postupnim deployem gdje je moguće. Stari asseti ostaju dovoljno dugo za otvorene sesije. Uspješan rollback koda ne vraća automatski podatke; destruktivne migracije imaju poseban plan.

Deploy s novim individualnim frontendom ne mijenja domene drugih studija. Promjena zajedničkih komponenti zahtijeva reprezentativni vizualni pregled postojećih dizajna. Ne uvoditi zasebne grane/instalacije po klijentu.

### 23.3. Backup i oporavak

Automatski šifrirani backup baze i potrebnih medija izvan instance, definirana retencija i odvojene ovlasti. Aktualni prijedlog iz 1.4/21 je RPO do 15 minuta i RTO do 4 sata, uz offsite binlog/PITR i mjesečni restore test. Noćni backup sam ne ispunjava cilj. Prije produkcije potvrditi trošak i operativnu pokrivenost; ovo nije ugovorni SLA.

Oporavak mora uskladiti bazu s vanjskim plaćanjima i računima nakon točke backupa; ne stvarati ponovno naplate ili fiskalne račune za već postojeće provider operacije. Runbook uključuje DB, aplikacijske tajne, medije, DNS/TLS i provjeru poslovnih tokova.

### 23.4. Monitoring i skaliranje

Dostupnost javnog sitea/checkouta, HTTP greške, CPU/RAM/disk, DB latencija, queue age/failed jobs, webhook backlog, neusklađene naplate, fiskalne greške, TLS i OAuth status, email bounce/complaint i video potrošnja. Alarm ima odgovornu osobu i radnju; logovi nemaju tokene ili nepotrebne osobne podatke.

Prvo optimizirati upite/indekse i resurse, zatim odvojiti bazu/workere i po potrebi dodati aplikacijske instance. Pri više instanci zajedničke sesije/cache, mediji i koordinator schedulera sprječavaju duple zadatke. Broj studija po serveru ne obećavati bez mjerenja. Financijske i pravne provider operacije prioritetnije su od masovnog marketinga; odvojiti queueove.

### 23.5. GitHub Actions → Akamai instanca

GitHub Actions može automatizirati deployment na instancu preko SSH-a; poseban Akamai deployment servis nije potreban. Jedna pipeline isporučuje zajedničku aplikaciju i sve registrirane frontendove. Klijentski sadržaj, računi i rezervacije u bazi ne mijenjaju se deployem osim kroz namjerne migracije. Dodavanje novog template koda zahtijeva deploy; promjena CMS sadržaja ne.

Predloženi tok: pull request pokreće testove i build bez produkcijskih tajni; push/merge u zaštićeni `main` pokreće uspješno testirani build i deployment. Mogući su staging i ručni `workflow_dispatch`; approval je opcionalna postavka vlasnika, ne obvezni dodatni korak. GitHub `production` environment čuva deployment tajne i ograničenje grana. `concurrency` i server-side lock dopuštaju jednu aktivaciju odjednom; `cancel-in-progress: false` ne prekida već započetu produkcijsku migraciju. Provjeriti da se stariji commit ne aktivira nakon novijeg.

Na serveru predložiti `/var/www/yoga-platform/releases/<release-id>`, `/var/www/yoga-platform/shared` i symlink `/var/www/yoga-platform/current`; Nginx root je `current/public`. Shared sadrži produkcijski `.env` i trajne upload/runtime podatke prema konfiguraciji. `bootstrap/cache` i drugi generirani cache koji ovisi o kodu ostaju po releaseu. Nema `.env`, DB dumpa ili privatnih ključeva u artefaktu.

Koraci: testiranje na produkcijski usklađenoj verziji PHP/Node/baze; build iz lockfileova (frontend i SSR ako je uključen); izrada artefakta s commit SHA/checksumom i produkcijskim ovisnostima bez dev paketa; SSH prijenos u novi release; povezivanje shared podataka i lokalna priprema config/route/view cachea; kompatibilne migracije `migrate --force`; atomska zamjena current symlinka; graceful obnova workera i SSR procesa te usklađenje PHP opcachea prema konfiguraciji; vanjski health check poznate domene, DB i reprezentativni tenant smoke test; zapis ishoda.

Composer ovisnosti mogu se pripremiti u CI-u ako je platforma usklađena; inače se instaliraju iz lockfilea u novom releaseu na serveru. Nema `composer update` ili `npm update` tijekom deploya. Ne čistiti produkcijski aplikacijski cache nekritičnim globalnim flushom jer može sadržavati lockove/sesije. Zadržati stare hashirane frontend assete dovoljno dugo da otvorene stranice mogu dohvatiti svoje lazy chunkove; samo čuvanje starog release direktorija nije dovoljno ako asset URL više nije posluživ.

Rollback prije aktivacije ostavlja current netaknut. Nakon aktivacije povrat symlinka, workera i SSR-a moguć je samo ako su schema i side effectovi kompatibilni. Ne izvršavati automatski destruktivni `migrate:rollback` niti vraćati backup preko novih naplata. Koristiti expand/contract migracije; za nekompatibilne promjene planirati maintenance postupak. Atomski switch sam ne jamči potpun zero-downtime na jednoj instanci.

SSH koristi zaseban deploy korisnik, ograničene ovlasti i provjeren host key iz pouzdanog izvora; ne isključivati host provjeru. Ključ u GitHub environment secretu, privatne runtime tajne na serveru. Third-party Actions pinati na provjeren commit i dodijeliti minimalan `GITHUB_TOKEN` permission. PR iz nepouzdanog izvora nema deploy tajne. GitHub-hosted runner mora mrežno dosegnuti SSH; kod stroge allowliste koristiti statični egress/private pristup ili izolirani runner, ne širiti pravila nekritično. Deploy ne upravlja Nginx certifikatima; to je odvojeni operativni korak.

Reference: [GitHub deploymenti](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/control-deployments), [sigurnost Actionsa](https://docs.github.com/en/actions/reference/security/secure-use) i [Laravel deployment](https://laravel.com/framework/docs/12.x/deployment). Verzije i točne naredbe prilagoditi stvarno odabranom stacku; dokument ne zaključava aplikaciju na Laravel 12.

## 24. Onboarding, migracija i prestanak korištenja

### 24.1. Postavljanje novog studija

| Korak | Nositelj | Rezultat |
|---|---|---|
| Ugovor i opseg | Platform owner + studio | Cijena, radovi, podrška, limiti i prava. |
| Poslovni podaci | Studio | Subjekt/OIB, adresa, kontakti, zona, jezik, porezni profil za potvrdu. |
| Dizajn | Platform owner | Individualni frontend, potrebne fotografije i dopuštena CMS shema. |
| Sadržaj | Studio + platform owner | Tekstovi, instruktori, pravila, kontakt, prijevodi. |
| Tenant | Platform admin | Račun studija, owner pozivnica, dizajn i feature flagovi. |
| Domena/HTTPS | Studio + operater | Verificirana domena, Nginx, certifikat i redirect. |
| Ponuda i raspored | Studio uz pomoć | Usluge, paketi, cijene, dostupnost i termini. |
| Stripe | Studio | Povezani račun i mogućnost prihvata naplate. |
| Fiskalizacija | Studio/računovođa + integrator | Organizacija, certifikat, API i potvrđena pravila dokumenata. |
| Email | Studio/DNS operater | Sender domena, Reply-To i test dostave. |
| Google/video | Studio prema planu | Autorizacija i aktivni moduli. |
| Uvoz | Platform owner | Validirani polaznici i početna prava. |
| Provjera | Obje strane | Test rezervacije, otkazivanja, plaćanja, računa i mobilnog prikaza. |
| Objava | Ovlašteni operater | Aktivacija uz zapis odobrene verzije i završene provjere. |

Onboarding statusi nisu dokaz da vanjska integracija radi; prije aktivacije izvršiti stvarne testne provjere u predviđenom okruženju. Za produkcijsku provjeru stvarnim novcem koristiti eksplicitno dogovoren testni postupak.

### 24.2. Migracija

CSV uvoz prvo prikazuje pregled: mapiranje kolona, obvezna polja, duplikati emaila unutar studija, neispravni datumi i sažetak promjena. Suhi pregled ne mijenja bazu. Import batch ima ID i može se sigurno ponoviti bez dupliciranja.

Početni krediti knjiže se kao migracijska dodjela s izvorom i saldom, ne kao nova plaćena kupnja. Stara plaćanja/računi su označena povijest; ne naplaćivati niti fiskalizirati ponovno samo zbog uvoza. Marketinšku privolu ne pretpostavljati; uvesti samo raspoloživ dokaz i preference. Slanje dobrodošlice zasebna je odluka, ne nuspojava importa.

### 24.3. Prestanak korištenja i suspenzija

Odvojiti kašnjenje SaaS pretplate, privremenu suspenziju i konačni odlazak. Grace period, obavijesti i dopuštene radnje definirati ugovorom. Već plaćene rezervacije i računi ne nestaju zbog jednog neuspjelog SaaS terećenja. Definirati ograničenje novih prodaja i nastavak nužnih obavijesti.

Izvoz polaznika, rezervacija, salda, kupnji i dokumenata u dostupnom formatu; predaja informacija o domeni i povezanim servisima. Studio zadržava vlastiti Stripe račun. Fiskalni podaci i dokumenti ostaju dostupni prema ugovorenom provider modelu i obvezama čuvanja. Odspojiti OAuth/API pristupe i opozvati javne tokene kad je prikladno.

Domenu pri prestanku prvo deaktivirati prema dogovoru, ukloniti routing i DNS upute te spriječiti preuzimanje starog mapiranja drugim tenantom bez nove verifikacije. Zadržavanje podataka, anonimizaranje i brisanje imaju definiran raspored; financijske dokumente ne brisati proizvoljno.

## 25. Konceptualni model podataka

Nazivi su prijedlog za razradu migracija. Poslovni entiteti nose `tenant_id` osim izričito globalnih. Koristiti zasebni naziv `class_sessions` za termine, ne miješati ga s autentikacijskim sesijama. Soft delete nije univerzalna zamjena za status i arhivu.

| Skup | Glavni entiteti | Bitne veze/polja |
|---|---|---|
| Platforma | tenants, tenant_domains, tenant_settings, saas_plans, tenant_saas_subscriptions | Status, subjekt, zona, valuta, design key, verified host; plan globalan. |
| Identitet | platform_users, users, role_assignments, invitations, customer_profiles, instructor_profiles | Tenant email unique, ovlasti, preference; platform identitet odvojen. |
| Dizajn/CMS | site_design_registry, pages, page_translations, page_revisions, media_assets | Registar može biti u kodu; schema version, published revision, vlasništvo medija. |
| Raspored | locations, rooms, class_types, schedule_rules, class_sessions, availability_rules, availability_exceptions | Lokalna pravila i UTC trenuci, snapshot kapaciteta, resursi. |
| Rezervacije | booking_holds, bookings, waitlist_entries, waitlist_offers, standing_enrollments, course_enrollments | Statusi, expires_at, reason, snapshot pravila i izvor prava. |
| Dolasci | attendance_records, checkin_tokens | Booking, vrijeme, metoda, operater; token hash i opoziv. |
| Ponuda | products, prices, product_entitlement_rules | Valuta/minor units, verzija, aktivnost/arhiva. |
| Prodaja | orders, order_items, payment_attempts, payments, refunds, payout_references | Provider/account IDs, snapshot, statusi i idempotency ključevi. |
| Prava | credit_accounts, credit_ledger_entries, customer_subscriptions, entitlements, membership_pauses | Kompenzacijski ledger, valid_from/to, izvor i opseg prava. |
| Dokumenti | fiscal_documents, fiscal_document_attempts, document_relations | Izdavatelj, vanjska referenca, broj, status, JIR/ZKI gdje primjenjivo, izvornik/ispravak. |
| Integracije | integration_accounts, webhook_events, outbox_events, reconciliation_runs | Tenant/account mapiranje, šifrirani tokeni, event dedup i obrada. |
| Poruke | sender_domains, notification_deliveries, communication_preferences, automation_rules, campaigns | Locale, verzija predloška, suppression i provider ID. |
| Online | external_calendar_events, videos, video_translations, video_collections, video_progress | Calendar organizator/event, provider video ID, entitlement poveznice. |
| Kasnija prodaja | coupons, coupon_redemptions, gift_cards, gift_card_entries, referrals, interest_requests, feedback_entries | Atomska iskorištenja, publika i kvalifikacije nagrada. |
| Operacije | audit_logs, import_batches, export_jobs, usage_counters, instructor_fee_rules | Autor/razlog, tenant, status i vremenska osnova. |

Ključni constraintovi: unique verificirani hostname; jedinstveni provider događaj unutar računa; jedinstvena poslovna referenca fiskalnog zahtjeva; jedan aktivni booking po osobi/terminu; jedan aktivan check-in po bookingu; jedinstvena kreditna operacija po poslovnom događaju. Točan partial-index/status pristup uskladiti s odabranom bazom.

Financijski snapshotovi čuvaju točnost starih kupnji nakon promjene imena/cijene/profila. Indeksi uključuju tenant i česte filtre: vrijeme termina, korisnik/status, datum naplate, dokument status i job stanje. Agregatne projekcije su obnovljive iz autoritativnih zapisa.

## 26. Backend ugovori i pozadinski poslovi

### 26.1. Granice servisa

Inertia kontroleri ostaju tanki. Domenski servisi provode ovlasti, validaciju i transakcije. Primjeri naredbi: CreateBooking, MoveBooking, CancelBooking, RecordAttendance, PurchaseProduct, ApplyPaymentEvent, RequestRefund, IssueFiscalDocument, PublishSiteRevision i GenerateClassSessions.

Provider adapteri izlažu normalizirane operacije; ne izmišljati stvarne URL-ove/endpointe prije čitanja dokumentacije odabranog providera. Primjer fiskalnog ugovora: provjeri vezu, izdaj dokument, pronađi po vanjskoj referenci, provjeri status, izdaj ispravak, dohvat izvornika. Podržane sposobnosti moraju biti eksplicitne; provider bez idempotency podrške traži dodatnu provjeru statusa i neizvjesnog ishoda.

Web rute grupirati na javne, portal, studio admin i platform admin. Webhook callbackovi imaju poseban auth/signature postupak i ne ovise o hostu klijenta. OAuth callbackovi vežu state uz autoriziranog korisnika, studio i jednokratni pokušaj; browser ne bira proizvoljan tenant nakon povratka.

### 26.2. Pouzdani poslovi

Plaćanje/booking/ledger promjena i outbox događaj spremaju se u istoj DB transakciji. Worker šalje vanjske zahtjeve i zapisuje rezultat. Nema dugih DB lockova dok se čeka Stripe, Google ili fiskalni API. Side effect može uspjeti i kad odgovor kasni; oporavak koristi istu referencu i provjeru statusa.

Queueovi: kritični payment/fiscal, booking-notifications, ostale integracije, exports i marketing. Job nosi tenant, ID entiteta, poslovnu referencu i po potrebi očekivanu verziju. Na početku ponovno provjerava status. Retry ograničen; trajno neuspjeli posao je vidljiv i ima idempotentan način ponovnog izvršavanja.

Scheduler: istek holdova/ponuda, budući termini, podsjetnici, prava koja istječu, naplatno/fiskalno usklađivanje, periodični sažeci, usage obračun i operativni checkovi. Spriječiti paralelno dvostruko izvršavanje kroz lock i jedinstvenost poslovnog događaja.

### 26.3. Stanja i prijelazi

| Entitet | Predložena stanja | Napomena |
|---|---|---|
| Termin | draft, scheduled, cancelled, completed | Završetak vremena sam ne potvrđuje sve dolaske. |
| Hold | active, consumed, expired, released | Vrijeme isteka provjerava se i u zahtjevu, ne samo schedulerom. |
| Booking | pending_payment, confirmed, cancelled, expired | Attendance odvojen. |
| Payment | pending, processing, succeeded, failed, cancelled | Refund je zasebni iznos/status. |
| Refund | requested, processing, succeeded, failed | Ne proglašavati uspjeh samo zbog klika. |
| Attendance | unmarked, present, no_show | Kasno otkazivanje pripada booking pravilu. |
| Fiskalni zahtjev | queued, submitting, unknown, succeeded, failed_requires_action | Izdavanje i fiskalna potvrda mogu imati dodatna odvojena polja. |
| Waitlist ponuda | offered, accepted, expired, cancelled | Prihvat vezan uz kapacitet i pravo. |

Točne enum vrijednosti definirati prije migracija. Svaki prijelaz dokumentira dozvoljenog aktera, preduvjete, transakciju i vanjske posljedice. Klijent ne postavlja proizvoljni status kroz generički update endpoint.

## 27. Sigurnost, privatnost i pristupačnost

Tenant izolacija vrijedi za sve ekrane, API-je, datoteke, logove i background poslove. Zaštite uključuju CSRF, rate limit, sigurne sesije, 2FA administracije, validaciju uploadova, sanitizaciju HTML-a, kontrolu javnih URL-ova i šifriranje tajni. Provider logovi moraju biti redaktirani. Rotacija i opoziv pristupa imaju operativni postupak.

QR tokeni, reset tokeni i privatni calendar/download linkovi ne sadrže osobne podatke i imaju definiran rok/opoziv. Server ne dohvaća proizvoljne korisničke URL-ove radi previewa bez zaštite od SSRF-a. Uploadovi se ne izvršavaju; SVG i drugi aktivni formati zahtijevaju sanitizaciju ili ograničenje. PDF/CSV export provjerava ovlasti pri stvaranju i preuzimanju; neutralizirati opasne spreadsheet formule u korisničkim tekstualnim poljima CSV-a.

Privatnost: minimalni podaci za račun/rezervaciju, odvojene marketinške preference, zabilježena verzija prihvaćenih pravila, izvoz i postupak brisanja/anonimizacije. Definirati uloge studija/platforme, ugovore s podizvršiteljima, retenciju i prekogranične obrade prema odabranim servisima prije produkcije. Ne pohranjivati medicinske anamneze u MVP-u. Brisanje profila ne znači proizvoljno brisanje financijskih dokumenata.

Audit: promjena ovlasti, ručna uplata, refund, kreditna korekcija, fiskalni retry, support pristup, sadržajna objava i promjena domene. Zapisuje tko/kada/tenant/razlog i primjerene before/after podatke bez tajni.

Ciljati WCAG 2.2 AA za ključne tokove kao projektni cilj: tipkovnica, fokus, čitljive greške, kontrast, labele, alt, odgovarajuće ciljne površine i smanjeno kretanje. Ne tvrditi formalnu sukladnost bez provjere. Animacije ne smiju biti jedini način pristupa sadržaju.

## 28. Testiranje i kriteriji prihvata

Kriteriji su obvezujući za modul kad ulazi u izdanje. Automatizirati rizične poslovne tokove i izolaciju; ne pisati testove koji samo ponavljaju trivijalnu implementaciju. Konkurentnost testirati na istoj vrsti baze kao produkcija, ne samo SQLiteom. Vizualne provjere nad individualnim dizajnima ne zamjenjuju financijske testove.

| ID | Scenarij | Očekivani rezultat |
|---|---|---|
| AC01 | Studio A pogađa ID/URL/export/file studija B | Bez pristupa ili curenja podataka. |
| AC02 | Dva uzastopna joba/SSR zahtjeva različitih studija | Nema zaostalog tenant konteksta ili cache curenja. |
| AC03 | Nepoznat host ili krivi proxy host | Odbijen, ne prikazuje prvi studio. |
| AC04 | Reset lozinke/email isti u dva studija | Djeluje samo na odgovarajući račun/domenu. |
| AC05 | Dva zahtjeva za zadnje mjesto | Najviše jedan potvrđen booking; višak naplate ide kroz definiran refund. |
| AC06 | Dva preklapajuća privatna termina | Samo dopušten raspored instruktora/prostora uključujući buffer. |
| AC07 | Dvostruki klik, webhook ili retry | Nema duplog bookinga, kredita, naplate, refund zahtjeva ili računa. |
| AC08 | Success URL bez uspjele naplate | Nema plaćenog prava ni potvrde rezervacije. |
| AC09 | Uplata nakon isteka holda | Atomska provjera mjesta ili jasan povrat bez prepunjavanja. |
| AC10 | Promjena termina na popunjen termin | Stara rezervacija i kredit ostaju. |
| AC11 | Istek paketa/granica otkazivanja/DST | Dosljedno pravilo prema zoni i snapshotu. |
| AC12 | Otkazivanje studija | Obavijesti, povrati prava/novca i pripadajući dokumenti točno jednom. |
| AC13 | Lista čekanja i istodobni prihvat/istek | Jedna valjana ponuda i bez prepunjavanja. |
| AC14 | Isti QR dvaput/pogrešan studio/otkazani booking | Idempotentan dolazak ili odbijanje; nema dodatnog trošenja kredita. |
| AC15 | Nedostatak kamere/veze | Ručni fallback; nema lažne server potvrde. |
| AC16 | Neuspjela obnova i otkazana buduća obnova | Prava slijede pravilo, već plaćeno razdoblje očuvano. |
| AC17 | Krivi Stripe connected account ili potpis | Događaj odbijen, bez promjene tuđih podataka. |
| AC18 | Fiskalni timeout nakon uspjeha kod providera | Dohvat po referenci, bez novog broja/duplog računa. |
| AC19 | Fiskalni provider nedostupan | Naplata se ne ponavlja, greška je vidljiva i eskalirana. |
| AC20 | Djelomični refund i fiskalni ispravak | Točan preostali iznos, povezan izvornik i zaseban status koraka. |
| AC21 | Otkazan booking prije podsjetnika | Podsjetnik se ne šalje. |
| AC22 | Neprovjerena sender domena/bounce/odjava | Dozvoljeni fallback ili suppression; marketing po pravilima. |
| AC23 | Marketinška kvota potrošena | Ne blokira nužne servisne poruke unutar osiguranog kapaciteta. |
| AC24 | Ponovljeni Meet job ili otkazivanje tijekom izrade | Nema duplikata ili ponovne aktivacije otkazanog termina. |
| AC25 | Video bez prava ili drugi tenant | Nema playback tokena; opoziv blokira nove tokene. |
| AC26 | CMS nacrt/neovlašten preview/dugi tekst | Nacrt nije javan; validacija i pregled rade. |
| AC27 | Animacije off/reduced motion/mobilni uređaj | Sadržaj i kupnja dostupni; bez prekrivenih kontrola. |
| AC28 | Inertia navigacija kroz više stranica | Bez dupliciranih listenera i trajnog rasta animacijskih resursa. |
| AC29 | SSR dva studija i jezici | Ispravan HTML/SEO/canonical za svaki tenant i prijevod. |
| AC30 | Financijski dashboard i CSV | Podudaraju se s izvorima uz točne datume/refunde, bez duplih webhook zbrojeva. |
| AC31 | Ponovljeni CSV import | Nema duplih osoba/prava ni novih fiskalizacija stare povijesti. |
| AC32 | Nova domena/certifikat/neispravan config | Test prije reloada, postojeći studiji nastavljaju raditi. |
| AC33 | Restore u izdvojeno okruženje | Podaci čitljivi i dokumentiran postupak reconciliationa. |
| AC34 | Gašenje studija | Kontrolirano ukidanje prodaje/pristupa, izvoz i očuvanje potrebne povijesti. |
| AC35 | Platform admin bira jedan studio, skup ili sve studije | Ispravni podaci, filtri, drill-down i autoriziran izvoz; studio admin ostaje ograničen na svoj studio. |
| AC36 | Zbirni analytics uključuje preklopljene posjetitelje, različite stope i nedostupan izvor | Točan aditivni zbir, jasno imenovan zbroj posjetitelja, pravilno ponderiranje i oznaka nepotpunog obuhvata. |

End-to-end tokovi: novi polaznik → kupnja → booking → potvrda → dolazak; paket → rezervacija → otkazivanje; privatni slot → naplata → račun; studio uređuje sadržaj → preview → objava. Koristiti sandbox providere, a stvarne transakcije samo u dogovorenom produkcijskom testu.

Vizualni QA: najmanje dva različita studija, mobilni/desktop prikaz, dulji hrvatski/engleski tekstovi, nedostajući optional asset, smanjeno kretanje i sporija veza. Performance budget za konkretne stranice definirati nakon prvog reprezentativnog dizajna; mjeriti posebno raspored i checkout.

## 29. Redoslijed implementacije i uputa za Codex

Za prvi funkcionalni dio koristiti [razvojni backlog M1](razvojni-backlog-m1.md): 14 zadataka za tenant izolaciju, autentikaciju i uloge, domenski resolver, osnovu oba admina i dva različita demo frontenda. M1 je odabrani početni opseg koji razrađuje dijelove koraka 1–3 u nastavku. Njegove upute za prvi razvojni zadatak imaju prednost pred ranijom užom uputom iz 29.2. Poslovni moduli i produkcijske integracije dolaze nakon M1.

### 29.1. Inkrementi

| Korak | Isporuka | Izlazni kriterij |
|---|---|---|
| 0 | Potvrđene otvorene MVP odluke, provider sandbox i model dokumenata | Nema neriješenih pretpostavki o stvarnim naplatama/fiskalizaciji. |
| 1 | Laravel/Vue/Inertia temelj, tenant resolver, identitet, ovlasti, dva demo studija | Izolacijski testovi prolaze. |
| 2 | Osnovni platform admin, domene/dizajn status i studio admin shell | Kreiranje studija i sigurna dodjela dizajna. |
| 3 | Individualni premium frontend, shema CMS-a, HR/EN temelj i SEO rendering | Preview/objava i mobilni/reduced-motion QA. |
| 4 | Raspored, privatna dostupnost, booking, krediti, premještanje i dolasci | Konkurentnost, DST i kreditni testovi prolaze. |
| 5 | Stripe sandbox, računi/fiskalni sandbox, refund i reconciliation | Cijeli kupovni tok bez duplih operacija. |
| 6 | Email, portal, oba dashboarda/analitika, izvoz i import | Osnovni operativni tokovi provjereni. |
| 7 | Članarine, stalne grupe, automatski Meet i osnovna videoteka | Obnova, prava pristupa i greške integracija provjereni. |
| 8 | Produkcijska priprema, TLS, monitoring, restore i pilot onboarding | Dokumentirana release provjera svih A modula. |
| 9 | Lista čekanja, QR, napredni video programi i prodajne automatizacije | B/C kriteriji, trošak i limiti provjereni prije prodaje. |

Za svaki korak isporučiti implementaciju, relevantne migracije, demo podatke, provjere i operativne upute. Ne implementirati cijeli katalog u jednom potezu. Funkcionalnosti iz B/C mogu zahtijevati prethodno definiran podatkovni temelj, ali ne treba unaprijed graditi sve ekrane i integracije.

### 29.2. Uputa za početni development

> Koristi ovu specifikaciju kao izvor produktnih zahtjeva. Najprije pregledaj repozitorij i njegove upute, postojeće verzije i komponente. Za prvi razvojni zadatak implementiraj samo korak 1: temelj Laravel + Vue + Inertia, zajedničku bazu s tenant izolacijom, autentikaciju i backend ovlasti, dva demo studija, verificirani domenski resolver te testove zabrane pristupa drugom studiju. Registriraj dizajne kroz dopušten registar i pripremi odvajanje platform admina, studio admina i javnog frontenda, bez izgradnje svih modula. Ne dupliciraj backend po klijentu. Ne povezuj stvarne naplate niti produkcijske fiskalne račune. Jasno označi preporuke i otvorene odluke; ne tretiraj predloženi cjenik ili provider kao odobren. Dokumentiraj lokalno pokretanje, testove i sljedeći inkrement. Nastavi na druge korake tek u okviru zasebno zadanog opsega.

### 29.3. Pravila za buduće zadatke

Navesti ciljne F/AC identifikatore, fazu, očekivanu promjenu i izvanopsežne module. Sačuvati tenant izolaciju u svakoj novoj tablici, jobu i datoteci. Provjeriti aktualnu dokumentaciju providera prije stvarnih integracija. Nova tema ima vlastiti vizualni QA, ali koristi zajedničke poslovne ugovore. Faze i cijene ne mijenjati prešutno tijekom implementacije.

## 30. Otvorene odluke i izvori

### 30.1. Odluke prije početka modula

| Odluka | Kada mora biti riješena |
|---|---|
| PostgreSQL ili MySQL; podržane kompatibilne verzije stacka | Prije migracija i konkurentnih testova. |
| Autentikacija/2FA i platformska domena | Prije prvog admina. |
| Profil pilot studija i detaljna pravila stalnih grupa; opseg A potvrđen u 1.4 | Prije finalizacije rasporeda/proizvoda. |
| Valjanost paketa, otkazivanje, refund i kasno plaćanje | Prije kupovne logike. |
| Fiskalni provider, ugovor/API cijena, dokumenti i porezni profili | Prije produkcijske naplate. |
| Stripe Connect konfiguracija i odgovornosti | Prije produkcijskog onboardinga. |
| Amazon SES potvrđen; odabrati regiju/plan, dovršiti DNS, suppression i kvote | Prije stvarnog slanja. |
| Google organizatori/planovi i OAuth zahtjevi | Prije Meet automatizacije. |
| Bunny Stream potvrđen; validirati biblioteke, zaštitu, prava i potrošnju | Prije prodaje videoteke. |
| Fathom potvrđen; validirati izvještaje, API kvotu, cache i komercijalni plan | Prije produkcijske analitike. |
| Cijena, naknada za izradu, ugovor i opseg podrške | Prije prodajne ponude. |
| RPO/RTO, retencija, privatnost i prestanak usluge | Prije produkcijskog lansiranja. |
| Performance budget i kriteriji vizualne kvalitete | Nakon prvog stvarnog dizajna, prije objave. |

### 30.2. Referentni izvori

Primarni izvori korišteni u razgovoru i konsolidaciji. Provider API-ji, cijene i pravila mogu se mijenjati; pravnu i poreznu primjenu potvrditi za konkretni subjekt i transakciju. Dio provider stranica sadrži najave iz ranijeg razdoblja; ne koristiti buduće datume iz takvih najava kao novo jamstvo.

- [Stripe Connect direct charges](https://docs.stripe.com/connect/direct-charges) — model naplate studija.
- [Stripe Connect cjenik](https://stripe.com/connect/pricing) i [Stripe Hrvatska](https://stripe.com/en-hr/pricing) — provjera konfiguracije i naknada prije ugovaranja.
- [Fina: certifikati za fiskalizaciju](https://www.fina.hr/poslovni-digitalni-certifikati/poslovni-certifikati-za-fiskalizaciju/najcesca-pitanja-i-odgovori-o-fiskalizaciji) — identitet obveznika.
- [Porezna: fiskalizacija u krajnjoj potrošnji](https://porezna.gov.hr/fiskalizacija/gotovinski-racuni/gotovinski-racuni-uvod), [eRačun](https://porezna.gov.hr/fiskalizacija/bezgotovinski-racuni/eracun) i [Zakon](https://narodne-novine.nn.hr/clanci/sluzbeni/2025_06_89_1233.html) — mjerodavni regulatorni okvir.
- [E-racuni.hr: certifikati i fiskalizacija](https://www.e-racuni.hr/hrracuni/fiskalizacija-2-0-svi-odgovori-na-jednom-mjestu/), [API povezivanje](https://e-racuni.hr/hrracuni/web-shop/) i [uvjeti](https://e-racuni.hr/hrracuni/uvjeti-i-pravila-uporabe/) — kandidat, partnerski model nije potvrđen.
- [Google Calendar stvaranje događaja](https://developers.google.com/workspace/calendar/api/guides/create-events) i [referenca](https://developers.google.com/workspace/calendar/api/v3/reference/events) — Meet i događaji.
- [Bunny Stream cjenik](https://bunny.net/docs/stream/pricing), [TUS upload](https://bunny.net/docs/stream/tus-resumable-uploads) i [zaštita](https://bunny.net/docs/stream/security) — potvrđeni video provider.
- [SES cjenik](https://aws.amazon.com/ses/pricing/) i [verifikacija identiteta](https://docs.aws.amazon.com/ses/latest/dg/creating-identities.html) — potvrđeni email provider.
- [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering) — javni rendering.
- [Nginx HTTPS](https://nginx.org/en/docs/http/configuring_https_servers.html), [ACME provjere](https://letsencrypt.org/docs/challenge-types/) i [limiti](https://letsencrypt.org/docs/rate-limits/) — domene i certifikati.
- [Forge domene](https://laravel.com/forge/docs/sites/domains) i [Caddy On-Demand TLS](https://caddyserver.com/on-demand-tls) — opcionalne infrastrukturne alternative.
- [Momence yoga proizvod](https://www.momence.com/yoga-studio-software/) — ranije razmatran konkurentski opseg, bez pretpostavke da se sve funkcije moraju kopirati.

### 30.3. Pravilo održavanja specifikacije

Ovaj dokument je jedinstveni razvojni pregled. Kada se potvrdi otvorena odluka, ažurirati registar odluka, relevantno poglavlje, katalog funkcija i kriterije prihvata. Ne ostavljati dva proturječna pravila u različitim poglavljima. Verziju 0.1 ne koristiti kao paralelni autoritativni izvor: njezina univerzalna tema i šire klijentsko uređivanje zamijenjeni su individualnim frontendovima i kontroliranim CMS-om.
