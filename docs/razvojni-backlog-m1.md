# M1 — prvi funkcionalni dio yoga platforme

Datum: 3. listopada 2026.  
Izvor: [produktna specifikacija](specifikacija-platforme-v1.md), verzija 1.5 i naknadni zahtjev vlasnika.  
Status: razvojni zadaci spremni za implementaciju; aplikacija ovim dokumentom nije implementirana.

## Cilj i granica isporuke

Jedna Laravel + Vue + Inertia aplikacija i zajednička MySQL baza služe dva demo studija. Host određuje studio i njegov frontend. Polaznik i osoblje prijavljuju se na domenu svojeg studija; platform admin koristi odvojenu domenu i identitet. Oba admina imaju funkcionalne početne ekrane i stvarne podatke temeljnog modela.

M1 pokriva dio F01, F02, F03, F36 i temelje iz odjeljaka 4–9, 11, 22, 25–28. Ovo je proširen prvi inkrement koji spaja temelj s minimalnim adminima i dva javna dizajna; nije cijeli opseg A iz glavne specifikacije.

Uključeno: domenski resolver, tenant izolacija, autentikacija, reset/verifikacija emaila u lokalnom inboxu, 2FA za administratore, uloge/policies, osnovni pozivi osoblja, minimalno uređivanje sigurnih podataka studija, audit administrativnih promjena, dva različita javna frontend dizajna i osnovni SSR.

Izvan M1: rezervacije, raspored, krediti, članarine, naplate, fiskalizacija, Meet, videoteka, QR, marketing, Fathom grafovi, integracija SES-a, potpuni CMS s revizijama, import/export, produkcijski onboarding i deployment. Odabrani provideri ostaju SES, Bunny Stream i Fathom; njihove integracije imaju zasebne kasnije zadatke. Ne prikazivati izmišljenu zaradu, rezervacije ili grafove kao stvarne podatke.

## Radne tehničke odluke

- MySQL kao početna baza, u skladu s vlasnikovim ranije navedenim planom; podržanu verziju i kompatibilne verzije frameworka odabrati u M1-01 i zaključati dependency lockovima. Ne preuzimati slijepo najnovije verzije.
- Laravel modularni monolit, bez posebne instalacije po studiju i bez mikroservisa. Vue frontendovi koriste zajedničke poslovne ugovore.
- Lokalne domene: `platform.yoga.test`, `lotus.yoga.test`, `balance.yoga.test`. To su razvojne vrijednosti; nisu kupljene ni produkcijske domene.
- Odvojeni `platform_admins` i tenant-scoped `users`; nema zajedničkog polazničkog računa ili cross-domain SSO-a. Isti email smije postojati u oba studija.
- Studio owner, manager, instructor i customer su tenant uloge. Za M1 korisnik ima jednu ulogu; proizvoljni permission editor i složene kombinacije uloga dolaze kasnije.
- Platform admin upravlja registrom studija i domena. U M1 nema impersonationa, support prijave u tuđi studio niti implicitnog zaobilaženja tenant policies.
- Login, email verifikacija i TOTP 2FA koriste održavane Laravel-kompatibilne komponente. Obvezni 2FA za platform admina i ownera nakon prvog postavljanja; protokol se ne implementira ručno.
- Objavljivi profil studija ima fiksna polja: naziv, kratki opis, email, telefon, adresa. Promjene su odmah javne, uz jasnu oznaku u obrascu; ovo nije puni CMS draft/publish sustav. Bez rich-text HTML-a, uploadova ili uređivanja animacija u M1.
- Javne stranice koriste SSR; admin može koristiti standardni Inertia prikaz. Svaki SSR zahtjev dobiva vlastite podatke i nema globalnog promjenjivog tenant stanja.
- Email u lokalnom/test okruženju ide u lokalni inbox ili testni transport. Produkcijsko slanje nije potrebno za prihvat M1.

## Matrica prava u M1

| Radnja | Platform admin | Owner | Manager | Instructor | Customer |
|---|---|---|---|---|---|
| Popis svih studija i operativni statusi | Da, na platform domeni | Ne | Ne | Ne | Ne |
| Kreiranje studija i dodjela dizajna/domena | Da | Ne | Ne | Ne | Ne |
| Studio dashboard | Ne preko studio sesije | Da | Da | Da, ograničen | Ne |
| Uređivanje javnog profila studija | Ne kroz studio rute | Da | Ne | Ne | Ne |
| Pregled i pozivanje osoblja | Ne kroz studio rute | Da | Ne | Ne | Ne |
| Promjena manager/instructor uloge i deaktivacija osoblja | Ne kroz studio rute | Da | Ne | Ne | Ne |
| Vlastiti profil, lozinka i odjava | Da | Da | Da | Da | Da |
| Minimalni portal polaznika | Ne | Ne | Ne | Ne | Da |

Javna registracija uvijek kreira customer ulogu. Ownera inicijalno poziva platform admin; owner u M1 poziva samo managera ili instructora. Promjena ownera i njegovo brisanje nisu dio M1. Skrivanje gumba nije autorizacija: backend provjerava svaku radnju.

## Redoslijed i ovisnosti

`M1-01 → M1-02 → M1-03 → M1-04 → M1-05 → M1-06 → M1-07 → M1-08 → M1-09 → M1-10 → M1-11 → M1-12 → M1-13 → M1-14`

Redoslijed je preporučeni implementacijski put. Testove pisati uz odgovarajući zadatak; M1-13 je integracijska provjera, a ne prvo testiranje. Nakon svakog zadatka mora postojati pokretljivo stanje ili dokumentiran razvojni fixture. Ne dodavati napredne module radi popunjavanja ekrana.

## M1-01 — repozitorij i lokalno pokretanje

**Ovisnosti:** nema. **Veza:** odjeljci 5, 23, 29.

Pregledati postojeći repozitorij i AGENTS.md prije scaffoldinga. Postaviti podržan Laravel/Vue/Inertia/TypeScript stack, MySQL vezu, frontend build, SSR proces i test runner. Odabrati jedan reproducibilni način lokalnog rada prikladan vlasnikovu računalu; dokumentirati preduvjete. Dodati `.env.example`, bez stvarnih tajni, i kratki zapis odabranih verzija/razloga. Ovo nije izbor produkcijskog orchestration sustava.

**Prihvat:** svjež checkout može instalirati zaključane ovisnosti, podići bazu, migrirati i prikazati neutralnu početnu stranicu. Backend i frontend osnovne provjere prolaze. Nema pretpostavke da je Node development server produkcijski web server.

## M1-02 — podatkovni model temelja

**Ovisnosti:** M1-01. **Veza:** F01, odjeljci 4, 5, 25.

Implementirati `tenants`, `tenant_domains`, `users`, `platform_admins`, `tenant_profiles`, `staff_invitations`, tenant i platform audit zapise te potrebne auth/session/reset tablice. `tenants` sadrži status i ključ dizajna. Domene imaju globalno jedinstven normalizirani hostname, status verifikacije i primarnu domenu. Razvojni verified status dolazi samo iz eksplicitnog lokalnog seeda.

`users` ima obvezan `tenant_id`, normalizirani email, ulogu, aktivnost i podatke verifikacije/2FA. Jedinstvenost je `(tenant_id, normalized_email)`. Tenant relacije imaju constraintove koji sprječavaju povezivanje s korisnikom drugog studija. Platformski identiteti nisu tenant korisnici s posebnim role flagom.

**Prihvat:** migracije prolaze na MySQL-u; isti email radi u različitim studijima, ali ne dvaput u istom. Duplicirani hostname i neispravne tenant relacije odbijaju se. Dokumentirati koje su tablice globalne i zašto.

## M1-03 — domenski resolver i kontekst zahtjeva

**Ovisnosti:** M1-02. **Veza:** F01, AC03, odjeljci 5.1, 22.

Normalizirati hostname i odvojiti platform domenu od domena studija. Prihvaćati samo aktivne verificirane domene aktivnih studija. Nepoznat host vratiti neutralni 404 bez fallbacka na prvi studio. Neaktivni studio vratiti neutralni 503 bez poslovnih podataka. Tenant kontekst postaviti prije auth upita i tenant route bindinga. `tenant_id` iz bodyja, queryja ili proizvoljnog zaglavlja ignorirati kao izvor konteksta. Proxy zaglavlja vjerovati samo eksplicitno konfiguriranim proxyjima.

**Prihvat:** Lotus host rješava Lotus, Balance host Balance, platform host nema implicitnog tenanta. Krivotvoreni forwarded host iz nepouzdanog izvora ne mijenja kontekst. Nije moguće koristiti platform admin rute na domeni studija niti obrnuto.

## M1-04 — zaštita podataka, cachea i jobova

**Ovisnosti:** M1-03. **Veza:** AC01–AC03, odjeljak 5.2.

Dodati tenant-scoped pristup, route binding, policies i serversko dodjeljivanje `tenant_id`. Bez aktivnog konteksta tenant upiti moraju odbiti rad, ne vraćati sve redove. Platformske globalne preglede izvesti kroz eksplicitne autorizirane upite. Cache ključevi uključuju tenant i vrstu podatka. Implementirati tenant-aware job middleware koji dohvaća tenant prema internom ID-ju i čisti kontekst u `finally`, uključujući neuspjeh posla.

**Prihvat:** korisnik Lotusa ne čita niti mijenja Balance profil preko poznatog ID-ja ili manipulirane relacije. Dva uzastopna joba za različite studije koriste odgovarajući kontekst; job koji baci iznimku ne kontaminira sljedeći. Jednako nazvani cache ključevi ne dijele podatke. Testirati s istim dugovječnim workerom, bez vanjskih servisa.

## M1-05 — prijava, registracija i oporavak računa

**Ovisnosti:** M1-04. **Veza:** AC04, odjeljci 4, 5, 11.

Implementirati studio login/logout, customer registraciju, verifikaciju emaila, zaboravljenu lozinku i reset. Platform admin nema javnu registraciju; prvi račun kreira se dokumentiranom lokalnom/admin naredbom. Tenant i platform auth imaju odvojene guardove/providere i reset kontekste. Host-only cookies, CSRF i regeneracija sesije pri prijavi; produkcijski secure cookie postavke odvojene od lokalnog HTTP-a. Sesija sadrži i provjerava tenant vezu, ne oslanja se samo na cookie izolaciju.

Reset/verifikacijske poveznice generirati isključivo za pouzdanu domenu odgovarajućeg identiteta. Rate limit kombinira studio/identitet/IP tako da jedan studio ne može nekritično blokirati drugi. Odgovori na reset ne otkrivaju postojanje računa.

**Prihvat:** isti email s različitim lozinkama u dva studija prijavljuje dva različita računa. Lotus reset token ne radi na Balance domeni. Ručno preneseni session cookie ne daje cross-tenant pristup. Registracija s podmetnutom owner ulogom i tenant ID-jem i dalje kreira customer u trenutnom studiju. Email tokovi rade kroz lokalni inbox.

## M1-06 — uloge i 2FA

**Ovisnosti:** M1-05. **Veza:** F01, odjeljak 4.

**Status (6. listopada 2026.):** implementirano i automatizirano provjereno;
prihvat čeka vizualni pregled. [Testovi](../tests/Feature/RoleAndTwoFactorTest.php),
[demo enrolment](../README.md#uloge-i-2fa--m1-06), [rezultati](progress.md#m1-06--uloge-i-2fa).

Implementirati matricu prava policies/gates slojem. Owner i platform admin nakon prvog logina moraju dovršiti TOTP enrolment prije pristupa administraciji. Omogućiti recovery kodove i potvrdu lozinke za osjetljive promjene. Bez univerzalnog skrivenog bypass koda. Demo enrolment dokumentirati; ne gasiti 2FA u demo aplikaciji samo radi lakšeg prikaza.

**Prihvat:** svaka uloga ima pozitivne i negativne provjere pristupa. Nedovršen 2FA ne daje admin ovlasti. Recovery kod vrijedi jednom, pogrešni kodovi su ograničeni rate limitom. Osjetljiva polja i recovery tajne ne pojavljuju se u Inertia propsima i logovima.

## M1-07 — platform admin

**Ovisnosti:** M1-06. **Veza:** F01, F36, odjeljak 8.

**Status (6. listopada 2026.):** implementirano i automatizirano provjereno;
prihvat čeka vizualni pregled. [Testovi](../tests/Feature/PlatformTenantTest.php),
[operativni postupak](../README.md#platform-administracija--m1-07),
[rezultati](progress.md#m1-07--platform-admin).

Implementirati zaseban layout, popis/pretragu studija, detalj, kreiranje tenanta s profilom i inicijalnom owner pozivnicom. Omogućiti izbor registriranog dizajna te unos domene u pending statusu. Dashboard prikazuje stvaran broj studija i njihove statuse, bez prihoda i lažnih grafova.

Verifikacija domene u M1 je dokumentirani ručni operativni postupak: operator provjeri kontrolu domene/infrastrukturu i tek zatim označi domenu verified uz dokaz/referencu u auditu. To ne konfigurira DNS, Nginx ni certifikat. Omogućiti deaktivaciju demo studija uz opis posljedica i audit; nema hard deletea.

**Prihvat:** novi tenant nastaje atomarno ili ostaje jasno označeno stanje za ponovni pokušaj poziva. Pending domena ne služi javni studio. Dodjela nepoznatog design ključa se odbija. Promjene bilježe autora, cilj, vrijeme i sanitiziranu promjenu, bez tokena i lozinki. Studio osoblje ne može pristupiti platform adminu.

## M1-08 — studio admin i osoblje

**Ovisnosti:** M1-07. **Veza:** F01, dio F03/F36, odjeljci 6.2, 9.

**Status (6. listopada 2026.):** implementirano i automatizirano provjereno;
potpuni prihvat čeka vizualni pregled owner ekrana i pozivnica.
[Testovi](../tests/Feature/StudioAdministrationTest.php),
[postupak](../README.md#studio-administracija-i-osoblje--m1-08),
[rezultati](progress.md#m1-08--studio-admin-i-osoblje).

Implementirati studio layout, identitet aktivnog studija, početni ekran i obrazac javnog profila. Owner upravlja manager/instructor pozivnicama i deaktivacijom; pozivnice su jednokratne, ograničenog trajanja i vezane uz tenant i email. Deaktivacija se provjerava pri svakom zahtjevu, uključujući postojeću sesiju. Manager i instructor imaju ograničen početni ekran prema matrici. Customer dobiva minimalni vlastiti profil i jasnu poruku da booking dolazi kasnije.

**Prihvat:** promjena telefona Lotusa vidljiva je na njegovoj javnoj stranici, ne na Balanceu. Manager ne može izvršiti isti update ni izravnim HTTP zahtjevom. Pozivnica ne radi na drugoj domeni niti drugi put; deaktivirani djelatnik gubi pristup. Ne postoje aktivni gumbi za neimplementiranu naplatu ili booking.

## M1-09 — registar dizajna i javni ugovor podataka

**Ovisnosti:** M1-08. **Veza:** F02/F03, odjeljak 6.

**Status (6. listopada 2026.):** dovršeno za opseg M1-09.
[Testovi](../tests/Feature/PublicStudioTest.php),
[registar i javni ugovor](../README.md#registar-javnih-dizajna--m1-09),
[rezultati](progress.md#m1-09--registar-dizajna-i-javni-ugovor-podataka).
Puni vizualni dizajni ostaju M1-10; otvoreni vizualni prihvat M1-08 nije zatvoren ovim zadatkom.

Implementirati dopuštene ključeve `lotus` i `balance` i njihovo mapiranje na Inertia Home/About komponente. Zajednički javni DTO sadrži samo objavljive podatke; nikada sirovi tenant/user model. Kod dizajna, CSS i animacije izolirani po dizajnu; koristiti lazy učitavanje. Nepostojeći design ključ sigurno prijaviti bez renderiranja tuđeg dizajna ili proizvoljne putanje.

**Prihvat:** promjena dopuštenog ključa mijenja frontend postojećeg studija uz isti backend. Putanja poput `../../PlatformAdmin` nije prihvaćena. U javnom payloadu nema privatnih podataka osoblja ili integracijskih tajni. Build ne učitava oba cijela dizajna za svaki posjet.

## M1-10 — dva vizualno različita demo frontenda

**Ovisnosti:** M1-09. **Veza:** F02, AC27–AC28.

**Status (7. listopada 2026.):** dovršeno za opseg M1-10.
[Implementacija i provjere](progress.md#m1-10--dva-vizualno-različita-demo-frontenda),
[vizualni pregled i screenshotovi](vizualni-pregled-m1.md#m1-10--javni-demo-dizajni-7-listopada-2026).
SEO/SSR prihvat M1-11 i raniji otvoreni admin pregledi ostaju zasebni zadaci.

Lotus: mirna editorial kompozicija, topli svijetli tonovi, veliki serifni naslovi, organski oblici i suptilni slojevi. Balance: dinamičnija kompozicija, tamniji kontrastni tonovi, geometrijski elementi i drugačiji raspored sekcija. Razlika mora biti u kompoziciji i vizualnom identitetu, ne samo logu i boji.

Svaki ima naslovnicu, kratku stranicu o studiju i pristup prijavi. Hero, predstavljanje i kontakt koriste sigurne podatke i lokalne demo medije s poznatim pravima korištenja. Uvesti ograničeni parallax i scroll reveal, poštovati `prefers-reduced-motion`, očistiti listenere/animacije pri Inertia navigaciji. Bez nedovršenih CTA-ova koji obećavaju rezervaciju.

**Prihvat:** obje stranice pregledane na 375 px, 768 px i 1440 px; nema horizontalnog overflowa, prekrivenih kontrola ili nečitljivog sadržaja. Navigacija i login dostupni tipkovnicom. Reduced-motion prikazuje cijeli sadržaj bez obvezne animacije. Sačuvati usporedne screenshotove kao dokaz vizualne razlike.

## M1-11 — SSR i granice hostova

**Ovisnosti:** M1-10. **Veza:** AC02, dio AC29.

**Status (7. listopada 2026.):** implementirano i provjereno za opseg M1-11.
[Implementacija i provjere](progress.md#m1-11--ssr-i-granice-hostova),
[browser pregled](vizualni-pregled-m1.md#m1-11--ssr-metadata-i-fallback-7-listopada-2026).

Uključiti SSR naslovnice i About stranice s tenant nazivom, title/description i canonical URL-om iz verificirane konfiguracije, nikada neprovjerenog Host zaglavlja. Držati browser-only animacije izvan SSR izvođenja. Razvojne/demo domene označiti noindex. Kad SSR proces nije dostupan, aplikacija treba imati dokumentirani client-rendered fallback i log greške bez curenja podataka.

**Prihvat:** početni HTML bez izvršavanja JavaScripta sadrži odgovarajući javni tekst studija. Lotus → Balance → Lotus zahtjevi kroz isti SSR proces ne miješaju props, metadata ni dizajn. Hydration nema grešaka. HR sadržaj obvezan; puni prijevodi nisu M1.

## M1-12 — demo seed i lokalne domene

**Ovisnosti:** M1-11. **Veza:** odjeljci 22, 29.

**Status (7. listopada 2026.):** demo seed i izravni lokalni pristup implementirani
i provjereni. [Pristupni podaci i setup](../README.md#demo-seed-i-lokalne-domene--m1-12),
[testovi](../tests/Feature/LocalDemoSeederTest.php), [rezultati](progress.md#m1-12--demo-seed-i-lokalne-domene).
Opcionalni Nginx reverse proxy dokumentiran je, ali nije izvršno provjeren;
potpuni prolaz svježeg checkouta ostaje za M1-13.

Pripremiti idempotentan lokalni seeder za dva studija, njihove verificirane demo domene, različite profile/dizajne i sve uloge. U oba studija dodati customer s istim emailom i različitim lozinkama radi provjere izolacije. Dodati dokumentirane lokalne pristupne podatke koji nikada nisu produkcijski; seed mora odbiti produkcijsko okruženje.

Dokumentirati hosts mapiranje i lokalni reverse proxy prema istoj Laravel aplikaciji. Ako lokalne promjene hosts datoteke traže sistemske ovlasti, dati precizne korisničke upute; ne pretpostavljati da su već primijenjene. Produkcijski Nginx/TLS nije dio ovog zadatka.

**Prihvat:** ponovljeni seed ne duplicira studije, domene i korisnike. Sve tri domene rade nakon dokumentiranog lokalnog postavljanja. Moguće je prikazati oba dizajna i oba admina iz čistog checkouta.

## M1-13 — integracijska provjera i CI

**Ovisnosti:** M1-12 i testovi svih prethodnih zadataka. **Veza:** AC01–AC04, AC27–AC28 i M1 dio AC29.

**Status (7. listopada 2026.):** CI dopunjen; lokalna integracijska zbirka
prolazi na MySQL-u sa stvarnim SSR-om (273 prolazi, 12 ranijih scaffold skipova,
2088 assertiona). Izvršavanje izmijenjenog workflowa na GitHub runneru još nije
potvrđeno. [Workflow](../.github/workflows/tests.yml),
[ponavljanje i mapa testova](../README.md#integracijska-provjera-i-ci--m1-13),
[rezultati i ograničenja](progress.md#m1-13--integracijska-provjera-i-ci).
Vizualni kriterij oslanja se na postojeći
[pregled M1-10](vizualni-pregled-m1.md#m1-10--javni-demo-dizajni-7-listopada-2026);
layout i animacije nisu mijenjani.
Puni svježi setup također ostaje otvoren zbog zastoja Composer autoloadera
u privremenoj čistoj kopiji; detalji su u rezultatima.

Dodati GitHub Actions provjeru migracija/testova na MySQL-u, frontend typecheck i build, te primjenjive repozitorijske lint provjere. CI ne radi deploy niti stvarno šalje mailove. Koristiti testne tajne i fixturee. Tenant/DB testovi ne smiju prolaziti samo na SQLiteu ako produkcijski constraintovi ovise o MySQL-u.

Obvezni tokovi: nepoznat host; podmetnut tenant; cross-tenant čitanje/upis; isti email i reset u dva studija; prijenos sesije na drugu domenu; registracija s podmetnutom ulogom; zabrana admina pogrešnoj ulozi; 2FA; pozivnica i deaktivacija; promjena javnog profila; job iznimka i sljedeći tenant; SSR izolacija.

**Prihvat:** automatizirane provjere prolaze, uz zapisan stvarni rezultat. Ručno pregledane obje kompozicije na definiranim širinama i reduced-motion varijanta. Ne tvrditi da je test prošao ako nije izvršen; blokadu dokumentirati kao otvorenu stavku.

## M1-14 — demonstracija i predaja

**Ovisnosti:** M1-13.

Isporučiti README za pokretanje, migracije/seed, dev mail inbox, SSR i testove; sažetu matricu prava; mapu tenanta/globalnih tablica; zapis tehničkih odluka i poznatih ograničenja. U backlogu označiti završene zadatke i linkati stvarne testove/datoteke.

Demonstracija: platform admin vidi dva studija → otvori Lotus i Balance na različitim domenama → owner promijeni Lotus kontakt → javni Lotus prikaže promjenu → Balance ostane isti → manager ne može urediti profil → polaznik istim emailom ima odvojene račune → zahtjev za tuđim podatkom je odbijen.

**Prihvat:** vlasnik može ponoviti demonstraciju prema README-u. Nema vanjskih produkcijskih ovisnosti ni stvarnih uplata. Svi M1 zadaci i prihvatni uvjeti dovršeni ili eksplicitno označeni nedovršenima; milestone se ne proglašava gotovim uz neriješeno curenje podataka.

## Definicija dovršenosti milestonea

- Dva stvarno različita javna frontend dizajna rade na jednoj aplikaciji i bazi.
- Platform i studio admin imaju funkcionalne osnovne radnje s backend autorizacijom.
- Prijava, reset, email verifikacija, 2FA i odvojeni identiteti rade u lokalnom okruženju.
- Host određuje tenant; korisnički unos ne može promijeniti njegov kontekst.
- Testovi izolacije baze, sesije, cachea, jobova i SSR-a prolaze.
- Novi developer može pokrenuti demo prema dokumentaciji; tajne nisu u repozitoriju.
- Ovo je razvojni demo spreman za sljedeći inkrement, ne sustav spreman za produkcijske naplate.

## Uputa za početak rada u Codexu

> Koristi outputs/razvojni-backlog-m1.md kao opseg ovog milestonea i outputs/specifikacija-platforme-v1.md kao širi produktni kontekst. Prvo pregledaj repozitorij i njegove upute. Implementiraj M1-01 do M1-04, uključujući pripadajuće testove; ne dodaj rezervacije, plaćanja ni vanjske produkcijske integracije. Zaključaj kompatibilne verzije, dokumentiraj lokalno pokretanje i MySQL model te dokaži izolaciju dva testna tenanta. Na kraju navedi provedene provjere, preostale stavke i pripremi nastavak od M1-05. Ne mijenjaj potvrđene produktne odluke bez obrazloženja.

Nakon prve isporuke nastaviti skupinama M1-05–M1-08 (identitet i admini), M1-09–M1-12 (dizajni i demo), pa M1-13–M1-14 (integracija i predaja). Ovaj raspored ne uvodi obvezu novih potvrda za rutinske implementacijske odluke; služi preglednosti i provjeri rezultata.
