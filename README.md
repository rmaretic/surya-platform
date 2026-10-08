# Surya

Laravel + Vue + Inertia platforma za yoga studije. Trenutačni inkrement je
M1-14: dokumentacija predaje i ponovljiva demonstracija lokalnog demoa.
Milestone još nije potpuno prihvaćen: otvorene provjere i dokazi za svih
14 zadataka nalaze se u [pregledu prihvata](docs/razvojni-backlog-m1.md#pregled-prihvata--8-listopada-2026).

Za predaju kreni od [demo scenarija](#demonstracija-i-predaja--m1-14).
Pokretanje, migracije, Mailpit i SSR opisani su u nastavku; postojeće
rezultate provjera čitaj uz njihov datum u [progressu](docs/progress.md).

## Preduvjeti

- Windows PowerShell, Git, Composer 2 i PHP 8.5 s `pdo_mysql`, `mbstring`,
  `openssl`, `fileinfo`, `curl`, `dom`, `xml` i `zip` ekstenzijama.
- Node.js 24 i npm. Inertia SSR zahtijeva najmanje Node 22.
- Docker Desktop s pokrenutim Linux engineom i Docker Compose v2.
- Slobodni lokalni portovi 13306 (razvojna baza), 3308 (testna baza),
  8000 (PHP), 5173 (Vite), 13714 (SSR iz builda), 11025 (SMTP) i 18025 (inbox).

PHP i Node rade na hostu; MySQL i Mailpit rade u Dockeru. Time se koristi postojeće
Windows okruženje bez dodatne PHP instalacije u kontejneru.

## Zaključane verzije

Zadržani su postojeći `composer.lock` i `package-lock.json`, bez nadogradnji:
Laravel 13.34.0, Inertia Laravel 3.5.1, Inertia Vue/Vite 3.8.0, Vue 3.5.43,
Vite 8.3.2, Vite+ 0.3.0, TypeScript 5.9.3, Tailwind 4.3.3 i Pest 5.3.0.
Lokalno je korišten PHP 8.5.11 i Node 24.21.0. Compose koristi fiksni
`mysql:8.4.11` image iz [službenog MySQL imagea](https://hub.docker.com/_/mysql).

## Prvo pokretanje

Pokreni Docker Desktop, zatim iz korijena svježeg checkouta:

```powershell
composer setup
php artisan db:seed --class=LocalDemoSeeder --no-interaction
composer dev
```

`composer setup` instalira Composer ovisnosti iz lockfilea, kopira `.env.example`
ako `.env` ne postoji, generira APP_KEY, pokreće obje MySQL baze i Mailpit,
izvršava migracije, instalira frontend s `npm ci` i gradi frontend i SSR.
Namijenjen je početnom setupu: ne pokretati ga radi običnog restarta jer
generira novi APP_KEY. Postojeći lokalni `.env` mora imati DB vrijednosti
usklađene s `.env.example`. Ne kopirati primjer preko vlastitih tajni.

`composer dev` pokreće PHP poslužitelj, queue listener i Vite. Inertia v3
automatski renderira SSR kroz Vite tijekom razvoja; nije potreban zaseban
SSR proces. Adresa aplikacije određena je `APP_URL` u `.env`.

Od M1-03 koriste se domene, bez fallbacka na `localhost`. Nakon eksplicitnog
lokalnog seeda dodaj sljedeći zapis u Windows hosts datoteku
`C:\Windows\System32\drivers\etc\hosts` (za uređivanje trebaju administratorska prava):

```text
127.0.0.1 platform.yoga.test lotus.yoga.test balance.yoga.test
```

Otvori odgovarajući hostname na portu 8000. Hosts datoteka nije automatski
mijenjana. `PLATFORM_DOMAIN=platform.yoga.test` određuje platformsku domenu,
a `.env.example` postavlja isti host u `APP_URL`. Domain resolver radi neovisno
o portu. Studio domene bez eksplicitnog seeda/verifikacije vraćaju 404.

Za sljedeća pokretanja:

```powershell
docker compose up -d --wait
composer dev
```

Nakon promjene `.env` zaustavi i ponovno pokreni `composer dev` (Ctrl+C),
uključujući queue proces. Stari proces može zadržati staru DB konfiguraciju.
Po potrebi izvrši `php artisan config:clear --no-interaction`.

## Baze i lokalni email

| Namjena | Host i port       | Baza / korisnik |
| ------- | ----------------- | --------------- |
| Razvoj  | `127.0.0.1:13306` | `surya`         |
| Testovi | `127.0.0.1:3308`  | `surya_testing` |

Lozinke u primjeru i Composeu su isključivo lokalne razvojne vrijednosti.
Baze imaju odvojene kontejnere, korisnike i trajne volumene; portovi su
dostupni samo preko loopbacka. PHPUnit prisilno koristi testnu bazu kako
`RefreshDatabase` ne bi obrisao razvojne podatke. Port 13306 odabran je jer
3306 i 3307 već koriste drugi lokalni projekti.

```powershell
docker compose ps
php artisan migrate --no-interaction
php artisan migrate:status --no-interaction
docker compose stop
```

`stop` čuva podatke. Ne koristiti `docker compose down -v` za rutinski restart:
ta naredba briše volumene. Inicijalne MySQL varijable vrijede pri prvom
stvaranju praznog volumena; naknadna promjena lozinke u `.env` ne mijenja
postojećeg MySQL korisnika.

`MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1` i `MAIL_PORT=11025` šalju lokalne
poruke u [Mailpit inbox](http://127.0.0.1:18025). Compose koristi
`axllent/mailpit:v1.31.3`, bez vanjskog SMTP relaya. Standardni port 1025
zauzima drugi lokalni projekt, zato se koristi 11025. Inbox sadrži osjetljive
reset/verifikacijske poveznice i dostupan je samo preko loopbacka.

Većina testova koristi array transport. `LocalAuthenticationMailTest` koristi
stvarni lokalni SMTP i Mailpit API, pa puna zbirka zahtijeva pokrenut Mailpit
uz testnu MySQL bazu. Test šalje samo na jedinstvene adrese domene `example.test`.

## Provjere

```powershell
composer validate --no-check-publish
composer check-platform-reqs
npm run check
npm run types:check
composer types:check
php artisan test --compact
npm run build
```

PHPStan koristi memorijski limit 512 MB. `npm run check` provjerava kod i
formatiranje; postojeći AGENTS.md, produktna specifikacija i backlog izuzeti
su iz automatskog formatiranja da tehničke provjere ne prepisuju te dokumente.
`npm run build` gradi i client i SSR; `build:ssr` je alias iste naredbe.
Build može prikazati upozorenja o opcionalnom Fontaine paketu i sourcemapama
Inertia plugina; dodatni paket nije instaliran.

Za lokalnu provjeru SSR-a iz builda zaustavi Vite i pokreni u odvojenim terminalima:

```powershell
npm run build
php artisan inertia:start-ssr --no-interaction
```

```powershell
php artisan serve --no-interaction
```

U trećem terminalu provjeri SSR proces:

```powershell
php artisan inertia:check-ssr --no-interaction
```

Provjeri da HTTP HTML odgovor već sadrži naslov početne stranice prije
izvršavanja JavaScripta. Vite development server nije produkcijski web server.

## Podatkovni temelj — M1-02

`users.tenant_id` je obvezan. MySQL računa `normalized_email` kao
`LOWER(TRIM(email))` i provodi jedinstvenost `(tenant_id, normalized_email)`.
Jednak email dopušten je u dva studija i u zasebnoj tablici `platform_admins`.
Izvedena polja ne upisuju se ručno. `normalized_hostname` uklanja okolne
razmake i završnu točku te pretvara naziv u mala slova; jedinstven je globalno.
Samo jedna domena po studiju može biti primarna. Od M1-03 isti normalizator
validira i pretvara IDN u ASCII/punycode pri zapisu domene i pri razrješenju hosta.

| Tablice                                                                    | Opseg i razlog                                                                                              |
| -------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `tenants`                                                                  | Globalni registar studija; nije podatak jednog studija.                                                     |
| `tenant_domains`                                                           | Domene pripadaju studiju; globalni jedinstveni indeks služi pouzdanom određivanju tenanta prije auth upita. |
| `users`, `tenant_profiles`, `staff_invitations`, `tenant_audit_logs`       | Tenant podaci s obveznim tenant FK-om.                                                                      |
| `platform_admins`, `platform_audit_logs`                                   | Odvojeni platformski identiteti i audit; platform audit može referencirati ciljni studio.                   |
| `tenant_sessions`, `tenant_password_reset_tokens`                          | Aktivno tenant auth spremište; sesije i reset tokeni ograničeni su na studio.                               |
| `platform_sessions`, `platform_password_reset_tokens`                      | Aktivno zasebno spremište platform autha.                                                                   |
| `sessions`                                                                 | Stara scaffold tablica; novi session driver je ne koristi.                                                  |
| `password_reset_tokens`                                                    | Stara scaffold tablica sačuvana bez brisanja podataka; novi brokeri je ne koriste.                          |
| `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Globalna infrastruktura; tenant cache ključevi i job kontekst opisani su u M1-04.                           |

Složeni FK-ovi na `(tenant_id, user_id)` štite autora i primatelja pozivnice,
autora tenant audita i korisnika tenant sesije. Pozivnica ima točno jednog
autora: tenant korisnika ili platform administratora. Uloge i statusi imaju
ograničene vrijednosti u bazi. Brisanje referenciranih identiteta/studija je
ograničeno; deaktivacija je predviđeni način upravljanja.

Audit `subject_type`/`subject_id` je povijesna referenca, a ne Eloquent
polimorfna veza ili živi FK. M1-07 dodaje sanitizirani platform audit i
jednokratni prihvat početne owner pozivnice. Studio upravljanje osobljem i
audit promjena javnog profila slijede u M1-08. U JSON audita ne ulaze tajne.

Migracija odbija postojeće korisnike kojima tenant nije eksplicitno dodijeljen.
Ne dodjeljuje zadani studio i ne briše podatke. Rollback odbija rad ako već
postoje studiji ili platform administratori; tada je potrebna pregledana
forward migracija. Tenant modeli koriste zaštitu upita i zapisa opisanu u M1-04.

Demo podaci stvaraju se isključivo eksplicitnom lokalnom naredbom:

```powershell
php artisan db:seed --class=LocalFoundationSeeder --no-interaction
```

Seed stvara Lotus i Balance, njihove profile i verificirane `.yoga.test`
domene. Ne stvara korisnike, lozinke, platform admine, DNS zapise ni javne
predloške. Ponovno pokretanje ne mijenja postojeće domene. Izvan `local` i
`testing` okruženja naredba odbija rad; obični `db:seed` ne stvara demo podatke.

Testovi ovog inkrementa:

```powershell
php artisan test --compact tests/Feature/TenantFoundationTest.php
php artisan test --compact tests/Feature/TenantAuthenticationBoundaryTest.php
```

U M1-06 vraćeno je još pet auth testova uz 26 novih regresijskih testova.
Preostalih 12 scaffold testova za settings i dashboard ostaje preskočeno.
Nisu izbrisani niti se računaju kao prolazni. Nedovršene settings rute ostaju
zatvorene; javna stranica ne izlaže korisnika stare scaffold sesije.

## Domenski resolver — M1-03

- Prihvaća samo aktivnu, verificiranu domenu s vremenom verifikacije i aktivnim
  studijem. Nepoznata/pending/neaktivna domena vraća neutralni 404; verificirana
  domena neaktivnog studija vraća neutralni 503.
- `App\TenantContext` je scoped servis: platform domena nema tenanta, studio
  domena postavlja točan studio. Kontekst se čisti na početku i u `finally`,
  uključujući iznimke. `requireTenant()` odbija rad bez tenant konteksta.
- Resolver se izvršava prije CORS-a, sesije, auth providera i route bindinga.
  Query/body `tenant_id` i `X-Tenant-ID` ne određuju studio.
- Putanje `/platform` i `/platform/*` rezervirane su za platform domenu.
  Ostale poslovne putanje pripadaju studiju. `/`, `/up` i postojeće razvojne
  pomoćne rute zajedničke su samo poznatim domenama. Platform registar dostupan
  je u M1-07; privremena zaštita ostaje na nedovršenim settings rutama.
- `TRUSTED_PROXIES` je prazan po zadanim postavkama. Kad je stvarni reverse proxy
  poznat, unesi njegove IP adrese ili CIDR raspone odvojene zarezom. Ne koristi
  `*` ili `REMOTE_ADDR`; nisu prihvaćeni. Proxy mora prepisivati ulazna forwarded
  zaglavlja. Izravni klijent ne može promijeniti studio pomoću tih zaglavlja.
- Nema promjene DNS-a, hosts datoteke, Nginxa, certifikata niti produkcijskog deploya.

Provjere:

```powershell
php artisan test --compact tests/Unit/NormalizeHostnameTest.php
php artisan test --compact tests/Feature/TenantResolutionTest.php
```

Konfiguracija proxyja namjerno zamjenjuje automatsko povjerenje za cloud
hostove u frameworku: primjenjuje se samo eksplicitni projektni popis.
Za API-je su provjereni instalirani Laravel 13 izvori i
[službena dokumentacija](https://laravel.com/framework/docs/requests#configuring-trusted-proxies).

## Izolacija podataka, cachea i jobova — M1-04

`User`, `TenantProfile`, `TenantDomain`, `StaffInvitation` i `TenantAuditLog`
koriste `TenantScope` i `BelongsToTenant`. Bez konteksta upiti bacaju iznimku;
platformski kontekst ne uklanja ograničenje. Implicitni route binding i relacije
koriste isti scope. `fresh()`, `refresh()` i obnova serijaliziranih modela također
poštuju kontekst. Novi zapisi dobivaju tenant na serveru; promjena vlasništva i
zapis/brisanje tuđeg učitanog modela odbijaju se.

`Tenant` je globalni registar. `ListPlatformTenants` provjerava aktivnog platform
admina i platform kontekst prije paginiranog pregleda. Resolver, lokalni seeder
i generator auth poveznica imaju eksplicitne upite domena bez tenant scopea.
Generator poveznica uvijek ograničava upit tenant ID-jem primatelja te aktivnom
verificiranom primarnom domenom.
Raw SQL, `withoutGlobalScope`, `saveQuietly` i bulk insert/upsert nisu poslovni
API za tenant podatke: zaobilaze Eloquent zaštitu i zahtijevaju zasebnu provjeru.
MySQL constraintovi ostaju dodatna zaštita pripadnosti povezanih zapisa.

`ReadTenantProfile` i `UpdateTenantProfile` autoriziraju ownera istog studija.
Akcija izmjene validira dopuštena polja i ignorira klijentski `tenant_id`.
Produkcijski obrasci/rute i audit izmjena profila slijede u zadatku za studio
administraciju; sada su HTTP granice provjerene razvojnim rutama samo u testovima.

Za tenant cache koristi `TenantCache::remember($type, $key, $seconds, $callback)`
i `forget($type, $key)`. Ključ sadrži interni tenant ID i hash zasebnog para
vrsta/ključ, bez kolizija pri spajanju segmenata. Servis odbija rad bez konteksta
i radi s postojećim database cacheom.

Tenant jobovi nose interni **skalarni tenant ID**, a ne Eloquent model ili
hostname dobiven od klijenta. Njihov `middleware()` vraća
`[new UseTenantContext($this->tenantId)]` iz `App\Jobs\Middleware`.
Middleware ponovno dohvaća aktivni studio i čisti kontekst u `finally`.
Modele učitavati tek u `handle()`; deserializacija modela događa se prije job
middlewarea i bez konteksta namjerno odbija rad. `failed()` također nema tenant
kontekst; eventualni tenant rad mora uspostaviti isti eksplicitni kontekst.

Factoryje tenant modela pozivaj uz postavljen `TenantContext` i `for($tenant)`.
Stariji DB-constraint testovi koriste `createQuietly()` samo za pripremu fixturea,
kako bi i dalje testirali MySQL ograničenja neovisno o modelskim događajima.

```powershell
php artisan test --compact tests/Feature/TenantIsolationTest.php
php artisan test --compact tests/Feature/TenantJobIsolationTest.php
```

Queue test koristi istu instancu Laravel workera za četiri stvarna posla iz
MySQL reda, uključujući namjerni neuspjeh i sljedeći posao drugog studija.
Nema vanjskih servisa ni queue fakea. Auth rute otvorene su u M1-05.

## Autentikacija — M1-05

Studio koristi `/login`, `/register`, `/forgot-password` i `/account` na
vlastitoj verificiranoj domeni. Platform koristi `/platform/login`,
`/platform/forgot-password` i `/platform/account` na platform domeni, bez
javne registracije. Nakon verifikacije emaila račun prikazuje minimalni
identitet i odjavu; administracija i booking još nisu dostupni.

Guardovi, aktivni identity provider i reset brokeri odvojeni su za studio i
platformu. Registracija uvijek stvara customer račun trenutnog studija;
podmetnuti tenant ID i uloga se ignoriraju. Isti email može imati neovisne
lozinke u različitim studijima i na platformi.

`SESSION_DRIVER=tenant-database` koristi odvojene tablice te ograničava studio
sesije tenant ID-jem. Middleware provjerava i oznaku konteksta u samoj sesiji.
Cookie je host-only i HttpOnly; produkcija prisilno uključuje Secure, a lokalni
HTTP ostaje podržan. Prijava regenerira sesiju, odjava je invalidira, a promjena
konteksta regenerira i CSRF token. Neaktivni računi ne prolaze auth provider.

Reset i verifikacijske poveznice koriste aktivnu verificiranu primarnu domenu
studija primatelja ili konfigurirani `PLATFORM_DOMAIN`. Ne preuzimaju hostname
iz zahtjeva. Lokalni protokol/port dolazi iz `APP_URL`; produkcija koristi HTTPS.
Rate limit uključuje studio/platformu, identitet i IP. Reset zahtjev vraća istu
poruku za postojeći i nepostojeći račun. Tokeni su ograničeni na odgovarajući
kontekst, istječu i nakon uspješnog reseta više ne vrijede.

Prvi platform admin kreira se lokalno u interaktivnom terminalu:

```powershell
php artisan platform:create-admin operator@example.test --name="Lokalni administrator"
```

Naredba traži lozinku i potvrdu skrivenim unosom; lozinka nije argument naredbe.
Zahtijeva najmanje 12 znakova, velika i mala slova, broj i simbol. Nakon prijave
zatraži verifikacijski email i otvori ga u Mailpitu. Bez eksplicitnog demo seeda
nije unaprijed kreiran razvojni admin. M1-12 ispod dokumentira demo račun;
za stvarni račun koristi vlastiti email.
Amazon SES i druge produkcijske integracije nisu dio ovog inkrementa.

Nakon ažuriranja lokalnog `.env` ponovno pokreni `composer dev` da procesi
učitaju novi session driver i SMTP postavke.

```powershell
docker compose up -d --wait
php artisan test --compact tests/Feature/TenantAuthenticationTest.php
php artisan test --compact tests/Feature/PlatformAdminCommandTest.php
php artisan test --compact tests/Feature/LocalAuthenticationMailTest.php
```

Rezultat pri završetku M1-05: 151 prolazi, 17 preskočeno, 519 assertiona. PHPStan, Pint,
frontend format/lint, TypeScript te client/SSR build prolaze. Stvarni HTTP
odgovori prikazuju SSR obrasce studija i platforme s ispravnim action putanjama.
Vizualni pregled i interakcija u pregledniku još nisu provjereni jer alat nema
povezan preglednik. Rezultati M1-06 slijede u nastavku.

## Uloge i 2FA — M1-06

Owner i platform admin nakon prijave i verifikacije emaila moraju potvrditi
lozinku i dovršiti TOTP enrolment. Samo generiranje QR koda ne daje ovlasti:
potrebna je potvrda koda. Obvezni 2FA nije moguće isključiti ni izravnim HTTP
zahtjevom. Ostali korisnici mogu dobrovoljno uključiti i isključiti 2FA.

| Radnja                         | Platform admin | Owner      | Manager | Instructor          | Customer |
| ------------------------------ | -------------- | ---------- | ------- | ------------------- | -------- |
| Platform registar studija      | Da, uz 2FA     | Ne         | Ne      | Ne                  | Ne       |
| Studio administracija          | Ne             | Da, uz 2FA | Da      | Da, ograničen opseg | Ne       |
| Javni profil studija i osoblje | Ne             | Da, uz 2FA | Ne      | Ne                  | Ne       |
| Portal polaznika               | Ne             | Ne         | Ne      | Ne                  | Da       |
| Vlastiti račun i sigurnost     | Da             | Da         | Da      | Da                  | Da       |

Policies provode matricu i izvan HTTP middlewarea. Osoblje se smije pozvati
ili prebaciti samo u manager/instructor ulogu; owner i customer nisu mete
deaktivacije osoblja. Svi tenant ciljevi moraju pripadati aktivnom studiju.
Platform registar i početna owner pozivnica dostupni su od M1-07,
a javni profil i upravljanje osobljem od M1-08.
`/dashboard` prikazuje pregled prema ulozi, `/portal` vlastiti račun polaznika;
`/platform/dashboard` prikazuje stvarni registar i brojeve studija.

### Lokalna demonstracija enrolmenta

1. Pokreni aplikaciju i Mailpit prema prethodnim uputama. Pripremi platform
   račun naredbom `platform:create-admin` ako ga još nemaš. Owner koristi
   isti tok na domeni vlastitog studija nakon prihvata pozivnice iz M1-07.
2. Na platform domeni otvori `/platform/login`, prijavi se i verificiraj email
   poveznicom iz Mailpita. Nakon prijave otvara se postavljanje 2FA uz potvrdu
   lozinke. Studio koristi `/login` i `/settings/two-factor`, a platform
   `/platform/settings/two-factor`.
3. Odaberi **Uključi 2FA**, skeniraj QR u TOTP aplikaciji ili unesi ključ ručno,
   nastavi i potvrdi šesteroznamenkasti kod. Sat uređaja treba biti usklađen.
4. Odaberi **Prikaži kodove za oporavak** i spremi ih u upravitelj lozinki.
   Svaki vrijedi jednom. **Obnovi kodove** poništava prethodni skup.
5. Odjavi se i ponovno prijavi. Nakon lozinke traži se kod iz aplikacije ili
   jedan recovery kod. Iskorišteni recovery kod ne radi drugi put. Nakon
   enrolmenta pričekaj sljedeći TOTP kod ako je prethodni već iskorišten.

Nema univerzalnog bypass koda niti demo isključivanja zaštite. QR, ključ i
recovery kodovi dolaze samo iz zaštićenih JSON endpointa nakon potvrde lozinke,
nikada iz Inertia propsa. Odgovori imaju `no-store`; Vue stanje je lokalno
instanci komponente i čisti se pri odlasku. Kodovi se ne spremaju u old input.
Pogrešni challenge/enrolment kodovi i potvrde lozinke ograničeni su rate
limitom po kontekstu, identitetu i IP-u. TOTP replay cache odvojen je po
studiju i identitetu. MySQL zaključavanje identiteta serijalizira potrošnju
recovery kodova; deaktivacija tijekom challengea odbija prijavu.

```powershell
php artisan test --compact tests/Feature/RoleAndTwoFactorTest.php
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer types:check
npm run types:check
npm run check
npm run build
```

Rezultat M1-06: **182 prolazi, 12 preskočeno, 894 assertiona**. PHPStan,
Pint, Vue TypeScript, format/lint i client/SSR build prolaze. Preostali
preskočeni testovi pripadaju nedovršenim settings/dashboard funkcijama.

Provjeren je render triju auth stranica kroz isti SSR proces, redom studio,
platforma, studio: devet odgovora s ispravnim action putanjama. Vizualni
pregled, tipkovnica, mobilni prikaz i hydration još nisu provjereni jer nema
povezanog preglednika. M1-06 zato još nije označen potpuno prihvaćenim.

Ako `inertia:start-ssr` na Windowsu ispiše `The system cannot find the path
specified`, izgrađeni SSR može se pokrenuti izravno, u zasebnom terminalu:

```powershell
node bootstrap/ssr/app.js
```

Provjeri ga naredbom `php artisan inertia:check-ssr --no-interaction`, a
zaustavi Ctrl+C. Ne pokreći drugi SSR proces ako je port već zauzet.

## Platform administracija — M1-07

Nakon preuzimanja promjena pokreni migraciju i build:

```powershell
php artisan migrate --no-interaction
npm run build
```

Migracija dodaje nullable `sent_at` i `delivery_failed_at` pozivnicama.
Postojeći podaci ostaju sačuvani. Rollback uklanja povijest tih dvaju polja;
za zajedničko okruženje prednost ima korektivna forward migracija.

Na platform domeni, nakon prijave, verifikacije emaila i 2FA, otvori
**Administracija studija** na računu ili `/platform/tenants`.
Platform layout sadrži registar, kreiranje studija, račun i odjavu.
Dashboard broji stvarne `pending`, `active` i `suspended` studije. Pretraga
po nazivu ili domeni ima paginaciju od 25 zapisa. Detalj prikazuje javni
profil, domene, pozivnicu i posljednjih 30 platformskih audit zapisa.

### Kreiranje i ručna verifikacija domene

1. Odaberi **Kreiraj studio**, unesi naziv, javni profil, početni owner email,
   primarni hostname bez protokola/porta i registrirani dizajn `lotus` ili
   `balance`. Tenant, profil, pending domena, pozivnica i audit nastaju u
   jednoj MySQL transakciji. Neuspjeh ne ostavlja djelomičan studio.
2. Studio je `pending`, a domena ne služi javnu stranicu niti autentikaciju.
   Dodatne domene također uvijek nastaju kao pending. Hostname se normalizira,
   globalno je jedinstven i ne smije biti platform domena.
3. **Izvan aplikacije** operator provjeri kontrolu domene (npr. DNS dokaz
   vlasnika), DNS usmjeravanje, reverse proxy prema ovoj aplikaciji i TLS.
   U lokalnom demou umjesto javnog DNS/TLS-a provjeri hosts mapiranje,
   lokalni port i odgovor odgovarajućeg hosta. Aplikacija ne mijenja DNS,
   hosts, Nginx ni certifikate.
4. Dokaze i vrijeme provjere zabilježi u operativnom zapisu, primjerice
   `OPS-123`. U detalju domene upiši njegovu referencu i potvrdi checkbox
   o provjeri. Referenca dopušta najviše 120 znakova: slova, brojke, razmak
   i `._/:#-`. Ne unositi lozinke, tokene ni URL s tajnim parametrima.
5. **Potvrdi verifikaciju** postavlja verified status i vrijeme. Ako je riječ
   o aktivnoj primarnoj domeni novog pending studija, ista transakcija aktivira
   studio. Audit bilježi operatora, studio, domenu, vrijeme, referencu i
   prethodni/novi status. Potvrda nije automatski dokaz konfiguracije infrastrukture.

Registar dizajna ograničava izbor i promjenu metapodatka `design_key`.
Od M1-09/M1-10 dopušteni ključevi prikazuju zasebne Lotus/Balance frontendove.

### Početna owner pozivnica

- Pozivnica nakon kreiranja čeka aktivnu verificiranu primarnu domenu.
  Potom odaberi **Pošalji poziv**. U lokalnom okruženju koristi Mailpit;
  produkcijski SES nije uključen. Za demo koristi `example.test` adrese.
- Slanje je sinkrono radi neposrednog prikaza rezultata. `sent_at` znači da
  je transport prihvatio poruku, ne da je poruka sigurno dostavljena u inbox.
  Transportna pogreška ostavlja `delivery_failed_at` i mogućnost ponavljanja,
  bez zapisivanja sirove SMTP iznimke. Token se ne sprema u queue ni audit.
- **Ponovi poziv** generira novi token i novih sedam dana; prethodna poveznica
  više ne vrijedi. Nakon prekida procesa ili nejasnog rezultata dostave
  ponovno slanje daje poznato stanje. U bazi ostaje samo SHA-256 hash.
- Poveznica koristi isključivo verificiranu primarnu domenu. Token je u URL
  fragmentu `#token=…`, koji preglednik ne šalje serveru pri otvaranju. Obrazac
  ga čita lokalno i uklanja iz adresne trake; nema ga u Inertia propsima,
  remembered form stanju ni session old inputu. Nakon osvježavanja stranice
  ponovno otvori poveznicu iz emaila. Za prihvat je potreban JavaScript.
- Vlasnik unosi ime i vlastitu lozinku. Email, tenant i owner uloga dolaze
  iz pozivnice; klijentski email/uloga/tenant ne mijenjaju identitet.
  Posjedovanje valjane email pozivnice potvrđuje email. Prihvat je jednokratan
  i pod MySQL zaključavanjem, nakon čega slijedi standardna prijava i obvezni
  Fortify 2FA enrolment. Prihvaćena pozivnica ne može se ponovno poslati.
- Postojeći račun istog emaila u tom studiju neće se promaknuti niti će mu
  se prepisati lozinka. Takav konflikt traži operativnu odluku; spajanje
  identiteta i prijenos vlasništva nisu dio M1. Manager/instructor pozivi,
  opoziv kroz UI i upravljanje osobljem slijede u M1-08.

### Deaktivacija i audit

**Suspendiraj studio** zahtijeva referencu odluke i potvrdu posljedica.
Suspendirani studio odmah vraća neutralni 503 na verificiranoj domeni,
uključujući prijavu i zahtjeve postojećih sesija; tenant worker odbija poslove.
Podaci se ne brišu. Verifikacija domene ne može reaktivirati suspendirani studio.
Reaktivacija i promjena primarne domene nisu dio ovog inkrementa.

Sve platform promjene provjeravaju postojeći policy i potvrđeni 2FA.
Privremeni tenant kontekst pri platform zapisu vraća se u `finally`.
Audit prihvata owner poziva pripada studiju i navodi novog vlasnika kao autora;
platform audit navodi platform admina za pripremu i slanje poziva.
Promijenjena je Fortify registracija koraka provjere 2FA kako ne bi zadržao
guard prethodnog konteksta kada ista instanca aplikacije obradi platform i studio.

Provjere M1-07:

```powershell
php artisan test --compact tests/Feature/PlatformTenantTest.php
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer types:check
npm run types:check
npm run check
npm run build
```

Testovi zahtijevaju zasebnu MySQL testnu bazu i lokalni Mailpit iz Composea.
Rezultat: **29 novih testova, 321 assertion**; puna zbirka **211 prolazi,
12 ranije preskočenih, 1215 assertiona**. PHPStan, Pint, Vue TypeScript,
format/lint i client/SSR build prolaze. Četiri nove stranice uspješno se
renderiraju kroz isti SSR proces. Vizualni pregled, mobilne širine,
tipkovnica i browser hydration ostaju otvoreni jer preglednik nije dostupan.

## Studio administracija i osoblje — M1-08

Na domeni svojeg studija prijavi se kao owner s potvrđenim 2FA. Navigacija
računa sada vodi na **Pregled**, **Javni profil** i **Osoblje**.

- Javni profil uređuje naziv, kratki opis, email, telefon i adresu. **Spremi
  i objavi** odmah mijenja javnu naslovnicu samo tog studija. Naziv u registru
  platforme ostaje operativni identitet studija. Javna stranica koristi
  dodijeljeni Lotus ili Balance dizajn iz dopuštenog registra.
- Owner poziva samo managera ili instruktora. Email se normalizira, a postojeći
  račun u istom studiju ne promiče se automatski. Postojeću nepotrošenu pozivnicu
  treba ponovno poslati ili opozvati prije stvaranja nove za isti email.
- Poziv traje sedam dana, vezan je uz studio i email te se koristi jednom.
  Prihvat postavlja ime i lozinku novog računa; email i uloga dolaze iz poziva.
  Korisnik potom prolazi normalnu prijavu. Token je hashiran u bazi i prenosi
  se URL fragmentom, bez uključivanja u Inertia props ili audit.
- Ponovno slanje poništava prethodnu poveznicu. Neuspjelo slanje ostavlja
  pozivnicu i jasan status za retry; opoziv trajno onemogućuje taj poziv.
- Owner mijenja manager/instructor uloge i deaktivira osoblje uz potvrdu
  posljedica. Deaktivacija briše tenant sesije i remember token; aktivnost
  identiteta provjerava se pri svakom zahtjevu. Owner i customer nisu ciljevi
  ovih radnji. Reaktivacija nije dio ovog ekrana.
- Manager i instructor imaju ograničeni pregled i vlastiti račun. Polaznik
  ima vlastiti profil s porukom da rezervacije dolaze kasnije. Nema aktivnih
  kontrola za rezervacije, naplatu ili druge neimplementirane module.

Promjene profila, uloga, deaktivacije i pozivnica zapisuju tenant audit s
autorom, ciljem, vremenom i dopuštenim promjenama. Email koristi postojeći
lokalni Mailpit; nema produkcijske integracije ni novih migracija/ovisnosti.
Scaffold uređivanje osobnog profila/brisanje računa ostaje zatvoreno kao i
prije ovog inkrementa; minimalni profil u M1-08 služi pregledu vlastitih podataka.

Provjere i ponavljanje demonstracije:

```powershell
php artisan test --compact tests/Feature/StudioAdministrationTest.php
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer types:check
npm run types:check
npm run check
npm run build
```

Rezultat 6. listopada 2026.: **21 novi test, 289 assertiona**; puna MySQL zbirka
**232 prolazi, 12 ranije preskočenih, 1504 assertiona**. PHPStan, Pint, frontend
format/lint, Vue TypeScript i client/SSR build prolaze. Četiri nova ekrana
renderirana su kroz isti SSR proces, uključujući popunjeno osoblje i pozivnicu.
Chrome pregled portala polaznika na 375/768/1440 px nema overflowa ni
warn/error zapisa. Owner ekrani i vizualni tijek pozivnice još čekaju pregled.

Za ručnu demonstraciju promijeni Lotus telefon, otvori njegovu javnu stranicu
i usporedi Balance. Zatim provjeri zabranu izmjene kao manager, pošalji poziv
na lokalnu testnu adresu i prihvati ga iz Mailpita. Nakon deaktivacije provjeri
da ranije otvorena sesija djelatnika više nema pristup. Automatizirani testovi
pokrivaju te granice na zasebnoj MySQL bazi, bez izmjene razvojnih računa.

## Registar javnih dizajna — M1-09

`App\DesignRegistry` je jedini izvor dopuštenih ključeva i mapiranja stranica:

| Ključ     | Naslovnica           | O studiju             |
| --------- | -------------------- | --------------------- |
| `lotus`   | `sites/lotus/Home`   | `sites/lotus/About`   |
| `balance` | `sites/balance/Home` | `sites/balance/About` |

Na domeni studija `/` i `/about` koriste isti `PublicStudioController` i
`PublicStudioProfile`: naziv, kratki opis, javni email, telefon i adresa.
Sirovi modeli, interni ID-jevi, osoblje, auth podaci, administrativne poruke
i validacijske greške nisu dio javnog ugovora. Shared props čiste se pri
svakom zahtjevu, uključujući uzastopne privatne i javne zahtjeve iste aplikacije.
Ako profil još ne postoji, prikazuje se samo naziv aktivnog studija i prazna
kontaktna polja. Platform naslovnica ostaje neutralna; nema platform `/about`.

Promjena registriranog ključa kroz platform administraciju mijenja obje javne
stranice uz isti profil i backend. Nevaljani ključ pri izmjeni odbija se;
nepoznati ključ koji je već u bazi daje 503 bez fallbacka. Operativni warning
bilježi samo ID studija, bez neprovjerene putanje ili privatnih podataka.

Vue koristi eksplicitni `pages: { path: './pages', lazy: true }`, zasebne
layoute s `scoped` CSS-om i zajednički kontaktni prikaz. Build manifest potvrđuje
četiri dinamička ulaza i odvojeni CSS za oba dizajna, bez statičnog uvoza drugog
dizajna. Ovo su osnovne stranice; pune kompozicije, mediji i animacije slijede
u M1-10, a potpuni SEO/SSR prihvat u M1-11.

```powershell
php artisan test --compact tests/Feature/PublicStudioTest.php
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer types:check
npm run types:check
npm run check
npm run build
```

Rezultat 6. listopada 2026.: **13 novih testova, 212 assertiona**; puna MySQL
zbirka **245 prolazi, 12 ranije preskočenih, 1716 assertiona**. PHPStan,
Pint, TypeScript, format/lint i client/SSR build prolaze. U Chromeu pregledane
su obje Home/About stranice, mobilna širina 375 px i odabrani desktop prikazi
na 1440 px, bez uočenog overflowa ili warn/error zapisa. Provjereni su navigacija
Enterom, stvarni SSR HTML i učitavanje samo pripadajućeg CSS-a dizajna.

## Javni demo dizajni — M1-10

Lotus koristi toplu editorial kompoziciju, serifne naslove i organsku ilustraciju;
Balance tamnu podlogu, geometrijsku ilustraciju i široke sekcije. Oba imaju
naslovnicu, stranicu o studiju, javni kontakt ako je popunjen i poveznice na
postojeću prijavu. Naziv, opis i kontakt dolaze iz javnog DTO-a; nema izmišljenog
kontakta ni aktivnih rezervacija. Dizajni zadržavaju svoj identitet i kad je
postavka računa tamna/svijetla; scoped CSS ne mijenja administraciju.

Izvorne lokalne SVG ilustracije `stillness.svg` i `movement.svg` nalaze se uz
pripadajuće stranice u `resources/js/pages/sites`. Izrađene su za ovaj projekt
i označene CC0-1.0; ne sadrže fotografije, vanjske resurse ni prikaze stvarnih
prostora/osoba. Svaka je manja od 2 KB i učitava se samo uz svoj dizajn.

Zajednički `usePublicMotion` pokreće kratko otkrivanje vidljivih sekcija i
dekorativni parallax do 18 px na širinama od 768 px. Sadržaj je vidljiv prije
inicijalizacije, a reduced-motion isključuje oba efekta. Promjena postavke
djeluje odmah; unmount uklanja listenere, observer, frame i aktivne animacije.

```powershell
npm run types:check
npm run check
npm run build
php artisan test --compact tests/Feature/PublicStudioTest.php
```

TypeScript, format/lint (`npm run check:fix`) i build prolaze;
javni testovi imaju 13 prolaza i 212 assertiona. Za ručno ponavljanje otvori
`/` i `/about` na obje lokalne studio domene: 375, 768 i 1440 px, zatim uključi
reduced-motion, prođi navigaciju Tabom/Enterom i ponovi Home/About navigaciju.
Vite u sandboxu može zahtijevati pokretanje izvan sandboxa zbog `spawn EPERM`.
Nisu dodane ovisnosti ni promijenjen redovni način pokretanja.

[Vizualni izvještaj i screenshotovi](docs/vizualni-pregled-m1.md#m1-10--javni-demo-dizajni-7-listopada-2026)
razlikuju stvarno prazne razvojne profile od označenih browser demo podataka.
Razvojna baza nije mijenjana. Cijela testna zbirka nije ponovno izvršena;
pokreni `php artisan test --compact` za širu integracijsku provjeru.
Sljedeći zadatak je M1-11 (SEO/SSR i granice hostova).

## SSR, SEO i granice hostova — M1-11

Javne Home/About stranice imaju hrvatski `lang`, naslov studija, description,
canonical i robots oznaku. Naslov javnog studija ne dodaje naziv platforme.
Opis koristi javni kratki opis, a kad je prazan, neutralni hrvatski tekst.
Metadata se ažurira i pri Inertia navigaciji; fallback ne stvara duple oznake.

Canonical koristi isključivo aktivnu verificiranu primarnu domenu trenutnog
studija i imenovanu rutu. Shema i port dolaze iz `APP_URL` kroz postojeću
`tenancy.auth_scheme/auth_port` konfiguraciju; produkcija koristi HTTPS.
Ulazni Host, forwarded zaglavlja, port i query ne određuju canonical.
Verificirani alias vodi na canonical primarne domene. Ako primarna domena
nedostaje, nije aktivna ili verificirana, stranica ostaje dostupna na dopuštenom
aliasu, bez canonicala i uz noindex; nema fallbacka na tuđi studio.

`PUBLIC_INDEXING=false` je zadana vrijednost. Indeksiranje traži istodobno
`APP_ENV=production`, `PUBLIC_INDEXING=true` i zahtjev na primarnoj domeni.
Lokalno/staging okruženje, aliasi i rezervirane demo domene poput `.test`
ostaju `noindex, nofollow`. M1 ne dodaje prijevode, hreflang, sitemap ni Open Graph.

### Pokretanje i provjera SSR-a

Uz `npm run dev` Inertia 3 koristi Vite SSR. Za provjeru izgrađene verzije:

```powershell
npm run build
node bootstrap/ssr/app.js
```

Node ostavi u zasebnom terminalu; standardni port je 13714. Ako već postoji
SSR proces, koristi njega ili ga restartaj nakon builda, bez pokretanja drugog
na istom portu. `php artisan inertia:check-ssr --no-interaction` provjerava
aktivni SSR transport; uz postojeći `public/hot` to je Vite, inače Node.

Testovi koriste zasebnu MySQL bazu i ne mijenjaju razvojne profile. Live SSR
testovi izričito zanemaruju `public/hot` i koriste zadani URL istog Node procesa:

```powershell
$env:SSR_TEST_URL = 'http://127.0.0.1:13714'
php artisan test --compact tests/Feature/PublicStudioSsrTest.php tests/Feature/PublicStudioTest.php tests/Feature/TenantResolutionTest.php
Remove-Item Env:SSR_TEST_URL
php vendor/bin/pint --dirty --format agent
composer types:check
npm run types:check
npm run check
```

Bez `SSR_TEST_URL` dva testa stvarnog Node renderiranja eksplicitno se preskaču;
ostali SEO/fallback testovi rade bez SSR procesa. Live testovi provjeravaju
početni HTML, javni tekst u DOM-u bez izvršavanja JavaScripta, zasebne metadata
oznake i dizajne u Lotus → Balance → Lotus slijedu te escaping javnog sadržaja.
Za širu integracijsku provjeru pokreni `php artisan test --compact`.

### Ponašanje pri nedostupnom SSR-u

Inertia zadržava HTTP 200 i šalje client-rendered shell. Naslov, description,
canonical i noindex ostaju u početnom HTML-u, a Vue prikazuje sadržaj nakon
učitavanja JavaScripta. Bez JavaScripta u tom stanju nema javnog sadržaja:
fallback održava upotrebljivost, ali ne zamjenjuje ispravan SSR za SEO.
Laravel warning `SSR rendering failed; using client rendering.` sadrži samo
enum kategoriju greške, bez URL-a, propsa, stacka ili sadržaja iznimke.
Ne uključivati `inertia.ssr.throw_on_error` za redovni rad; live testovi ga
uključuju kako ne bi prihvatili fallback kao uspješan SSR.

Za ručno ponavljanje koristi izgrađene assete bez aktivnog Vite hot transporta,
otvori Home/About obaju studija, zatim zaustavi vlastiti Node proces s Ctrl+C
i osvježi stranicu. Provjeri vidljivi sadržaj, navigaciju, jednu canonical oznaku
i sanitizirani warning u Laravel logu. Ponovno pokreni Node nakon provjere.
Ako `public/hot` postoji, gašenje zasebnog Node procesa ne simulira pad Vite SSR-a.

## Demo seed i lokalne domene — M1-12

Nakon `composer setup` pokreni eksplicitni demo seed iz prvog poglavlja.
Za postojeću instalaciju s primijenjenim migracijama dovoljno je:

```powershell
php artisan db:seed --class=LocalDemoSeeder --no-interaction
```

Seed dopušta samo `local` i `testing`; `--force` ne zaobilazi zabranu produkcije
ili staginga. Zadani `DatabaseSeeder` ostaje prazan, a `LocalFoundationSeeder`
i dalje stvara samo osnovne studije. Demo seed ga nadopunjuje u jednoj transakciji.
Ne šalje emailove. Lotus i Balance imaju različite opise, dizajne i jasno
označene demo adrese te emailove pod rezerviranom domenom `example.test`.
Telefon ostaje prazan kako demo ne bi povezao stvarnu osobu.

Pristupni podaci **isključivo za lokalni demo**, nikada za produkciju:

| Domena             | Uloga          | Email                           | Početna lozinka       |
| ------------------ | -------------- | ------------------------------- | --------------------- |
| platform.yoga.test | Platform admin | platform@example.test           | `Platform-Demo-2026!` |
| lotus.yoga.test    | Owner          | owner.lotus@example.test        | `Lotus-Demo-2026!`    |
| lotus.yoga.test    | Manager        | manager.lotus@example.test      | `Lotus-Demo-2026!`    |
| lotus.yoga.test    | Instructor     | instructor.lotus@example.test   | `Lotus-Demo-2026!`    |
| lotus.yoga.test    | Customer       | customer@example.test           | `Lotus-Demo-2026!`    |
| balance.yoga.test  | Owner          | owner.balance@example.test      | `Balance-Demo-2026!`  |
| balance.yoga.test  | Manager        | manager.balance@example.test    | `Balance-Demo-2026!`  |
| balance.yoga.test  | Instructor     | instructor.balance@example.test | `Balance-Demo-2026!`  |
| balance.yoga.test  | Customer       | customer@example.test           | `Balance-Demo-2026!`  |

Studio prijava je `/login`, platform prijava `/platform/login`. Novi demo računi
imaju verificiran email. Owner i platform admin nakon prijave moraju potvrditi
lozinku, uključiti 2FA, skenirati QR vlastitim autentikatorom i potvrditi TOTP.
Spremi recovery kodove privatno. Tek tada su dostupni `/dashboard` odnosno
`/platform/dashboard`; seed ne uključuje poznatu TOTP tajnu niti zaobilazi 2FA.
Manager/instructor imaju ograničeni dashboard, customer vlastiti `/account`.

Ponovljeni seed ne duplicira zapise i ne resetira lozinke, 2FA, verifikaciju
emaila, deaktivaciju ni uređene profile. U profilu dopunjuje samo `null` demo
polja; namjerno prazni string ostaje prazan. Postojeći osnovni seed može se
nadograditi bez brisanja podataka. Početna lozinka vrijedi samo za novostvoreni
račun; za ranije promijenjenu lozinku koristi postojeći reset kroz Mailpit.
Konflikt uloge demo emaila ili promijenjena konfiguracija studija/domene prekida
cijeli seed bez djelomičnih upisa. Provjeri postojeći zapis u administraciji;
nemoj brisati bazu niti vraćati tuđu ulogu radi prolaska seeda.

### Hosts i izravni razvojni pristup

U Windowsu otvori Notepad preko **Run as administrator**, zatim otvori
`C:\Windows\System32\drivers\etc\hosts` uz filter **All files**. Sačuvaj postojeće
retke i dodaj ovaj samo ako iste domene već nisu ispravno mapirane:

```text
127.0.0.1 platform.yoga.test lotus.yoga.test balance.yoga.test
```

Spremi datoteku bez nastavka `.txt`. `ipconfig /flushdns` osvježava DNS cache.
Hosts zapis ne postavlja port: uz standardni `composer dev` koristi port 8000
na sve tri domene i zadrži `APP_URL=http://platform.yoga.test:8000`.
Nema tri instalacije ni tri baze. Ako dobiješ 404, provjeri seed, hostname i
status domene; ako je veza odbijena, provjeri PHP proces i port.

### Lokalni reverse proxy prema istoj aplikaciji

Za lokalni prikaz preko porta 8080 može se koristiti zasebna Windows Nginx
instanca. Nginx nije Composer/npm ovisnost niti ga `composer setup` instalira.
Preduvjet je raspakiran [službeni Windows Nginx](https://nginx.org/en/docs/windows.html)
i slobodan port 8080. Ne prepisuj konfiguraciju postojećih projekata: ovaj primjer
stavi kao `conf/surya-local.conf` u zasebnoj Nginx instanci.

```nginx
worker_processes 1;
events { worker_connections 128; }
http {
    server {
        listen 127.0.0.1:8080 default_server;
        server_name _;
        return 404;
    }
    server {
        listen 127.0.0.1:8080;
        server_name platform.yoga.test lotus.yoga.test balance.yoga.test;
        location / {
            proxy_pass http://127.0.0.1:8000;
            proxy_set_header Host $http_host;
            proxy_set_header Forwarded "";
            proxy_set_header X-Forwarded-For "";
            proxy_set_header X-Forwarded-Host "";
            proxy_set_header X-Forwarded-Port "";
            proxy_set_header X-Forwarded-Proto "";
        }
    }
}
```

Zadržavanje [Host zaglavlja](https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_set_header)
omogućuje Laravelu razriješiti studio. Proxy ne mijenja shemu i uklanja forwarded
zaglavlja; `TRUSTED_PROXIES` ostaje prazan. Ne postavljati wildcard povjerenje.
U lokalnom `.env` postavi `APP_URL=http://platform.yoga.test:8080`, ostavi
`PLATFORM_DOMAIN=platform.yoga.test`, `PUBLIC_INDEXING=false` i host-only cookie.
Očisti konfiguraciju i restartaj `composer dev`. PHP i dalje sluša na 8000;
browser koristi 8080. Vite ostaje izravno dostupan na 5173 za assete/HMR.

Iz korijena raspakirane zasebne Nginx instance:

```powershell
.\nginx.exe -t -c conf/surya-local.conf
Start-Process -FilePath .\nginx.exe -ArgumentList '-c', 'conf/surya-local.conf' -WindowStyle Hidden
```

Nakon izmjena prvo ponovi `-t`, zatim `.\nginx.exe -s reload -c conf/surya-local.conf`.
Za gašenje te instance: `.\nginx.exe -s quit -c conf/surya-local.conf`.
To je lokalni HTTP primjer bez certifikata; produkcijski DNS/Nginx/TLS nije M1-12.
Za povratak na izravni pristup vrati `APP_URL` na port 8000 i restartaj procese.

### Ponovljiva provjera

```powershell
php artisan db:seed --class=LocalDemoSeeder --no-interaction
php artisan db:seed --class=LocalDemoSeeder --no-interaction
php artisan test --compact tests/Feature/LocalDemoSeederTest.php tests/Feature/TenantFoundationTest.php tests/Feature/TenantAuthenticationTest.php tests/Feature/RoleAndTwoFactorTest.php
```

Na odabranom portu otvori naslovnice i About obaju studija, platform prijavu,
zatim owner i platform admin prijavu s vlastitim TOTP enrolmentom. Provjeri
da Lotus customer lozinka ne radi na Balanceu, a Balance lozinka radi za isti
email. Za provjeru proxy routinga bez izmjene hosts datoteke:

```powershell
curl.exe --noproxy "*" --resolve lotus.yoga.test:8080:127.0.0.1 -I http://lotus.yoga.test:8080/
curl.exe --noproxy "*" --resolve balance.yoga.test:8080:127.0.0.1 -I http://balance.yoga.test:8080/
curl.exe --noproxy "*" --resolve platform.yoga.test:8080:127.0.0.1 -I http://platform.yoga.test:8080/platform/login
```

Očekuje se 200 za sva tri zahtjeva; ta provjera ne potvrđuje Windows hosts
mapiranje. Njega provjeri browserom bez `--resolve`. Stvarno izvršene provjere
i ograničenja zabilježeni su u [napretku M1-12](docs/progress.md#m1-12--demo-seed-i-lokalne-domene).
Za cijelu zbirku pokreni `php artisan test --compact`.

## Integracijska provjera i CI — M1-13

Workflow `.github/workflows/tests.yml` pokreće se na push u `main` i pull
requestove. Koristi PHP 8.5, Node 24 i zaključane Composer/npm ovisnosti.
`composer setup --no-interaction` na praznom runneru podiže MySQL 8.4.11 i
lokalni Mailpit, izvršava migracije te gradi client i SSR. Zatim se provjeravaju
Composer manifest/platforma i dvostruko izvršavanje demo seeda.

Prije `composer ci:check` CI pokreće izgrađeni Node SSR, čeka `/health` i
postavlja `SSR_TEST_URL`. Time se izvršavaju i dva stvarna SSR testa; kvar
procesa prekida provjeru. `ci:check` uključuje frontend format/lint i
TypeScript, Pint, PHPStan i cijelu Pest zbirku. MySQL je prisilno odabran u
`phpunit.xml`; testna baza odvojena je od demo baze. CI koristi generirani
APP_KEY i lokalne fixture lozinke, bez GitHub produkcijskih tajni. Zadani mail
transport je `array`; jedini SMTP test šalje na `example.test` kroz lokalni
Mailpit bez vanjskog relaya. Workflow nema deploy i na kraju gasi svoje servise.

Lokalno ponavljanje uz pokrenute servise i postojeći `.env`:

```powershell
docker compose up -d --wait
composer validate --strict --no-check-publish
composer check-platform-reqs
npm run build
node bootstrap/ssr/app.js
```

Node ostavi u prvom terminalu. U drugom:

```powershell
$env:SSR_TEST_URL = 'http://127.0.0.1:13714'
composer ci:check
Remove-Item Env:SSR_TEST_URL
```

Zaustavi samo Node proces koji si pokrenuo pomoću Ctrl+C. Ako je port već
zauzet, provjeri postojeći SSR proces prije pokretanja drugog. Live testovi
zanemaruju `public/hot`; postojeći Vite može ostati pokrenut.

| Obvezni tokovi                                                       | Regresijski testovi u `tests/Feature`                        |
| -------------------------------------------------------------------- | ------------------------------------------------------------ |
| Nepoznat host, proxy i podmetnut tenant                              | `TenantResolutionTest.php`                                   |
| Cross-tenant čitanje/upis, relacije i cache                          | `TenantIsolationTest.php`, `TenantFoundationTest.php`        |
| Isti email, reset, verifikacija, prenesena sesija i podmetnuta uloga | `TenantAuthenticationTest.php`                               |
| Uloge, obvezni 2FA, jednokratni recovery i rate limit                | `RoleAndTwoFactorTest.php`                                   |
| Pozivnica, deaktivacija sesije i javni profil                        | `StudioAdministrationTest.php`                               |
| Iznimka joba i sljedeći tenant na istom workeru                      | `TenantJobIsolationTest.php`                                 |
| Lotus → Balance → Lotus na istom Node procesu                        | `PublicStudioSsrTest.php`                                    |
| Demo seed i lokalni email                                            | `LocalDemoSeederTest.php`, `LocalAuthenticationMailTest.php` |

### Pregled rezultata na GitHubu

U repozitoriju otvori **Actions**, lijevo odaberi **tests**, zatim izvršavanje
za odgovarajući commit (M1-13 uveden je commitom `41d04f2`, `Add github actions`).
Otvori posao **ci** i njegove korake. Za uspješan M1-13 trebaju proći
`Setup Application`, provjera seeda i `Run CI Checks`; samo zeleni checkout
nije dovoljan. U `Run CI Checks` provjeri zbirni rezultat testova i uspjeh
lint/typecheck provjera. Lokalna referenca je 273 prolaza, 12 ranijih scaffold
skipova i 2088 assertiona. Ako izvršavanje padne, otvori prvi neuspjeli korak
i pronađi stvarnu grešku iznad završnog `exit code 1`.

Popis u Actions prikazuje izvršavanja vezana uz commitove, a ne samo Git
povijest. Detalji postupka su u
[GitHub dokumentaciji](https://docs.github.com/en/actions/how-tos/monitor-workflows/view-workflow-run-history).

### Dijagnostika Composer autoloadera na Windowsu

Za ponovno generiranje autoloadera bez nadogradnje paketa:

```powershell
composer dump-autoload --optimize --profile
```

Provjera 8. listopada 2026.: postojeći projekt prolazi za 6,7 s, uključujući
Laravel package discovery. Nova instalacija iz lockfilea u odvojenoj kopiji
pod radnim direktorijem prolazi za 43,9 s (154 paketa). Ista kopija u Windows
`%TEMP%` pokazuje izrazito sporije čitanje datoteka: uzorak 174 PHP datoteke
čita se 0,132 s na D:, a 9,829 s u `%TEMP%`. Dijagnostičko praćenje potvrđuje
da skeniranje napreduje kroz pakete, iako dugo nema standardnog ispisa.

Projekt i čiste probne instalacije drži u radnom direktoriju na D:, umjesto
u `%TEMP%`. Nije utvrđen točan OS uzrok sporijeg čitanja; antivirus nije
potvrđen kao uzrok. Nisu potrebne nadogradnje, isključivanje zaštite ni
brisanje postojećeg `vendor` direktorija radi ovog nalaza. Upozorenje za
testni `TenantIsolationProbeJob` izvan PSR-4 putanje nije fatalno i pojavljuje
se i pri uspješnom generiranju.

Stvarni rezultati i ograničenja nalaze se u
[napretku M1-13](docs/progress.md#m1-13--integracijska-provjera-i-ci).
Prethodni ručni pregled Home/About obaju dizajna na 375/768/1440 px,
tipkovnice i reduced-motion dokumentiran je uz
[snimke M1-10](docs/vizualni-pregled-m1.md#m1-10--javni-demo-dizajni-7-listopada-2026).
Layout i animacije u M1-13 nisu mijenjani. Dvanaest ranije isključenih
single-tenant scaffold testova (`Settings/*`, `DashboardTest.php`) ostaje
preskočeno; tenant dashboard i sigurnosni tokovi imaju zasebne aktivne testove.
To nije tvrdnja da su sve scaffold funkcionalnosti vraćene.

## Demonstracija i predaja — M1-14

Datum predaje dokumentacije: 8. listopada 2026. Ovo je lokalni razvojni demo.
Postupak ispod je uputa za ponavljanje; nije zapis da je cijeli scenarij
ručno izvršen. Stvarni rezultati i otvorene stavke vode se u
[progressu](docs/progress.md#m1-14--demonstracija-i-predaja) i
[backlogu](docs/razvojni-backlog-m1.md#pregled-prihvata--8-listopada-2026).

### Priprema demonstracije

- Za novu instalaciju slijedi [prvo pokretanje](#prvo-pokretanje), uključujući
  migracije i `LocalDemoSeeder`. Za postojeću instalaciju pokreni lokalne
  servise i `composer dev`; ne ponavljaj `composer setup` jer mijenja APP_KEY.
- Provjeri [hosts zapis i port](#hosts-i-izravni-razvojni-pristup). Standardni
  lokalni origin je `http://platform.yoga.test:8000`; Lotus i Balance koriste
  isti protokol i port, s hostnameovima `lotus.yoga.test` i `balance.yoga.test`.
- Koristi [demo račune](#demo-seed-i-lokalne-domene--m1-12). Na postojećoj bazi
  njihove lozinke ili 2FA mogu već biti promijenjeni; seed ih ne resetira.
  Platform admin i Lotus owner moraju završiti
  [stvarni 2FA enrolment](#lokalna-demonstracija-enrolmenta).
- Pripremi obični prozor za administratore i zasebni privatni prozor za
  managera/polaznika. Tabovi na istoj domeni u istom profilu dijele sesiju.
  Za javni prikaz dovoljne su zasebne kartice s dvije naslovnice.
- Zapiši početni Lotus kontakt email i Balance kontakt email. Koristi samo
  demo podatke. Promjena javnog profila odmah se objavljuje i ostavlja audit.

### Scenarij i očekivani rezultati

1. **Platform admin vidi studije.** Na platform domeni otvori
   `/platform/login`, prijavi se kao `platform@example.test`, dovrši 2FA i
   otvori `/platform/tenants`. Registar mora sadržavati Lotus i Balance,
   njihove domene i različite dizajne. Na svježem seedu postoje dva studija;
   postojeća baza može sadržavati dodatne zapise, pa ih ne briši radi demoa.
2. **Dva javna dizajna.** Otvori `/` i `/about` na Lotus i Balance domeni.
   Provjeri naziv, kontakt i kompoziciju: Lotus je svijetli editorial dizajn,
   Balance tamni geometrijski. Obje domene koriste istu aplikaciju.
3. **Owner objavljuje kontakt.** Na Lotus `/login` prijavi se kao
   `owner.lotus@example.test`. Nakon 2FA otvori **Javni profil**
   (`/studio/profile`). Promijeni samo **Kontakt email** u
   `demo.m1-14@example.test` i klikni **Spremi i objavi**. Očekuj potvrdu uspjeha.
4. **Promjena je izolirana.** Osvježi obje javne naslovnice. Lotus mora
   prikazati novi email, a Balance svoj prethodni email. Povratak kroz
   Home/About navigaciju mora zadržati odgovarajući studio i dizajn.
5. **Manager nema pravo uređivanja.** U zasebnom prozoru na Lotus domeni
   prijavi se kao `manager.lotus@example.test`. `/dashboard` je dostupan,
   ali izravan odlazak na `/studio/profile` mora vratiti **403**.
   Zabranu stvarnog PATCH upisa, uz provjeru da podaci nisu promijenjeni,
   ponovi testom navedenim ispod; skrivena poveznica sama nije dokaz zaštite.
6. **Isti email, odvojeni računi.** Odjavi managera. Na Lotus `/login`
   prijavi `customer@example.test` lozinkom `Lotus-Demo-2026!`, a na Balance
   `/login` isti email lozinkom `Balance-Demo-2026!`. Na `/account` provjeri
   vlastiti identitet i pripadajući studio. Odjavi oba računa i jednom pokušaj
   Lotus lozinku na Balanceu: očekuj odbijenu prijavu. To vrijedi za izvorne
   demo lozinke; ako su promijenjene, koristi aktualne lozinke tih računa.
7. **Tuđi podatak je odbijen.** Pokreni niže navedeni test poznatog stranog
   ID-ja. On stvara dva izolirana fixture studija i očekuje **404** i za
   čitanje i za upis Balance profila pod Lotus kontekstom, uz nepromijenjen
   Balance zapis. Ruta `/_test/profiles/{profile}` postoji samo unutar testa;
   nije demo URL. Dodatni test osoblja provjerava stvarnu aplikacijsku rutu
   i odbijanje izmjene djelatnika drugog studija.
8. **Vrati demo kontakt.** Kao Lotus owner vrati izvorni kontakt email,
   spremi i osvježi obje naslovnice. Audit promjene i vraćanja ostaje.
   Odjavi demo račune; ne spremaj sesije, TOTP tajne ili recovery kodove u
   screenshotove ili repozitorij. Ponovljeni seed nije način vraćanja profila.

Sigurnosni dio demonstracije, iz korijena projekta uz pokrenut testni MySQL:

```powershell
php artisan test --compact tests/Feature/StudioAdministrationTest.php --filter="manager instructor and customer cannot manage profile staff or invitations"
php artisan test --compact tests/Feature/TenantIsolationTest.php --filter="known foreign profile IDs return 404 for both reading and writing"
php artisan test --compact tests/Feature/StudioAdministrationTest.php --filter="owner changes staff roles but cannot modify owners customers or foreign staff"
```

Za objedinjenu regresijsku provjeru demoa:

```powershell
php artisan test --compact tests/Feature/LocalDemoSeederTest.php tests/Feature/StudioAdministrationTest.php tests/Feature/TenantIsolationTest.php tests/Feature/TenantAuthenticationTest.php tests/Feature/PlatformTenantTest.php
```

`phpunit.xml` prisilno koristi zasebni `surya_testing` MySQL na 3308;
testovi resetiraju tu testnu bazu. Platform test uključuje lokalni Mailpit.
Ovaj skup ne provjerava browser izgled ni živi SSR; za njih vidi
[vizualne dokaze](docs/vizualni-pregled-m1.md) i
[integracijski postupak](#integracijska-provjera-i-ci--m1-13).

Nakon ručnog prolaza zabilježi u `docs/progress.md` datum, commit, preglednik,
izvršene korake, očekivani/stvarni rezultat i preostale greške. Ne označavaj
neizvršeni korak prolaznim. Svako uočeno curenje podataka blokira prihvat M1.

### Tehničke odluke i granice isporuke

| Odluka                                  | Razlog i mjesto u projektu                                                                                                                                         |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Jedna aplikacija i zajednički MySQL     | Modularni monolit bez instalacije po studiju; [mapa tenant/globalnih tablica](#podatkovni-temelj--m1-02).                                                          |
| Tenant dolazi iz verificiranog hosta    | Nema zadane studio domene niti povjerenja u klijentski `tenant_id`; [resolver](app/Http/Middleware/ResolveTenantFromHost.php) i [kontekst](app/TenantContext.php). |
| Više slojeva izolacije                  | Scope, binding, policies, DB constraintovi te cleanup cache/job konteksta; [provedba](#izolacija-podataka-cachea-i-jobova--m1-04).                                 |
| Platform identitet je zaseban           | Bez implicitnog support pristupa studiju ili cross-domain SSO-a; [sažeta matrica prava](#uloge-i-2fa--m1-06).                                                      |
| Obvezni 2FA ostaje i u demou            | Fortify enrolment za ownera/platform admina, bez poznate seed tajne ili bypassa.                                                                                   |
| Fiksni javni profil i dopušteni dizajni | Odmah objavljena sigurna polja, zajednički DTO i eksplicitni registar; nema proizvoljnih Vue putanja ni CMS draftova.                                              |
| SSR javnih stranica uz noindex demoa    | Metadata iz pouzdane domene; [fallback i ograničenja](#ssr-seo-i-granice-hostova--m1-11).                                                                          |
| Lokalni servisi i zaključane ovisnosti  | MySQL/Mailpit u Composeu, PHP/Node na hostu; nema produkcijskog SES-a, Bunnyja, Fathoma ni naplata.                                                                |

Poznata ograničenja pri predaji:

- **Otvoreni prihvat:** puni svježi setup s bazom i frontendom, GitHub CI run
  odgovarajućeg commita i preostali admin/auth vizualni tokovi. Pojedinačni
  zadaci i dokazi navedeni su u backlogu; dokumentacija ih ne zatvara.
- **Račun korisnika:** vlastiti profil služi pregledu, odjava i reset lozinke
  rade; scaffold uređivanje/brisanje profila i settings promjene ostaju
  zatvoreni. Dvanaest starih scaffold testova je preskočeno. Stavka matrice
  o vlastitoj lozinci zato nije potpuni prihvat uređivanja kroz settings.
- **Operativa:** opcionalni Nginx primjer nije izvršno provjeren; nema
  produkcijskog TLS-a, deploya, monitoringa ili potvrđenog restore postupka.
  Ručna verifikacija domene ne konfigurira infrastrukturu.
- **Browseri i jezik:** prethodni QA je Chromium na emuliranim širinama;
  Safari/Firefox, fizički slabiji mobitel i potpuni accessibility audit nisu
  potvrđeni. Dio auth sučelja i validacija još je na engleskom; HR usklađenje
  ostaje otvoreno. Bez SSR-a i JavaScripta javni body nije dostupan.
- **Izvan M1:** raspored, booking, krediti, članarine, naplate/fiskalizacija,
  videoteka, analitika, potpuni CMS, import/export i produkcijski onboarding.
  Šira specifikacija opisuje budući opseg, ne trenutačne funkcionalnosti.

Sljedeći korak je ponoviti ovaj demo i zatvoriti otvorene prihvate prije
proglašenja M1 dovršenim. Poslovni moduli zahtijevaju zasebno zadan opseg.

## Projektne upute

- [AGENTS.md](AGENTS.md)
- [Specifikacija](docs/specifikacija-platforme-v1.md)
- [Backlog M1](docs/razvojni-backlog-m1.md)
- [Napredak i rezultati provjera](docs/progress.md)

Git ima remote `origin` za `rmaretic/surya-platform`. `.env`, lokalni agent
config, ovisnosti i build artefakti se ne
verzioniraju. AGENTS.md je uključen u verzioniranje kao zajednička uputa.
