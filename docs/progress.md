# Napredak

## M1-01 — repozitorij i lokalno pokretanje

Datum: 4. listopada 2026.
Status: implementirano; prihvat čeka vizualni pregled i svježu instalaciju.

Implementirano:

- Lokalni Git repozitorij na grani main, bez commita ili remotea.
- Docker MySQL 8.4.11: razvojna baza na 13306 i zasebna testna na 3308.
- MySQL konfiguracija u `.env.example` i lokalnom `.env`; PHPUnit koristi
  isključivo testni kontejner. SQLite podaci nisu brisani ni preneseni.
- Reproducibilni setup koristi lockfileove i `npm ci`; build uključuje SSR.
- Neutralna početna stranica i README s Windows/Docker uputama.
- PHPStan limit 512 MB; korisnički projektni dokumenti izuzeti iz formattera.
- Lokalni mail koristi array transport bez stvarnog slanja i logiranja tokena.

Provjere:

- Composer platform requirements: prolaze na PHP 8.5.11.
- Composer validate: prolazi. Install dry-run potvrđuje usklađen lockfile;
  dohvat udaljenog popisa sigurnosnih filtera nije bio dostupan iz sandboxa.
- MySQL migracije: sve četiri migracije uspješno izvršene.
- Pest na MySQL-u: 40 testova, 130 assertiona, sve prolazi.
- PHPStan: prolazi bez pogrešaka uz limit 512 MB.
- Vue TypeScript: prolazi.
- Client i SSR build: prolaze uz upozorenja plugina o fontovima/sourcemapama.
- Pint `--dirty --format agent`: prolazi.
- Pint nad izmijenjenim `config/database.php`: prolazi.
- `npm run check`: formatiranje 67 datoteka i lint 58 datoteka prolaze.
- SSR health check prolazi; HTTP odgovor na izoliranom poslužitelju sadrži
  `data-server-rendered`, naslov i uvodni tekst. Provjeren i render iz builda.

Otvoreno:

- Vizualni pregled nije dostupan: alat za preglednik nema povezani browser.
- Nije izvedena instalacija iz potpuno svježeg checkouta niti GitHub CI run.
- Ranije pokrenuti dev/queue procesi zadržavaju SQLite postavke i zahtijevaju restart.

Korisnik je potvrdio da lokalno okruženje radi. Instalacija iz svježeg checkouta
i GitHub CI i dalje nisu zasebno provjereni.

## M1-02 — podatkovni model temelja

Datum: 5. listopada 2026. Status: dovršeno u opsegu podatkovnog temelja.

- Dodani modeli, factoryji i migracija za studije, domene, javne profile,
  odvojene platform admine, pozivnice, audite te tenant/platform auth spremišta.
- Obvezan tenant na korisniku; MySQL generirani normalizirani email i hostname,
  jedinstveni indeksi i složeni FK-ovi sprečavaju neispravne tenant veze.
- Lokalni eksplicitni seed stvara dva demo studija i verificirane domene bez
  računa/lozinki. Seed je izvršen u razvojnoj bazi.
- README dokumentira globalne tablice, zaštite i granice implementacije.
- Privremena HTTP zaštita zatvara auth/admin scaffold do tenant-aware tokova;
  neutralna javna stranica ne izlaže korisnika stare sesije.

Provjereno na MySQL-u 8.4.11:

- Migracija i lokalni seed prolaze; Boost schema potvrđuje složene FK-ove.
- 28 testova modela i 12 testova HTTP zaštite prolaze.
- Cijela zbirka: 80 testova, 42 prolaze, 38 legacy auth/settings/dashboard
  testova je eksplicitno preskočeno do M1-05/M1-06; 105 assertiona.
- PHPStan i Pint prolaze. Paketi i njihove zaključane verzije nisu mijenjani.

Nije još implementirano: tenant scope/policies, stvarna tenant autentikacija,
sanitizirani audit writer i potrošnja pozivnica. To pripada sljedećim zadacima.
Sljedeći korak: M1-03 — domenski resolver i kontekst zahtjeva.

## M1-03 — domenski resolver i kontekst zahtjeva

Datum: 5. listopada 2026. Status: dovršeno.

- Normalizacija hostova i IDN/punycode pri zapisu i čitanju domena.
- Samo aktivne verificirane domene aktivnih studija; neutralni 404/503 bez
  fallbacka. Platform domena ima zaseban kontekst bez implicitnog tenanta.
- Scoped kontekst postavljen prije autha i route bindinga te očišćen u finally.
- Platform/studio putanje odvojene; klijentski tenant ID se ignorira.
- Eksplicitni proxy popis bez wildcarda i automatskog cloud povjerenja.
- `.env.example` i lokalni APP_URL koriste platformsku domenu; README sadrži
  hosts upute. Sistemski hosts/DNS nisu mijenjani.

Provjere:

- 53 ciljana testa normalizacije, resolvera i privremene auth zaštite prolaze.
- Cijela zbirka: 121 test, 83 prolaze, 38 legacy testova preskočeno; 170 assertiona.
- PHPStan bez pogrešaka; Pint prolazi.
- Stvarni HTTP: platform/lotus/balance 200; nepoznat i krivotvoreni host 404;
  platformska putanja na studio domeni 404. Privremeni poslužitelj nakon provjere
  zaustavljen.
- Testirano čišćenje konteksta nakon iznimke, redoslijed prije autha/bindinga,
  promjena statusa domene između zahtjeva i zabrana CORS preflight bypassa.

Ograničenja: auth/admin rute ostaju zatvorene do M1-05; tenant scope/policies i
cache/job izolacija slijede u M1-04. Boost transport zatvoren tijekom M1-03;
API provjere izvedene kroz instalirani framework i službenu dokumentaciju.

Sljedeći korak: M1-04 — zaštita podataka, cachea i jobova.

## M1-04 — zaštita podataka, cachea i jobova

Datum: 5. listopada 2026. Status: dovršeno.

- Tenant scope za korisnike, profile, domene, pozivnice i tenant audit; bez
  konteksta upiti odbijaju rad, uključujući platform kontekst.
- Serverska dodjela vlasništva, zabrana prijenosa zapisa drugom studiju,
  zaštita relacija, implicitnog route bindinga i učitanih modela pri zapisu/brisanju.
- Zaštićeni `fresh`, `refresh` i obnova serijaliziranih modela.
- Policy i akcije za čitanje/izmjenu profila samo owneru vlastitog studija;
  eksplicitno autoriziran platformski pregled registra studija.
- Tenant cache izoliran po studiju i vrsti podatka na database storeu.
- Job middleware dohvaća aktivni studio po internom ID-ju i čisti kontekst
  nakon uspjeha, iznimke i nepostojećeg/neaktivnog studija.
- Seeder uspostavlja kontekst za zapise; legacy DB test fixturei i dalje
  neposredno provjeravaju constraintove MySQL-a.

Provjere:

- Cijela zbirka: 150 testova, 112 prolazi, 38 ranijih legacy testova preskočeno;
  300 assertiona. Novi M1-04 testovi: 29.
- Isti stvarni database queue worker obrađuje četiri posla za dva studija,
  uključujući iznimku; nema curenja konteksta ni podataka u sljedeći posao.
- Poznati tuđi ID vraća 404 za čitanje i izmjenu profila; provjerene manipulirane
  relacije, uloge, neaktivni korisnik, platform identitet i validacija podataka.
- PHPStan bez pogrešaka; Pint prolazi.
- Boost shema baze i verzijski specifična dokumentacija dostupni nakon početnog
  prekida transporta. Nisu mijenjani paketi ni razvojni podaci.

Ograničenja: nema novih produkcijskih ekrana/ruta; HTTP testovi koriste testne
rute s postojećim middlewareom i bindingom. Auth/admin privremena zabrana ostaje
do M1-05. Puni obrazac i audit profila slijede u zadatku studio administracije.
Tenant poslovi prenose skalarne ID-jeve; modeli se učitavaju tek u `handle()`.

Sljedeći korak: M1-05 — odvojena studio/platform prijava, registracija,
verifikacija emaila i oporavak računa.

## M1-05 — prijava, registracija i oporavak računa

Datum: 5. listopada 2026. Status: implementirano i automatski testirano;
vizualni pregled ostaje otvoren.

- Odvojeni studio/platform guardovi, aktivni provideri, session tablice i reset
  brokeri. Tenant sesije i tokeni imaju serversko ograničenje studija.
- Customer registracija ignorira klijentsku ulogu i tenant ID; nema javne
  platform registracije. Interaktivna naredba `platform:create-admin` traži
  skrivenu lozinku; razvojni admin nije automatski kreiran.
- Login/logout, verifikacija emaila, zaboravljena lozinka i reset kroz Fortify.
  Minimalni ekran računa i Wayfinder poveznice koriste odgovarajući auth kontekst.
- Host-only/HttpOnly cookies, produkcijski Secure, regeneracija sesije i CSRF
  tokena. Test stvarnog CSRF middlewarea otkrio je gubitak početnog tokena pri
  promjeni konteksta; problem je ispravljen i regresijski test prolazi.
- Pouzdane primarne domene u email poveznicama, izolirani rate limiti i jednak
  odgovor na reset za postojeće/nepostojeće račune.
- Mailpit u Dockeru: inbox na loopback portu 18025, SMTP na 11025 zbog zauzetog
  porta 1025. Lokalni `.env` i primjer koriste SMTP i novi session driver.
  Potreban je restart postojećeg `composer dev` procesa.

Provjere:

- MySQL puna zbirka: 168 testova — 151 prolazi, 17 preskočeno, 519 assertiona.
  Vraćen je 21 legacy auth test; preostali čekaju 2FA/settings/dashboard.
- Provjereni isti email s različitim lozinkama, kopirani session/remember cookie,
  pogrešna domena i istek tokena, jednokratni reset, podmetnuta uloga/tenant,
  neaktivni korisnici, CSRF, rate limit i izmjena konteksta u istoj aplikaciji.
- Stvarni SMTP i Mailpit API potvrđuju verifikacijske/reset poruke na jedinstvene
  `example.test` adrese i pouzdane domene; nema slanja vanjskim primateljima.
- PHPStan, Pint, frontend format/lint, Vue TypeScript i client/SSR build prolaze.
- Stvarni HTTP/SSR odgovori: studio login/registracija i platform login/reset
  vraćaju 200, renderirane obrasce i ispravne action putanje. Platform login
  nema registracijsku poveznicu. Privremeni HTTP i SSR procesi zaustavljeni.
- Boost schema i dokumentacija korišteni uz instalirane verzije; bez nadogradnje
  PHP/JS paketa, novih migracija ili automatskog kreiranja razvojnih računa.

Otvoreno: vizualni pregled nije moguć bez povezanog preglednika. Administracija
i 2FA rute ostaju privremeno zatvorene; produkcijski SES nije dio M1.

Sljedeći korak: vizualno provjeriti auth obrasce te M1-06 — matrica uloga,
obvezni TOTP enrolment za ownera/platform admina, recovery kodovi i potvrda lozinke.

## M1-06 — uloge i 2FA

Datum: 6. listopada 2026.
Status: implementirano i automatizirano provjereno; prihvat čeka vizualni pregled.

Implementirano:

- Policies/gates matrica za platform registar, studio administraciju, portal,
  javni profil i osoblje. Tenant granice, aktivnost, verifikacija i obvezni
  potvrđeni 2FA vrijede i pri izravnom pozivu policies/akcija.
- Owner/platform enrolment kroz Fortify 1.40.0, uz potvrdu lozinke, QR/ručni
  ključ, potvrdu stvarnog TOTP koda i osam recovery kodova. Obvezni 2FA nije
  moguće isključiti; ostalim korisnicima ostaje opcionalan.
- Odvojene studio/platform rute, ispravna preusmjeravanja, hrvatsko sučelje
  i Wayfinder poveznice. Administrativne rute zasad prikazuju minimalni račun;
  poslovni ekrani i pozivnice ostaju opseg M1-07/M1-08.
- Challenge ponovno provjerava aktivnost. MySQL row lock serijalizira potrošnju
  recovery koda; TOTP replay cache odvojen je po tenantu i identitetu umjesto
  Fortifyjeva zajedničkog ključa temeljenog samo na kodu.
- Rate limit za challenge, enrolment, potvrdu lozinke i obnovu kodova. Tajne
  nisu u Inertia propsima/old inputu; zaštićeni JSON odgovori imaju no-store.
  Vue tajne više nisu u globalnom stanju; dohvaćaju se na zahtjev i čiste pri
  odlasku. README sadrži demo enrolment bez bypassa.
- Bez novih paketa, migracija, razvojnih računa ili promjena lokalnog `.env`.

Izvršene provjere:

- `php artisan test --compact`: MySQL, **194 testa — 182 prolazi, 12 preskočeno,
  894 assertiona**. Vraćeno pet scaffold auth testova i dodano 26 testova u
  `RoleAndTwoFactorTest`; preostali preskočeni testovi čekaju settings/admin.
- Pozitivna/negativna matrica uloga, nepotpun enrolment, TOTP i recovery login,
  jednokratni recovery, replay i cross-domain sesija, deaktivacija tijekom
  challengea, password timeout, rate limit, neispravni tipovi ulaza i tajne.
- Pint `--dirty --format agent`, PHPStan, Vue TypeScript, frontend format/lint
  te client/SSR build prolaze. Vite provjere zahtijevale su pokretanje izvan
  sandboxa zbog `spawn EPERM`; bez izmjena ovisnosti.
- Devet renderiranja auth/TwoFactorSettings, ConfirmPassword i
  TwoFactorChallenge kroz isti SSR proces redom studio → platforma → studio:
  ispravne action putanje, bez SSR pogrešaka. SSR health check prolazi;
  privremeni proces zaustavljen. Na ovom Windowsu izravni
  `node bootstrap/ssr/app.js` radi kad Artisan launcher prijavi nedostupnu putanju.

Otvoreno: preglednik nije povezan, a ugrađeni browser nije dostupan. Vizualni
pregled, mobilne širine, tipkovnica i hydration ostaju neprovjereni. Nije
proveden konkurentni višeklijentski stress test recovery potrošnje; automatizirano
je provjerena jednokratna potrošnja na MySQL-u. Nema produkcijskog deploya.

Sljedeći korak: ručno proći demo enrolment i pregledati 2FA sučelje; zatim
M1-07 — platform registar studija, domene i inicijalne owner pozivnice.

## M1-07 — platform admin

Datum: 6. listopada 2026.
Status: implementirano i automatizirano provjereno; prihvat čeka vizualni pregled.

- Zaseban platform layout, stvarni brojevi statusa, paginirani registar,
  pretraga naziva/domena, kreiranje i detalj studija. Postojeći platform
  policy, host granica, verifikacija emaila i 2FA štite sve radnje.
- Tenant, profil, pending primarna domena, owner pozivnica i audit nastaju
  atomarno. Dopušteni dizajni `lotus`/`balance` imaju zajednički registar;
  mapiranje javnih komponenti ostaje M1-09. Dodatne domene nastaju pending.
- Ručna verifikacija traži potvrdu provjere i sanitiziranu operativnu referencu.
  Primarna domena aktivira pending studio. Suspenzija traži potvrdu posljedica;
  nema brisanja. Platform audit sadrži autora, cilj, vrijeme i odabrana polja.
- Owner poziv čeka verificiranu primarnu domenu; slanje i retry imaju vidljiv
  status. Nova migracija dodaje dva nullable vremena dostave. SMTP pogreška
  ne gubi tenant i ne zapisuje iznimku s mogućim tajnama. Retry rotira token.
- Minimalni prihvat početnog owner poziva uključen je radi upotrebljivosti
  M1-07. Token vrijedi sedam dana, vezan je uz tenant/email, hashiran u bazi,
  jednokratan pod MySQL lockom. URL fragment izbjegava slanje tokena serveru
  pri GET-u; nema tokena u Inertia propsima/old inputu. Nakon prihvata slijedi
  prijava i obvezni 2FA. Ostalo upravljanje osobljem ostaje M1-08.
- Regresija platform → studio login otkrila je Fortify scoped korak koji
  zadržava prethodni guard u istoj aplikaciji. Transient registracija osigurava
  aktualni guard; cijeli tok i raniji auth/2FA testovi prolaze.

Izvršene provjere:

- Nova migracija uspješno izvršena na razvojnom MySQL-u; postojeći podaci
  sačuvani. Nema novih razvojnih identiteta ni izmjena `.env`/ovisnosti.
- `php artisan test --compact tests/Feature/PlatformTenantTest.php`:
  **29 prolazi, 321 assertion**. Pokriveni rollback, nepouzdani inputi,
  nepoznati dizajn, duplikati hostova, autor/target audita, domain binding,
  suspenzija, uloge, istek/opoziv/ponovna potrošnja poziva, rotacija tokena,
  postojeći račun, izolirani limiter, SMTP neuspjeh/retry i escape emaila.
- Stvarni lokalni SMTP/Mailpit test potvrđuje email na jedinstvenu
  `example.test` adresu i poveznicu na verificiranu domenu s token fragmentom.
- `php artisan test --compact`: **223 ukupno; 211 prolazi, 12 ranije
  preskočenih, 1215 assertiona** na MySQL-u.
- Pint `--dirty --format agent`, PHPStan, Vue TypeScript i frontend
  format/lint prolaze. Client i SSR build prolaze uz ranija font/sourcemap
  upozorenja. Vite je zahtijevao izvođenje izvan sandboxa zbog `spawn EPERM`.
- Stvarni SSR render registra, kreiranja, detalja i prihvata owner poziva
  prolazi kroz isti Node proces; SSR health check prolazi. Pregled ruta
  potvrđuje devet platform endpoints. Privremeni SSR proces zaustavljen.

Otvoreno: browser inventory je prazan, a ugrađeni browser nije dostupan.
Vizualni pregled, mobilni prikaz, tipkovnica i hydration nisu potvrđeni.
Nije proveden konkurentni višeklijentski stress test prihvata pozivnice.
Reaktivacija, promjena primarne domene i rješavanje konflikta postojećeg
owner emaila nemaju UI u M1-07; nema implicitnog promicanja tuđeg računa.
DNS/TLS i produkcijsko slanje/deploy nisu izvršeni.

Sljedeći korak: vizualno proći platform registar i početnu owner pozivnicu;
zatim M1-08 — studio admin, uređivanje javnog profila i upravljanje osobljem.

## Vizualni pregled M1-01–M1-07

Datum: 6. listopada 2026. Status: djelomično izvršeno.

- Chrome je povezan i lokalni dev server dostupan. Pregledane javne naslovnice,
  studio auth obrasci i platform email verifikacija na 375, 768 i 1440 px.
- Nema horizontalnog overflowa u 24 mjerenja; provjereni Tab/Enter, vidljivi
  fokus i native required validacija. Samplirani browser error/warn zapisi prazni.
- Pregledane neutralne greške nepoznatog hosta i platform putanje na studio hostu.
- Zapažanje: auth scaffold još prikazuje engleski sadržaj; tekstovi nisu mijenjani.
- Zaštićeni M1-06/M1-07 ekrani čekaju email verifikaciju i 2FA otvorenog
  platform računa; puni vizualni prihvat ostaje otvoren. Backend testovi nisu
  ponavljani tijekom ovog pregleda. Nije mijenjan aplikacijski kod ni razvojni podaci.
- [Izvještaj i granice provjere](vizualni-pregled-m1.md), uz 27 screenshotova
  i mjerenja. Sljedeći korak: dovršiti platform/studio sesije i preostale ekrane.

Ponovni pokušaj 6. listopada: platform registar i Lotus račun preusmjeravaju
na prijavu. Browser/server rade; zaštićeni pregled čeka novu autentificiranu
sesiju. Rezultat je dopisan u vizualni izvještaj; nema novih provjerenih ekrana.

Nastavak 6. listopada nakon korisnikove prijave s 2FA:

- M1-06: pregledani platform sigurnost s aktivnim 2FA i potvrda lozinke.
- M1-07: pregledani registar, kreiranje, oba detalja i prazna pretraga na
  375/768/1440 px; 21 mjerenje bez overflowa. Pretraga Lotus i Inertia
  navigacija rade; prazan submit kreiranja fokusira obvezni naziv.
- Dodano 30 PNG snimki i protected-measurements.json u vizualni izvještaj.
- Otvoren nalaz: hydration mismatch Label oznake na potvrdi lozinke.
  Studio sesija, QR/recovery/challenge i admin stanja nakon izmjena ostaju
  neprovjereni; puni vizualni prihvat M1-06/M1-07 ostaje otvoren.
- Nema izmjena koda/podataka ni ponavljanja backend testova/builda.
  Sljedeće: ispraviti hydration nalaz, zatim pregledati preostala stanja.

### M1-06 — ispravak hydrationa potvrde lozinke

6. listopada 2026. — dovršeno za ovaj nalaz.

- `ConfirmPassword.vue`: `Label htmlFor` zamijenjen podržanim `for`, koji
  povezuje oznaku s poljem i daje dosljedan SSR/klijentski prikaz.
- Chrome: izravno otvorena SSR stranica, bez warn/error zapisa; klik na
  oznaku fokusira `password`. Vizualni prikaz uredan.
- `npm run types:check` i `npm run build` (client + SSR) prolaze. Build je
  zahtijevao izlazak iz sandboxa zbog `spawn EPERM`; ostaju ranija
  font/sourcemap upozorenja. Backend testovi nisu ponavljani za ovaj atribut.
- Ažuriran vizualni izvještaj. Sljedeće: preostali studio i 2FA/pozivnica
  scenariji iz izvještaja; puni vizualni prihvat i dalje je djelomičan.

### Dodatni vizualni prolaz

6. listopada 2026.: platform račun pregledan na tri širine bez overflowa;
   pretraga Balance domene Enterom vraća jedan rezultat. Provjerena vidljivost
   skip poveznice pri fokusiranju i aktivacija sidra. Lotus račun zahtijeva
   zasebnu studio prijavu; otvoren tab za nastavak. Izvještaj dopunjen granicama
   provjere i potrebnim razvojnim stanjima. Puni vizualni prihvat ostaje otvoren.
   Sljedeće: korisnikova studio prijava, potom pregled računa i dostupnih 2FA stanja.

Nastavak nakon studio prijave, 6. listopada: Lotus račun i sigurnost s
aktivnim 2FA pregledani na 375/768/1440 px, šest mjerenja bez overflowa.
Navigacija radi; browser warn/error uzorak prazan. Dodane tri PNG snimke
sigurnosti i studio-measurements.json. Recovery kodovi ostaju skriveni;
sigurnosne postavke nisu mijenjane. Izvještaj ažuriran. Preostaju QR/enrolment,
challenge/recovery prikaz, svijetla tema i admin scenariji s dodatnim podacima.

Dodatni nastavak: native required validacija praznih obrazaca suspenzije i
dodavanja domene fokusira odgovarajuća polja, bez promjene podataka; konzola
bez warn/error zapisa. Stranica appearance prikazuje neutralni tekst u
pripremi, pa nema dostupne kontrole teme. Za challenge zatražen je korisnikov
novi tijek prijave koji staje prije unosa koda. Vizualni izvještaj dopunjen;
sljedeći korak je pregled challengea kada se taj ekran otvori.

M1-06 — challenge pregledan 6. listopada: studio OTP i recovery prijava na
tri širine, šest mjerenja bez overflowa. Prebacivanje Enterom i native
required validacija praznog recovery polja prolaze; browser konzola bez
warn/error zapisa. Dodano šest PNG snimki i challenge-measurements.json.
Vraćen OTP ekran za korisnikovu prijavu. Preostaju QR/enrolment, prikaz
izdanih recovery kodova, nevaljani kodovi i ranije navedeni admin scenariji.

M1-07 dodatni vizualni pregled: kroz UI kreiran zaseban razvojni studio #3
„Vizualni QA — razvojni studio”. Pregledani dugi opis, pending domena,
pripremljena neposlana pozivnica i popunjeni audit na tri širine bez overflowa.
Prazna verifikacija zaustavlja native validacija. Nevaljana referenca
suspenzije daje inline englesku poruku; format nije objašnjen u UI-ju.
Ispravna suspenzija testnog studija daje poruku uspjeha i audit. Studio #3
ostaje suspendiran, domena neverificirana, pozivnica neposlana; nema owner
računa ni promjena Lotusa/Balancea. Devet snimki i četiri mjerenja dodani u
izvještaj. Konzola bez warn/error zapisa. Preostaju verifikacija/aktivacija,
slanje/prihvat poziva, paginacija i QR/enrolment/recovery prikaz.

## M1-08 — studio admin i osoblje

Datum: 6. listopada 2026.
Status: implementirano i automatizirano provjereno; potpuni prihvat čeka
vizualni pregled owner ekrana i pozivnica.

- Studio layout s identitetom aktivnog studija, navigacijom prema ovlastima,
  owner pregledom te ograničenim manager/instructor pregledom. Minimalni portal
  prikazuje vlastite podatke i poruku da rezervacije dolaze kasnije.
- Owner uređuje fiksna javna polja. Neutralna naslovnica čita samo dopuštena
  polja trenutnog studija; izmjena je odmah javna. Profil i audit spremaju se
  u istoj transakciji. Registar naziva studija ostaje operativni identitet.
- Pozivi managera/instruktora, ponovljeno slanje s rotacijom tokena, status
  SMTP neuspjeha i opoziv. Poziv je vezan uz tenant/email, vrijedi sedam dana,
  token je hashiran i prenosi se URL fragmentom. Prihvat koristi postojeći
  zaključani tok owner pozivnice, bez promicanja postojećeg računa.
- Owner mijenja samo manager/instructor uloge i deaktivira osoblje uz potvrdu
  posljedica. Deaktivacija uklanja tenant sesije i remember token; postojeći
  aktivni provider nastavlja provjeravati aktivnost pri svakom zahtjevu.
- Tenant audit bilježi profil, pozive, uloge i deaktivaciju bez tajni.
  Backend policies i tenant binding štite sve radnje; Vue koristi Wayfinder.
- Nema novih migracija, paketa, razvojnih identiteta, izmjena `.env` niti
  produkcijskog slanja. Scaffold uređivanje osobnog računa ostaje zatvoreno;
  M1-08 minimalni profil služi pregledu. Dizajni ostaju M1-09/M1-10.

Izvršene provjere:

- `php artisan test --compact tests/Feature/StudioAdministrationTest.php`:
  **21 prolazi, 289 assertiona**, MySQL. Provjereni javni profil i izolacija,
  autorizacija, obvezni enrolment, nevaljani ulazi, podmetnuta uloga/email/tenant,
  tuđe pozivnice/osoblje, rotacija, istek/opoziv, jednokratni prihvat,
  konflikt postojećeg računa, deaktivacija stvarne sesije i SMTP failure/retry.
- Regresije StudioAdministration, PlatformTenant, RoleAndTwoFactor i
  TenantIsolation: **97 prolazi, 1053 assertiona** prije četiri dodatna testa.
- `php artisan test --compact`: **244 ukupno, 232 prolazi, 12 ranije
  preskočenih, 1504 assertiona**. Nema novih preskočenih testova.
- Pint `--dirty --format agent`, PHPStan, Vue TypeScript, frontend format/lint
  te client/SSR build prolaze. Vite zahtijeva izvođenje izvan sandboxa zbog
  `spawn EPERM`; ostaju ranija font/sourcemap upozorenja.
- Četiri nova ekrana renderirana kroz isti SSR proces: dashboard, javni profil,
  popunjeno osoblje/pozivnice i prihvat poziva. SSR health check prolazi.
- Chrome: javni Lotus na 375 px i portal polaznika na 375/768/1440 px bez
  overflowa. Ispravljen gubitak hrvatskih znakova pri početnom zapisu novih
  Vue datoteka; ponovljeni build i pregled potvrđuju ispravne znakove.
  Uzorak browser warn/error zapisa prazan. Viewport vraćen na izvornu veličinu.

Otvoreno: owner administracija i stvarni UI prihvata pozivnice nisu vizualno
pregledani; dostupna Lotus sesija je customer. Zatražena je korisnikova owner
prijava s 2FA. Nije izvršen konkurentni višeklijentski stress test prihvata;
jednokratnost je provjerena na MySQL-u. Cijeli M1-08 nije označen dovršenim.

Sljedeći konkretan korak: pregledati owner dashboard, obrazac profila,
osoblje i pozivnice na tri širine nakon owner prijave; zatim M1-09.

## M1-09 — registar dizajna i javni ugovor podataka

Datum: 6. listopada 2026. Status: dovršeno za opseg M1-09.

- Jedinstveni `DesignRegistry` mapira `lotus`/`balance` na četiri eksplicitne
  Home/About komponente. Platform izbori i validacija koriste isti registar.
  Nepoznati spremljeni ključ daje 503 i siguran warning s tenant ID-jem;
  nema fallbacka, proizvoljnih putanja ni zapisivanja neprovjerenog ključa.
- `PublicStudioController` i readonly `PublicStudioProfile` izlažu pet javnih
  polja. Javna stranica ne dijeli sirove modele, identitet prijavljenog korisnika,
  ovlasti, administrativne validacijske poruke ni flash podatke. Profil koji još
  ne postoji daje samo naziv svojeg studija i prazne kontakte.
- Regresijski test otkrio zadržane Inertia shared propse kroz uzastopne zahtjeve
  iste aplikacije. Middleware sada čisti prethodne shared propse prije stvaranja
  aktualnog ugovora; privatni podaci ne prelaze na javne stranice.
- Osnovne Home/About stranice, zasebni layouti sa scoped CSS-om, zajednički
  kontaktni prikaz i TypeScript ugovor. Wayfinder povezuje navigaciju i prijavu.
  Eksplicitno lazy učitavanje kroz instalirani Inertia Vite plugin.
- Bez novih ovisnosti, migracija ili promjena razvojnih podataka. Puni vizualni
  dizajni/mediji/animacije ostaju M1-10; SEO i cjeloviti SSR scenariji M1-11.

Provjere:

- `php artisan test --compact tests/Feature/PublicStudioTest.php`:
  **13 prolazi, 212 assertiona**. Pokriveni svi registrirani prikazi, promjena
  dizajna uz isti profil, nevaljani ključ/putanja, neutralna platforma,
  nepoznate/pending domene, izostanak profila, podmetnuti tenant/dizajn,
  uzastopni Lotus/Balance zahtjevi i privatni session payload.
- `php artisan test --compact`: **257 ukupno, 245 prolazi, 12 ranije
  preskočenih, 1716 assertiona** na MySQL-u.
- Pint, PHPStan, Vue TypeScript, frontend format/lint i client/SSR build prolaze.
  Vite izvršen izvan sandboxa zbog poznatog spawn ograničenja; postojeća
  font/sourcemap upozorenja ostaju. Nema novih upozorenja tipova.
- Build manifest: sva četiri javna ulaza su dinamička; tranzitivni statički
  importi svakog ulaza sadrže samo vlastiti layout/CSS, bez drugog dizajna.
- Chrome: obje Home/About stranice pregledane na 375 px; Lotus About i Balance
  Home/About i na 1440 px. Šest DOM mjerenja bez overflowa. Inertia navigacija
  klikom i Enterom radi; pregledani warn/error zapisi prazni. DOM stilovi na
  svakom hostu sadrže samo pripadajući dizajn. Viewport resetiran.
- Stvarni HTTP Lotus Home → Balance About → Lotus About kroz isti SSR proces:
  200 i renderirani naslovi u početnom HTML-u. SSR health check prolazi.
  Privremeni PHP/SSR procesi za pregled zaustavljeni nakon provjera.

Ograničenja: razvojni profili imaju malo sadržaja; puni vizualni prihvat s
medijima, dugim sadržajem i reduced motion pripada M1-10. Raniji owner vizualni
pregled M1-08 ostaje otvoren.

Sljedeći korak: M1-10 — pune vizualno različite kompozicije Lotusa i Balancea,
lokalni demo mediji te pristupačne animacije.

Završna provjera 7. listopada 2026.: `npm run check:fix` nakon dopune
dokumentacije prolazi bez upozorenja ili lint pogrešaka u 81 datoteci.
Prethodni pokušaj nije izvršen zbog limita automatske provjere odobrenja.
Testovi i build nisu ponavljani jer aplikacijski kod nije mijenjan.

## M1-10 — dva vizualno različita demo frontenda

Datum: 7. listopada 2026. Status: dovršeno za opseg M1-10.

- Četiri Home/About stranice: Lotus ima toplu editorial kompoziciju, serifne
  naslove i organski hero; Balance tamni geometrijski identitet, široki hero
  i drukčiji raspored predstavljanja. Zajednički javni DTO/kontakt ostaju
  izvor podataka. Nema aktivnih booking kontrola ni novih ovisnosti.
- Dvije izvorne lokalne SVG ilustracije (CC0-1.0, svaka ispod 2 KB), lazy
  učitavanje i scoped CSS po dizajnu. Ilustracije nisu fotografije studija.
- Zajednički motion composable: reveal 550 ms, parallax najviše 18 px samo
  od 768 px, reduced-motion pri otvaranju i pri promjeni postavke. Sadržaj
  ostaje vidljiv bez inicijalizacije. Unmount čisti sve pripadajuće resurse.
- Vidljiv fokus, skip poveznica koja fokusira main, Wayfinder navigacija
  i postojeća prijava. Prazan kontakt ne stvara praznu kontaktnu sekciju.

Provjere:

- `php artisan test --compact tests/Feature/PublicStudioTest.php`: **13 prolazi,
  212 assertiona** na zasebnoj MySQL bazi. Backend nije mijenjan.
- `npm run types:check`, `npm run check:fix` i `npm run build`: prolaze;
  formatter/lint bez upozorenja ili pogrešaka u 82 datoteke. Client i SSR build
  imaju ranija font/sourcemap upozorenja. Vite pokrenut izvan sandboxa zbog
  poznatog `spawn EPERM`. Puna testna zbirka nije ponavljana.
- Browser QA: sve četiri stranice na **375/768/1440 px**, bez horizontalnog
  overflowa, prekrivenih kontrola ili nečitljivog sadržaja. Dodatnih 12 provjera
  s browser-only demo opisom/kontaktom, dugim emailom i višerednom adresom.
  Podaci u bazi nisu mijenjani.
- Tipkovnica: vidljiv fokus kroz navigaciju, Enter otvara prijavu oba studija;
  skip link prenosi fokus na glavni sadržaj.
- Reduced-motion: sve četiri stranice vidljive, bez aktivnih animacija ili
  parallaxa; promjena postavke uživo uklanja scroll/resize listenere i observer.
- Po **20 Inertia navigacija po dizajnu**: stabilno 6 praćenih listenera
  (uključuje 2 framework listenera), 1 observer i 0 animacija u mirovanju.
  Nakon odlaska na login ostaju 2 framework listenera i 0 javnih observera.
  Novi browser warn/error uzorak za ponovljene Balance navigacije prazan.
- Bez JavaScripta: obje Home/About stranice imaju SSR naslov i vidljiv sadržaj,
  nema overflowa na 375 px; standardne navigacijske poveznice rade.
- [Vizualni izvještaj](vizualni-pregled-m1.md#m1-10--javni-demo-dizajni-7-listopada-2026)
  povezuje 22 screenshota: 12 osnovnih, 8 s označenim demo podacima i 2
  reduced-motion snimke. Privremeni HTTP/SSR QA procesi i router uklonjeni;
  postojeći razvojni procesi i public/hot sačuvani.

Ograničenja: pregled u Chromiumu s emuliranim širinama, ne na fizičkom slabijem
mobitelu ili u Safari/Firefoxu. Razvojni profili zasad nemaju opis/kontakt;
popunjavanje demo podataka ostaje M1-12. Potpuni SEO/SSR zahtjevi ostaju M1-11,
raniji otvoreni admin pregledi nisu zatvoreni ovim zadatkom.

Sljedeći korak: M1-11 — metadata/canonical/noindex, SSR izolacija i fallback.
Za širu integracijsku provjeru pokrenuti `php artisan test --compact`.

## M1-11 — SSR i granice hostova

Datum: 7. listopada 2026. Status: dovršeno za opseg M1-11.

- Zajednički javni metadata ugovor i Vue Head za oba Home/About dizajna:
  hrvatski jezik dokumenta, tenant naslov bez naziva platforme, javni opis
  ili neutralni HR fallback, canonical i robots. Blade fallback koristi isti
  ugovor i upravljane oznake bez duplikata pri Inertia navigaciji.
- Canonical dolazi iz aktivne verificirane primarne tenant domene i imenovane
  rute; shema/port iz postojeće konfiguracije, u produkciji HTTPS. Ne koristi
  ulazni port, forwarded zaglavlja ni query. Bez pouzdane primarne domene
  dopušteni alias ostaje dostupan bez canonicala, uz noindex.
- `PUBLIC_INDEXING=false` zadano; samo eksplicitno uključena produkcija na
  primarnoj nedemo domeni može biti indexable. Alias, local/staging i `.test`
  ostaju noindex. Nema novih ovisnosti, migracija ni izmjena razvojnih podataka.
- Pad SSR-a daje client-rendered shell uz server metadata i sigurni Laravel
  warning samo s kategorijom greške. README razlikuje Vite/built SSR transport,
  dokumentira pokretanje, live testove i ograničenje fallbacka bez JavaScripta.

Provjere:

- `SSR_TEST_URL=http://127.0.0.1:13714` uz
  `php artisan test --compact tests/Feature/PublicStudioSsrTest.php tests/Feature/PublicStudioTest.php tests/Feature/TenantResolutionTest.php`:
  **53 prolazi, 522 assertiona**, zasebni MySQL i stvarni Node SSR.
- Novi SEO/SSR testovi: 17; uključuju ignoriranje krivotvorenog origin inputa,
  noindex uvjete, nevaljane primarne domene, fallback, sanitizirane logove,
  šest Lotus → Balance → Lotus renderiranja i escaping javnog HTML sadržaja.
  Bez `SSR_TEST_URL` dva live testa eksplicitno se preskaču.
- Pint `--dirty --format agent`, PHPStan, Vue TypeScript, `npm run check:fix`
  i client/SSR build prolaze. Vite izvan sandboxa zbog `spawn EPERM`; ranija
  font/sourcemap upozorenja ostaju, bez nadogradnje paketa.
- Chrome: četiri izravne SSR stranice, Home/About navigacija, ispravan HR
  sadržaj i metadata, bez zabilježenih hydration warn/error poruka. Nakon
  stvarnog gašenja Nodea rade client prikaz i navigacija bez duplih metadata.
  [Detalji pregleda](vizualni-pregled-m1.md#m1-11--ssr-metadata-i-fallback-7-listopada-2026).

Ograničenja: puna zbirka nije ponovno pokrenuta; raniji admin pregledi i
Safari/Firefox ostaju otvoreni. Bez SSR-a i bez JavaScripta nema javnog body
sadržaja, samo metadata; fallback nije zamjena za SSR SEO. Nema deploya.
Privremeni QA procesi/router uklonjeni; postojeći dev procesi i `public/hot`
sačuvani. Sljedeći korak: M1-12 — idempotentan demo seed i lokalne domene.
Za širu integracijsku provjeru pokrenuti `php artisan test --compact`.

## M1-12 — demo seed i lokalne domene

Datum: 7. listopada 2026. Status: implementirano i provjereno na postojećoj
lokalnoj instalaciji i praznoj testnoj MySQL bazi; proxy i puni svježi checkout
ostaju neprovjereni.

- Eksplicitni `LocalDemoSeeder` nadopunjuje osnovni seed: dva različita demo
  profila, verificirane domene, owner/manager/instructor/customer u oba studija
  i zaseban platform administrator. Customer email jednak je u oba studija,
  lozinke različite. Kontakti/adrese jasno su demo, bez stvarnog telefona.
- Dopušten samo `local`/`testing`, čak i uz `--force`. Novi računi imaju
  verificiran email, ali owner/platform moraju proći stvarni TOTP enrolment.
  Nema ugrađenih 2FA tajni, produkcijskih poziva niti slanja emailova.
- Transakcija, normalizirani email lookup i tenant kontekst: ponavljanje čuva
  postojeće lozinke, 2FA, deaktivaciju i uređena polja profila. Dopunjuju se
  samo null demo polja. Konflikt domene ili uloge prekida cijeli seed;
  kontekst se čisti i nakon iznimke. Zadani seeder ostaje prazan.
- README: naredba nakon `composer setup`, svih devet demo pristupa, 2FA koraci,
  hosts upute, dijagnostika i opcionalna Windows Nginx konfiguracija na 8080
  prema istoj PHP aplikaciji na 8000. Nema novih paketa, migracija ni deploya.

Izvršene provjere:

- `php artisan test --compact tests/Feature/LocalDemoSeederTest.php`:
  **11 prolazi, 109 assertiona** na odvojenom MySQL-u. Obuhvaćeni idempotentnost,
  nadogradnja osnovnog seeda, očuvanje postojećih zapisa, produkcija/staging,
  rollback i cleanup, javni payloadi, customer izolacija te stvarna prijava,
  TOTP enrolment i dashboard oba ownera i platform administratora.
- LocalDemoSeeder, TenantFoundation, TenantAuthentication i RoleAndTwoFactor:
  **79 prolazi, 603 assertiona**. Pint `--dirty --format agent` i
  `composer types:check` prolaze. Puna zbirka nije ponavljana.
- `php artisan db:seed --class=LocalDemoSeeder --no-interaction` dvaput na
  razvojnoj bazi: oba prolaza uspješna. Postojeći korisnikovi računi i studiji
  sačuvani. Hosts zapis zatečen i pročitan, nije mijenjan. Stvarni HTTP zahtjevi
  bez `--resolve`: obje naslovnice i platform login na portu 8000 vraćaju 200.
- Chrome: Home/About obaju studija s novim demo profilima pregledani na 375 px,
  bez horizontalnog overflowa, hrvatski sadržaj i različiti kontakti uredni.
  Inertia navigacija radi; uzorak warn/error zapisa prazan. Viewport resetiran.
- Pregled je otkrio zastarjeli `public/hot` prema ugašenom Viteu na 5174.
  Pokrenut `npm run dev -- --port 5174 --strictPort`; nakon reloada prikazi rade.
  Sandbox blokira Node spawn (EPERM), pokretanje izvan sandboxa uspješno.
  Vite ostavljen pokrenut za nastavak lokalnog rada. Drugi procesi nisu gašeni.

Ograničenja: Nginx nije dostupan u PATH-u, pa dokumentirani opcionalni proxy
nije pokrenut niti je izvršen `nginx -t`. Nije dodana nova sistemska ovisnost.
Nema novog client/SSR builda jer frontend kod nije mijenjan; browser koristi
Vite. Admin enrolment potvrđen je automatiziranim HTTP testovima, bez promjene
2FA postojećih browser računa. Raniji otvoreni vizualni prihvati ostaju otvoreni.

Sljedeći konkretan korak: M1-13 — integracijska provjera i CI, uključujući
svježi checkout; prije zatvaranja proxy provjere pripremiti zasebni lokalni
Nginx prema README-u. Za cijelu zbirku pokrenuti `php artisan test --compact`.

## M1-13 — integracijska provjera i CI

Datum: 7. listopada 2026. Status: CI implementiran i integracijske provjere
lokalno prolaze; izvršavanje novog workflowa na GitHub runneru ostaje otvoreno.

- Dopunjen postojeći `.github/workflows/tests.yml`: PHP 8.5 s potrebnim
  ekstenzijama, Node 24, vremensko ograničenje, zaključane ovisnosti i postojeći
  `composer setup` za migracije na MySQL-u te client/SSR build. Dodane provjere
  Composer manifesta/platforme, dvostruki demo seed i Mailpit readiness.
- CI pokreće stvarni izgrađeni Node SSR, provjerava health i vlastiti PID te
  postavlja `SSR_TEST_URL` prije `composer ci:check`. Oba live SSR testa sada
  ulaze u CI; greška renderiranja ne prihvaća se kao client fallback. Proces
  se čisti preko Bash trapa, a Compose servisi u završnom `always()` koraku.
- Read-only GitHub ovlasti i postojeći SHA pinovi ostaju. Nema produkcijskih
  tajni, deploya ni vanjskog SMTP-a. Array mail je zadani transport; lokalni
  SMTP test koristi samo Mailpit i jedinstvene adrese `example.test`.
- Pregledana pokrivenost svih obveznih tokova; README sadrži mapu postojećih
  testova i naredbe za lokalno ponavljanje. Nisu dodavani duplicirani testovi,
  ovisnosti ni promjene poslovnog ponašanja.

Izvršene provjere:

- `SSR_TEST_URL=http://127.0.0.1:13714` uz `php artisan test --compact`:
  **273 prolazi, 12 ranije preskočenih, 2088 assertiona**, 83,7 sekundi.
  Stvarni MySQL 8.4.11, lokalni Mailpit i isti Node SSR proces; uključeni su
  DB constraintovi, auth granice, uloge/2FA, osoblje, javni profil, queue worker
  nakon iznimke i Lotus → Balance → Lotus SSR izolacija.
- `composer validate --strict --no-check-publish`, `composer check-platform-reqs`,
  `composer lint:check`, `composer types:check`, `npm run types:check`,
  `npm run check` i `npm run build`: prolaze. Build zadržava poznata upozorenja
  za opcionalni Fontaine i Inertia sourcemape. Vite je izvršen izvan sandboxa
  nakon `spawn EPERM`; ovisnosti nisu mijenjane.
- Workflow je uspješno parsiran postojećim Symfony YAML parserom; svi `run`
  blokovi prošli su Git Bash `-n`. Mailpit `/readyz` i SSR `/health` vraćaju
  uspjeh. To nije zamjena za izvršavanje na GitHub Actions runneru.
- Vizualni kriterij preuzet je iz dokumentiranog ručnog pregleda M1-10:
  Home/About oba dizajna na 375/768/1440 px, tipkovnica i reduced-motion,
  sa sačuvanim snimkama. Layout i animacije nisu mijenjani niti se ovdje
  tvrdi da je izvršen novi browser pregled.
- Čista kopija iz `git archive HEAD` bez postojećih ovisnosti: `npm ci
--no-audit --no-fund` uspješan (198 paketa). `composer install
--no-interaction --prefer-dist` raspakirao je 154 zaključana paketa, ali
  ostao na `Generating optimized autoload files` dulje od deset minuta.
  Prekinut je i dijagnostički `composer dump-autoload --no-interaction
--profile -vvv` nakon ponovljenog zadržavanja u istoj fazi. Uzrok nije
  utvrđen; to se ne bilježi kao uspješna Composer instalacija. Fresh build,
  migracije i demo seed te kopije nisu izvršeni. Privremeni MySQL na 13309
  nije korišten za upis podataka; razvojne baze i `.env` nisu mijenjani.

Otvoreno: puni svježi setup i GitHub run nakon slanja izmjena. Dvanaest starih single-tenant
scaffold testova u `Settings/*` i `DashboardTest.php` ostaje isključeno;
aktivni tenant testovi pokrivaju M1 dashboard i sigurnosne granice. Raniji
admin vizualni prihvati, opcionalni Nginx i Safari/Firefox nisu zatvoreni.
Nema commita, pusha ni deploya u ovom zadatku.
Privremena kopija, njezin MySQL kontejner i vlastiti Node SSR uklonjeni/ugašeni;
postojeći lokalni servisi ostaju pokrenuti.

Sljedeći korak: potvrditi izmijenjeni workflow na GitHubu, zatim M1-14 —
demonstracija i predaja uz eksplicitan popis preostalih prihvatnih stavki.
Punu lokalnu zbirku moguće je ponoviti s `php artisan test --compact`;
za oba live SSR testa zadržati pokrenut Node i postaviti `SSR_TEST_URL`.
