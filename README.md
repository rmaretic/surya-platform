# Surya

Laravel + Vue + Inertia platforma za yoga studije. Trenutačni inkrement je
M1-10: dva različita javna dizajna, lokalne ilustracije i pristupačne animacije.
Studio administracija, javni profil i osoblje implementirani su u M1-08;
njihov potpuni vizualni prihvat još je otvoren.

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
Preostalih 12 scaffold testova za settings i dashboard čeka M1-08.
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
zatraži verifikacijski email i otvori ga u Mailpitu. Nije unaprijed kreiran
razvojni admin ni zajednička lozinka. Za stvarni račun koristi vlastiti email.
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
Platform registar i početna owner pozivnica dostupni su u M1-07.
Obrasci profila i upravljanje ostalim osobljem slijede u M1-08.
`/dashboard` i `/portal` trenutačno prikazuju minimalnu stranicu računa;
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

Registar dizajna u M1-07 ograničava izbor i promjenu metapodatka `design_key`.
Mapiranje na individualne javne Vue frontendove ostaje opseg M1-09/M1-10.
Javna stranica zasad ostaje postojeći neutralni prikaz.

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
  platforme ostaje operativni identitet studija. Javna stranica zasad koristi
  neutralni prikaz; zasebni dizajni slijede u M1-09/M1-10.
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

## Projektne upute

- [AGENTS.md](AGENTS.md)
- [Specifikacija](docs/specifikacija-platforme-v1.md)
- [Backlog M1](docs/razvojni-backlog-m1.md)
- [Napredak i rezultati provjera](docs/progress.md)

Git je lokalno inicijaliziran na grani `main`; remote i prvi commit nisu
postavljeni. `.env`, lokalni agent config, ovisnosti i build artefakti se ne
verzioniraju. AGENTS.md je uključen u verzioniranje kao zajednička uputa.
