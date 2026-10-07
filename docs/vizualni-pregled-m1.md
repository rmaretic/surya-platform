# Vizualni pregled M1-01–M1-07

Datum: 6. listopada 2026.
Okruženje: lokalni `composer run dev`, Chrome povezan preko browser ekstenzije.
Status: **djelomično izvršeno; pregledani platform administracija i studio račun s aktivnim 2FA**.

## Metoda i granice

- Pregled dostupnih stvarnih stranica u pregledniku, screenshotovi i mjerenje
  širine dokumenta na **375 × 900, 768 × 900 i 1440 × 900 px**.
- Tamna tema postojećeg preglednika. Svijetla tema i fizički mobilni uređaj
  nisu provjereni. Na kraju je uklonjena privremena viewport postavka.
- Pregledani su reprezentativni screenshotovi; za svaku od osam stranica
  spremljene su sve tri širine i provjeren horizontalni overflow.
- Tab navigacija, Enter na registracijskoj poveznici, vidljivi focus ring i
  native validacija praznog registracijskog obrasca provjereni su na stvarnom UI-ju.
- U prvom prolazu samplirani browser warn/error zapisi bili su prazni.
  U nastavku je na platform potvrdi lozinke zabilježen hydration mismatch
  oznake polja (`Label htmlFor="password"`, server `for="true"`, klijent bez `for`).
  Nalaz je ispravljen 6. listopada zamjenom `htmlFor` s `for="password"`.
  Ponovno izravno otvaranje SSR stranice u Chromeu: `data-server-rendered="true"`,
  bez warn/error zapisa; klik oznake fokusira polje `password`.
- Reset obrazac otvoren je s **izmišljenim placeholder tokenom**. Provjeren je
  izgled, bez slanja obrasca ili izmjene lozinke. Email/registracijski tokovi
  i osjetljive promjene nisu izvršeni tijekom ovog pregleda.
- Ovaj zapis ne zamjenjuje ranije backend testove. Nova zbirka testova/build
  nije pokretana jer je zadatak vizualni pregled, bez izmjena aplikacijskog koda.

## Rezultati po zadatku

| Zadatak | Što je pregledano                                                                                                                                  | Rezultat / što ostaje                                                                                                                                                                                                                                                                                                                                                  |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| M1-01   | Neutralna početna stranica na platform, Lotus i Balance hostu; tri širine                                                                          | Dostupan i čitljiv sadržaj; bez horizontalnog overflowa. Vizualni dio neutralne naslovnice provjeren. Svježi checkout ostaje zasebna otvorena stavka.                                                                                                                                                                                                                  |
| M1-02   | Nema zasebnog novog poslovnog ekrana                                                                                                               | Podatkovni model nema dodatni vizualni kriterij; backend rezultati ostaju u progressu.                                                                                                                                                                                                                                                                                 |
| M1-03   | Nepoznat host (`127.0.0.1`) i platform putanja na studio hostu                                                                                     | U oba slučaja neutralni tekst `Not found.` bez studijskih podataka. Izgled 503 za neaktivni studio nije provjeren. HTTP status se ovim vizualnim pregledom nije ponovno mjerio.                                                                                                                                                                                        |
| M1-04   | Nema zasebnih produkcijskih ekrana                                                                                                                 | Scope, policies, cache i jobovi provjereni su ranijim backend testovima; nema dodatnog dostupnog UI-ja za ovaj zadatak.                                                                                                                                                                                                                                                |
| M1-05   | Studio prijava, registracija, zaboravljena lozinka, prikaz reset obrasca; platform email verifikacija                                              | Prikaz i tri širine prolaze. Tipkovnica/fokus i native required validacija prolaze u provjerenom uzorku. Platform login/reset nakon prijave, studio verifikacija i završetak email tokova ostaju neprovjereni u ovom prolazu.                                                                                                                                          |
| M1-06   | Platform sigurnost i potvrda lozinke; platform/studio račun, studio sigurnost, OTP challenge i recovery prijava na tri širine                      | Raspored čitljiv, bez overflowa. Prebacivanje između challenge obrazaca radi tipkovnicom; prazan recovery submit zaustavlja validacija. Studio sigurnost nudi isključivanje 2FA, platform sigurnost prikazuje obveznu zaštitu. Hydration potvrde lozinke ispravljen. Enrolment, QR, prikaz izdanih recovery kodova i poruke nakon nevaljanog koda ostaju neprovjereni. |
| M1-07   | Registar, kreiranje, detalji Lotus/Balance i testnog studija, pretraga, pending verifikacija, pripremljena pozivnica, suspenzija i popunjeni audit | Raspored prolazi u pregledanom uzorku. Testni studio kreiran i suspendiran kroz UI; potvrđeni poruka uspjeha i audit. Pregledane native i server validacije. Nalaz: engleska poruka za nevaljan format reference, bez upute o formatu. Aktivacija/verifikacija, slanje/prihvat poziva i višestranična paginacija ostaju neprovjereni.                                  |

Sve tri javne naslovnice trenutačno namjerno prikazuju isti neutralni tekst.
Dva zasebna dizajna još nisu dio implementiranog opsega: M1-09/M1-10 nisu
obuhvaćeni ovim pregledom.

## Zapažanja

1. **Jezik auth sučelja:** prijava, registracija, forgot/reset password i
   email verifikacija prikazuju engleske naslove, oznake i gumbe. Preporuka
   je uskladiti ih s hrvatskim sučeljem prije završnog prihvata milestonea.
   Tijekom ovog zadatka tekstovi nisu mijenjani.
2. **Mobilni raspored:** na pregledanim obrascima polja i gumbi ostaju unutar
   375 px, a sadržaj je čitljiv. Na svih 45 mjerenja (24 javna/auth + 21
   zaštićeno) `scrollWidth <= innerWidth`. Dugi admin obrasci zahtijevaju
   normalno okomito pomicanje; pregledani su i donji dijelovi.
3. **Tipkovnica:** login kontrole i poveznice mogu se dosegnuti Tabom;
   registracijska poveznica aktivirana je Enterom. Email polje registracije
   ima vidljivi focus ring. Prazan submit fokusira obvezno polje `name`.
   Cjelovit audit pristupačnosti i screen reader nisu provedeni.
4. Browser veza sada radi. Raniju prepreku činio je nepovezan preglednik,
   a nakon povezivanja nedostupan lokalni server; pokretanjem dev procesa
   omogućeno je otvaranje stvarnih stranica.

## Dokazi

Spremljeno je **75 PNG snimki** u [visual-qa-m1](visual-qa-m1/), bez lozinki,
QR ključeva, recovery kodova ili stvarnih reset/pozivnih tokena.
Mjerenja su u [measurements.json](visual-qa-m1/measurements.json) i
[protected-measurements.json](visual-qa-m1/protected-measurements.json).
Studio mjerenja su u [studio-measurements.json](visual-qa-m1/studio-measurements.json).
Challenge mjerenja su u [challenge-measurements.json](visual-qa-m1/challenge-measurements.json).

| Stranica                       | 375 px                                                | 768 px                                                | 1440 px                                                |
| ------------------------------ | ----------------------------------------------------- | ----------------------------------------------------- | ------------------------------------------------------ |
| Lotus naslovnica               | [PNG](visual-qa-m1/lotus-home-375.png)                | [PNG](visual-qa-m1/lotus-home-768.png)                | [PNG](visual-qa-m1/lotus-home-1440.png)                |
| Balance naslovnica             | [PNG](visual-qa-m1/balance-home-375.png)              | [PNG](visual-qa-m1/balance-home-768.png)              | [PNG](visual-qa-m1/balance-home-1440.png)              |
| Platform naslovnica            | [PNG](visual-qa-m1/platform-home-375.png)             | [PNG](visual-qa-m1/platform-home-768.png)             | [PNG](visual-qa-m1/platform-home-1440.png)             |
| Studio prijava                 | [PNG](visual-qa-m1/studio-login-375.png)              | [PNG](visual-qa-m1/studio-login-768.png)              | [PNG](visual-qa-m1/studio-login-1440.png)              |
| Registracija                   | [PNG](visual-qa-m1/studio-register-375.png)           | [PNG](visual-qa-m1/studio-register-768.png)           | [PNG](visual-qa-m1/studio-register-1440.png)           |
| Zaboravljena lozinka           | [PNG](visual-qa-m1/studio-forgot-375.png)             | [PNG](visual-qa-m1/studio-forgot-768.png)             | [PNG](visual-qa-m1/studio-forgot-1440.png)             |
| Reset obrazac                  | [PNG](visual-qa-m1/studio-reset-375.png)              | [PNG](visual-qa-m1/studio-reset-768.png)              | [PNG](visual-qa-m1/studio-reset-1440.png)              |
| Platform email verifikacija    | [PNG](visual-qa-m1/platform-verify-375.png)           | [PNG](visual-qa-m1/platform-verify-768.png)           | [PNG](visual-qa-m1/platform-verify-1440.png)           |
| Registar studija               | [PNG](visual-qa-m1/admin-register-375.png)            | [PNG](visual-qa-m1/admin-register-768.png)            | [PNG](visual-qa-m1/admin-register-1440.png)            |
| Novi studio                    | [PNG](visual-qa-m1/admin-create-375.png)              | [PNG](visual-qa-m1/admin-create-768.png)              | [PNG](visual-qa-m1/admin-create-1440.png)              |
| Detalj Lotus                   | [PNG](visual-qa-m1/admin-lotus-375.png)               | [PNG](visual-qa-m1/admin-lotus-768.png)               | [PNG](visual-qa-m1/admin-lotus-1440.png)               |
| Detalj Balance                 | [PNG](visual-qa-m1/admin-balance-375.png)             | [PNG](visual-qa-m1/admin-balance-768.png)             | [PNG](visual-qa-m1/admin-balance-1440.png)             |
| Sigurnost platform računa      | [PNG](visual-qa-m1/platform-security-375.png)         | [PNG](visual-qa-m1/platform-security-768.png)         | [PNG](visual-qa-m1/platform-security-1440.png)         |
| Potvrda lozinke                | [PNG](visual-qa-m1/platform-confirm-password-375.png) | [PNG](visual-qa-m1/platform-confirm-password-768.png) | [PNG](visual-qa-m1/platform-confirm-password-1440.png) |
| Prazna pretraga                | [PNG](visual-qa-m1/admin-empty-375.png)               | [PNG](visual-qa-m1/admin-empty-768.png)               | [PNG](visual-qa-m1/admin-empty-1440.png)               |
| Studio sigurnost — aktivan 2FA | [PNG](visual-qa-m1/studio-security-375.png)           | [PNG](visual-qa-m1/studio-security-768.png)           | [PNG](visual-qa-m1/studio-security-1440.png)           |
| Studio 2FA challenge           | [PNG](visual-qa-m1/studio-challenge-375.png)          | [PNG](visual-qa-m1/studio-challenge-768.png)          | [PNG](visual-qa-m1/studio-challenge-1440.png)          |
| Studio recovery prijava        | [PNG](visual-qa-m1/studio-recovery-login-375.png)     | [PNG](visual-qa-m1/studio-recovery-login-768.png)     | [PNG](visual-qa-m1/studio-recovery-login-1440.png)     |

Donji dijelovi admin obrazaca spremljeni su kao `admin-create-bottom-*`,
`admin-lotus-bottom-*` i `admin-balance-bottom-*` za sve tri širine.

Dodatno: [fokus tipkovnice](visual-qa-m1/keyboard-focus-register-1440.png),
[platform putanja na studio domeni](visual-qa-m1/studio-platform-404-1440.png),
[nepoznat host](visual-qa-m1/unknown-host-404-1440.png).
Jedno full-page snimanje email verifikacije isteklo je; ponovljeno snimanje
viewporta uspjelo je. Ta tehnička poteškoća ne označava grešku aplikacije.

## Sljedeći prolaz

Admin razvojni fixture, 6. listopada 2026.:

- Kroz UI kreiran studio #3 „Vizualni QA — razvojni studio”, s dugim opisom,
  primarnom pending domenom `visual-qa.yoga.test` i pripremljenom pozivnicom
  za `visual-qa@example.test`. Email nije poslan, owner račun nije kreiran.
- Pending detalj pregledan na tri širine bez overflowa; dostupni obrazac
  verifikacije, pripremljena pozivnica i audit `tenant.created`. Prazna
  verifikacija fokusira obveznu referencu.
- Nevaljana referenca suspenzije s dugom crtom prikazuje inline server
  grešku „The reference field format is invalid.”. Zapažanje: engleska
  poruka i nedostatak upute o dopuštenom formatu reference u hrvatskom UI-ju.
- Ispravna referenca `VISUAL-QA-20261006` i potvrda suspenzije daju status
  „Suspendiran”, poruku da su podaci sačuvani i audit `tenant.suspended`.
  Mobilni audit/pozivnica čitljivi, bez overflowa. Konzola bez warn/error zapisa.
- Testni studio ostaje **suspendiran**, njegova domena neverificirana i
  pozivnica neposlana. Lotus i Balance nisu mijenjani. Nema brisanja podataka.
- Devet novih snimki: `admin-pending-domain-*`, `admin-pending-audit-*`,
  [nevaljana referenca](visual-qa-m1/admin-suspend-invalid.png),
  [uspješna suspenzija](visual-qa-m1/admin-suspended-1440.png) i
  [mobilni audit](visual-qa-m1/admin-suspended-audit-375.png).
  Mjerenja: [admin-fixture-measurements.json](visual-qa-m1/admin-fixture-measurements.json).
- Preostaju stvarna verifikacija/aktivacija i slanje/prihvat poziva: testna
  domena nema pripremljen hosts/DNS zapis pa kontrola infrastrukture nije
  potvrđena. Višestranična paginacija, QR/enrolment i prikaz izdanih recovery
  kodova također ostaju otvoreni. Appearance je namjerno blokiran middlewareom
  do M1-08; svijetla tema nije dostupna kroz trenutačni UI.

Pregled challengea 6. listopada 2026., nakon što je korisnik stao prije koda:

- Studio OTP i recovery obrazac pregledani na 375/768/1440 px; svih šest
  mjerenja bez overflowa. Naslovi, upute, polja i gumbi čitljivi.
- Prebacivanje OTP → recovery → OTP radi Enterom; recovery polje pri ulasku
  dobiva fokus. Prazan recovery submit zaustavlja native required validacija.
- Browser warn/error uzorak prazan. Spremljeno šest snimki praznih obrazaca,
  bez stvarnih kodova. Vraćen OTP ekran za korisnikov nastavak prijave.
- M1-06 challenge i recovery **obrazac prijave** sada su vizualno pregledani.
  Preostaju QR/enrolment, prikaz izdanih recovery kodova i poruke nakon
  nevaljanog koda; prijava stvarnim kodom nije izvršena u ovom pregledu.

Dodatne provjere 6. listopada 2026.:

- Prazan submit suspenzije zaustavljen je native validacijom i fokusira
  `suspension-reference`. Prazno dodavanje domene fokusira obvezni `hostname`.
  Nije došlo do promjene studija/domene; browser warn/error uzorak prazan.
- Studio `/settings/appearance` prikazuje neutralno „Prijava i administracija
  su u pripremi.”, bez kontrole teme. Svijetla tema ostaje neprovjerena.
- Za vizualni pregled challengea korisniku je zatraženo da ponovi prijavu i
  stane prije unosa 2FA koda. Challenge trenutačno nije otvoren u pregledniku.
  Pregled čeka to stanje; sigurnosne postavke nisu mijenjane radi pregleda.

Nastavak nakon korisnikove studio prijave, 6. listopada 2026.:

- Lotus račun i sigurnost s aktivnim 2FA pregledani na tri širine; šest
  dodatnih mjerenja bez overflowa. Račun prikazuje jasan opis budućih
  funkcionalnosti i poveznicu na sigurnost. Navigacija radi.
- Studio sigurnost je dostupna u trenutačnoj sesiji; recovery kodovi su
  skriveni. Isključivanje 2FA i obnova kodova nisu izvršeni.
- Browser warn/error uzorak ovog taba prazan. Dodane tri snimke sigurnosti;
  osobni podaci računa nisu spremljeni na snimkama u repozitorij.
- Studio sesija više nije prepreka. Preostaju enrolment/QR, challenge,
  recovery prikaz, svijetla tema i admin scenariji s dodatnim razvojnim podacima.

Dodatni nastavak 6. listopada 2026.:

- Platform račun pregledan uživo na 375/768/1440 px: čitljiv raspored bez
  horizontalnog overflowa (tri dodatna mjerenja). Snimke s osobnim podacima
  nisu spremljene u repozitorij. Poveznica na administraciju radi.
- Tipkovnicom dosegnuta vidljiva poveznica „Preskoči na sadržaj”; Enter
  postavlja `#main-content`. Programski fokus glavnog sadržaja nije potvrđen.
- Pretraga domene `balance.yoga.test`, poslana Enterom, vraća samo Balance
  i jedan rezultat. Privremeni viewport ponovno uklonjen.
- Lotus `/account` i dalje preusmjerava na studio prijavu. Otvoren je zaseban
  tab za korisnikovu prijavu; platform sesija ne omogućuje pregled studio računa.
- Za pending/verifikaciju domene, pozivnicu, popunjeni audit, suspenziju i
  višestraničnu paginaciju potrebni su dodatni razvojni podaci. Trenutačni
  registar sadrži dva aktivna studija. Ti scenariji nisu označeni pregledanima.
- Svijetla tema i detaljni scenariji iz popisa ispod ostaju otvoreni.

Raniji ponovni pokušaj 6. listopada 2026.: lokalna aplikacija i Chrome rade, ali
`/platform/tenants` preusmjerava na platform prijavu, a Lotus `/account` na
studio prijavu. Trenutačno nema aktivne prijavljene sesije za ove preglede.
Zaštićeni ekrani nisu označeni provjerenima; platform prijava ostavljena je
otvorena za korisnika. Nakon prijave i 2FA može se nastaviti ovaj popis.

Nastavak nakon korisnikove prijave s 2FA: platform pregled gore je izvršen.
Postojeći studiji i sigurnosne postavke nisu mijenjani; viewport je resetiran.

1. Hydration nalaz zatvoren: `npm run types:check` i client/SSR `npm run build`
   prolaze. Build izvršen izvan sandboxa zbog `spawn EPERM`, uz postojeća
   font/sourcemap upozorenja. Backend testovi nisu ponavljani za atribut oznake.
2. Studio račun i aktivna 2FA sigurnost pregledani; za preostale enrolment/QR
   i challenge scenarije pripremiti zaseban razvojni račun i tijek prijave.
3. Provjeriti prazne/pogrešne ulaze, duge tekstove, uspješne i neuspješne
   statuse, rad tipkovnicom te svijetlu temu za zaštićene ekrane.
4. Po dovršetku ažurirati ovaj izvještaj i tek tada zatvoriti odgovarajuće
   vizualne stavke M1-05/M1-06/M1-07. Njihov puni vizualni prihvat ostaje otvoren.

## M1-08 — djelomični pregled, 6. listopada 2026.

- Pregledan novi portal polaznika na 375 × 812, 768 × 1024 i 1440 × 1000 px.
  `scrollWidth` jednak je širini viewporta u sva tri mjerenja. Prikaz sadrži
  vlastiti profil, identitet studija, sigurnost/2FA i poruku o budućim
  rezervacijama; nema owner navigacije ni booking/naplata kontrola.
- Neutralna javna Lotus stranica pregledana na 375 × 812 px bez overflowa.
- Pregled otkrio neispravne hrvatske znakove u novim Vue datotekama. Ispravljeni
  su, ponovljen client/SSR build i pregled portala; znakovi se prikazuju uredno.
- Trenutačni browser warn/error uzorak prazan. Viewport resetiran. Nisu
  mijenjani razvojni računi, javni kontakt ni sigurnosne postavke.
- Backend: 21 novi test prolazi, puna MySQL zbirka 232 prolazi / 12 ranije
  preskočenih. Četiri nova ekrana uspješno renderirana kroz SSR proces.
- Otvoreno: owner dashboard, profil i osoblje te UI pozivnica, svijetla tema,
  tipkovnica i validacijska stanja tih ekrana. Otvorena studio sesija je
  customer; zatražena owner prijava. Ovaj zapis ne potvrđuje puni vizualni prihvat.

## M1-09 — osnovni javni dizajni, 6. listopada 2026.

Obje Home/About stranice pregledane na 375 × 812 px. Dodatno pregledani Lotus
About i Balance Home/About na 1440 × 1000 px. Šest DOM mjerenja potvrđuje da
scrollWidth ne prelazi viewport. Lotus koristi vlastiti svijetli layout,
Balance vlastiti tamni; ovo su temelji za M1-10, bez medija i animacija.

Navigacija Home → About radi klikom za Lotus i Enterom za Balance. Aktivna
stranica označena je u navigaciji. Browser warn/error uzorci prazni. DOM popis
stylesheetova pokazuje samo vlastiti CSS dizajna uz zajedničke app stilove.
Build manifest dodatno potvrđuje odvojene dinamičke JS ulaze i statičke ovisnosti.

Početni HTTP HTML sadrži renderirane naslove kroz isti SSR proces. Nisu
mijenjani razvojni profili niti računi. Viewport vraćen na početnu veličinu.
Puni prikazi s popunjenim sadržajem, tri širine i reduced motion ostaju M1-10;
ranije otvoreni owner scenariji M1-08 nisu dio ovog pregleda.

## M1-10 — javni demo dizajni, 7. listopada 2026.

Status: vizualni prihvat M1-10 dovršen u Chromiumu. Pregledane četiri stranice
na 375 × 1000, 768 × 1000 i 1440 × 1000 px. Nema horizontalnog overflowa,
prekrivenih kontrola ni nečitljivog sadržaja. Screenshot obuhvaća cijelu
stranicu; Chromium iz slike izostavlja scrollbar od 15 px kada postoji.

| Stranica           | 375 px                                             | 768 px                                             | 1440 px                                             |
| ------------------ | -------------------------------------------------- | -------------------------------------------------- | --------------------------------------------------- |
| Lotus naslovnica   | [Snimka](visual-qa-m1/m1-10-lotus-home-375.png)    | [Snimka](visual-qa-m1/m1-10-lotus-home-768.png)    | [Snimka](visual-qa-m1/m1-10-lotus-home-1440.png)    |
| Lotus o studiju    | [Snimka](visual-qa-m1/m1-10-lotus-about-375.png)   | [Snimka](visual-qa-m1/m1-10-lotus-about-768.png)   | [Snimka](visual-qa-m1/m1-10-lotus-about-1440.png)   |
| Balance naslovnica | [Snimka](visual-qa-m1/m1-10-balance-home-375.png)  | [Snimka](visual-qa-m1/m1-10-balance-home-768.png)  | [Snimka](visual-qa-m1/m1-10-balance-home-1440.png)  |
| Balance o studiju  | [Snimka](visual-qa-m1/m1-10-balance-about-375.png) | [Snimka](visual-qa-m1/m1-10-balance-about-768.png) | [Snimka](visual-qa-m1/m1-10-balance-about-1440.png) |

Lotus kombinira serif, tople tonove, organsku ilustraciju i asimetrični hero.
Balance koristi kontrastni tamni identitet, veliki sans naslov, široku
geometrijsku ilustraciju i numerirane sekcije. Lokalni SVG-ovi su izvorni
projektni demo mediji označeni CC0-1.0. Tijekom pregleda uklonjen je znak koji
je Windows renderirao kao obojeni emoji; zamijenjen je kontroliranim SVG-om.

Razvojni profili imaju samo naziv. Zato je dodatno pregledan kompletan kontakt
na sve tri širine pomoću presretanja Inertia odgovora samo u QA pregledniku.
Demo opis, izmišljena adresa/telefon i `example.test` email jasno su testni;
nisu spremljeni u bazu. Dugi email i višeredna adresa ne stvaraju overflow.

| Popunjeni demo prikaz | 375 px                                                  | 1440 px                                                  |
| --------------------- | ------------------------------------------------------- | -------------------------------------------------------- |
| Lotus naslovnica      | [Snimka](visual-qa-m1/m1-10-lotus-home-demo-375.png)    | [Snimka](visual-qa-m1/m1-10-lotus-home-demo-1440.png)    |
| Lotus o studiju       | [Snimka](visual-qa-m1/m1-10-lotus-about-demo-375.png)   | [Snimka](visual-qa-m1/m1-10-lotus-about-demo-1440.png)   |
| Balance naslovnica    | [Snimka](visual-qa-m1/m1-10-balance-home-demo-375.png)  | [Snimka](visual-qa-m1/m1-10-balance-home-demo-1440.png)  |
| Balance o studiju     | [Snimka](visual-qa-m1/m1-10-balance-about-demo-375.png) | [Snimka](visual-qa-m1/m1-10-balance-about-demo-1440.png) |

- Tipkovnica na 375 px: Tab doseže preskok, brand i sve navigacijske poveznice
  uz vidljivi outline; Enter na preskoku fokusira `main`, a na Prijava otvara
  stvarnu prijavu odgovarajućeg studija.
- Reduced-motion provjeren na svim stranicama, pri učitavanju i promjeni uživo:
  sadržaj je vidljiv, nema aktivnih animacija, observera ni pomaka dekoracija.
  Snimke: [Lotus](visual-qa-m1/m1-10-lotus-reduced-motion.png),
  [Balance](visual-qa-m1/m1-10-balance-reduced-motion.png).
- Po 20 Home/About Inertia navigacija svakog dizajna: broj praćenih listenera
  ostaje 6 i observera 1; odlaskom na login vraća se na 2 framework listenera,
  0 javnih observera i 0 javnih animacija. Nema rasta resursa kroz ponavljanje.
- JavaScript isključen: četiri SSR stranice i obična navigacija rade; sadržaj
  nije skriven i nema overflowa. Ovo ne zamjenjuje cjeloviti SSR prihvat M1-11.
- Završni Balance ciklus od 20 navigacija nema console warning/error ni
  pageerror zapisa. Raniji nedostupni IPv6 Vite nije korišten za ove snimke;
  pregled je izvršen nad buildom kroz privremeni HTTP/SSR na 8010/13715.
- Provjere: javni MySQL testovi 13/13 (212 assertiona), Vue TypeScript,
  format/lint i client/SSR build prolaze. Nije pokretana cijela zbirka.

Nije provjeren fizički slabiji mobitel niti Safari/Firefox. Prethodno otvoreni
admin/auth scenariji iz M1-05–M1-08 ostaju otvoreni. QA nije mijenjao račune,
profil studija, sigurnosne postavke ni postojeće razvojne procese.

## M1-11 — SSR, metadata i fallback (7. listopada 2026.)

Status: prihvat M1-11 provjeren. Povezani Chrome, izgrađeni client/SSR,
privremeni PHP na 8010 i isti Node proces na 13714; postojeći Vite `public/hot`
sačuvan. Razvojni profili i računi nisu mijenjani.

- Izravno otvorene sve četiri Home/About stranice: vidljiv HR sadržaj,
  `lang=hr`, tenant naslov bez platformskog sufiksa, description, noindex i
  canonical na pripadajuću primarnu domenu. Canonical zadržava konfigurirani
  port 8000 iako je pregled izvršen na 8010.
- Lotus → Balance → Lotus kroz isti SSR proces: ispravan sadržaj i dizajn,
  `data-server-rendered=true`; uzorci browser warn/error logova prazni.
- Home → About Inertia navigacija oba dizajna mijenja naslov i canonical;
  nema duplih title/description/canonical oznaka. Izravni About reload
  također nema zabilježenih hydration grešaka.
- Nakon gašenja vlastitog Node procesa, Lotus Home i Balance About ostaju
  vidljivi kroz client rendering, bez `data-server-rendered` oznake i bez
  browser warn/error zapisa. Lotus Home → About navigacija radi i metadata
  ostaje neduplicirana. Laravel bilježi samo `type=connection` warning.
- Automatizirani live testovi dodatno parsiraju stvarni početni HTML bez
  JavaScripta, provjeravaju javni tekst u `#app`, šest uzastopnih renderiranja,
  tenant metadata/dizajn i escaping naslova/opisa s HTML oznakama.

Nisu ponavljani svi responsive/reduced-motion scenariji iz M1-10 jer layout i
animacije nisu mijenjani. Safari/Firefox i raniji otvoreni admin pregledi
ostaju izvan ove provjere. Nema produkcijskog deploya.
