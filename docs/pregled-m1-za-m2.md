# M2-01 — pregled M1 i mapa nastavka

Datum: 8. listopada 2026. Status: M2-01 dovršen za opseg pregleda.
Opseg: pregled postojećeg koda, sheme i provjera
prema odjeljcima 5, 25 i 29 specifikacije. Novi M2 moduli nisu implementirani.

## Polazište

Pročitani su `AGENTS.md`, specifikacija, oba backloga, README i progress.
Direktorij `.ai/rules` ne postoji. Početni Git status sadržavao je samo
korisnikov nepraćeni `docs/razvojni-backlog-m2.md`.

Instalirane verzije potvrđene su s `composer show --direct`, `npm ls`
i manifestima: PHP 8.5.11, Laravel 13.34.0, Fortify 1.40.0,
Inertia Laravel 3.5.1, Inertia Vue/Vite 3.8.0, Vue 3.5.43,
Vite 8.3.2, TypeScript 5.9.3, Tailwind 4.3.3, Pest 5.3.0.
Node je 24.21.0; oba lokalna MySQL servisa koriste 8.4.11.
Ovisnosti i lock datoteke nisu mijenjani.

M1 nije potpuno prihvaćen prema vlastitoj
[matrici prihvata](razvojni-backlog-m1.md#pregled-prihvata--8-listopada-2026).
Ovaj pregled odvojeno ocjenjuje tehnički temelj za M2 i preostali ručni
prihvat M1; ne proglašava cijeli prethodni milestone dovršenim.

## Postojeći modeli i tablice

Mapa je provjerena u `app/Models`, migracijama i stvarnoj MySQL shemi
preko Boost `database-schema`.

| Model / tablica                                                            | Granica i namjena                                                                                              | Nastavak u M2                                                                                                               |
| -------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `Tenant` / `tenants`                                                       | Globalni registar: naziv, status, dopušteni dizajn                                                             | Zadržati identitete; postojeći red studija može biti koordinacijski lock iz M2-07. Zona i poslovne postavke još ne postoje. |
| `TenantDomain` / `tenant_domains`                                          | Tenant model; globalno jedinstven normalizirani hostname, jedna primarna domena, status/verifikacija           | Zadržati server-side odabir konteksta; M2 ne uvodi klijentski odabir tenanta.                                               |
| `TenantProfile` / `tenant_profiles`                                        | Jedan javni kontaktni profil po studiju                                                                        | Zadržati javni ugovor; poslovna pravila i raspored nisu polja javnog profila.                                               |
| `User` / `users`                                                           | Tenant identitet, jedna uloga, aktivnost, auth/2FA; unique `(tenant_id, normalized_email)` i `(tenant_id, id)` | Novi instructor profil povezati s postojećim korisnikom istog studija. Owner zadržava owner ulogu i kada podučava.          |
| `StaffInvitation` / `staff_invitations`                                    | Poziv ownera/managera/instructora; hash tokena, autor, prihvat, opoziv i isporuka                              | Zadržati tok poziva; profil instruktora ne zamjenjuje identitet ni pozivnicu.                                               |
| `TenantAuditLog` / `tenant_audit_logs`                                     | Tenant audit s autorom iz istog studija                                                                        | Proširiti tipove događaja uz minimalne podatke; kreditni ledger je zaseban zapis.                                           |
| `PlatformAdmin` / `platform_admins`                                        | Odvojen globalni identitet i guard, obvezan 2FA                                                                | Bez impersonationa; M2 izvještaji samo operativni agregati.                                                                 |
| `PlatformAuditLog` / `platform_audit_logs`                                 | Globalni audit s ciljanim studijem i platform autorom                                                          | Zadržati odvojeno od tenant audita.                                                                                         |
| `tenant_sessions`, `tenant_password_reset_tokens`                          | Tenant-aware spremišta bez zasebnih Eloquent modela                                                            | Ne koristiti `sessions` kao naziv treninga.                                                                                 |
| `platform_sessions`, `platform_password_reset_tokens`                      | Odvojena platformska auth spremišta                                                                            | Ne spajati sa studio identitetima.                                                                                          |
| `sessions`, `password_reset_tokens`                                        | Preostale scaffold tablice; trenutačni auth koristi gornja spremišta                                           | Ne prenamjenjivati i ne brisati u M2-01.                                                                                    |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` | Zajednička infrastruktura                                                                                      | Tenant ključevi i eksplicitni job kontekst ostaju obvezni; outbox je novi poslovni zapis.                                   |

`TenantBuilder` je prilagođeni query builder, nije novi poslovni model.
MySQL složeni FK-ovi već štite autore/primatelje pozivnica, tenant audit
i autentificirane sesije od povezivanja s korisnikom drugog studija.

## Pregled sigurnosnih i izvršnih granica

| Područje           | Pregledani kod i zaključak                                                                                                                                                                                                                                                               | Regresijski dokaz                                                                                                           |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Host i kontekst    | `ResolveTenantFromHost`, `NormalizeHostname`, `TrustExplicitProxies`, `EnsureDomainArea`, `bootstrap/app.php`: samo aktivna verificirana domena; nepoznat host nema fallback; resolver prethodi authu/bindingu i čisti kontekst u `finally`.                                             | `TenantResolutionTest`, `NormalizeHostnameTest`                                                                             |
| Upiti i veze       | `TenantScope`, `BelongsToTenant`, `TenantBuilder`: bez konteksta iznimka; scope vrijedi i za binding/relacije; zaštita vlasništva pri save/delete, refresh i restoration. Raw SQL i bulk insert/upsert zahtijevaju vlastitu provjeru i FK-ove.                                           | `TenantIsolationTest`, `TenantFoundationTest`                                                                               |
| Auth i tokeni      | `AuthenticationServiceProvider`, `ConfigureAuthentication`, `BindSessionIdentity`, `IsolatedSessionGuard/Handler`, `TenantTokenRepository`, `TrustedAuthUrl`: odvojeni guardovi/spremišta, host-only cookie, aktivni korisnici, URL iz verificirane konfiguracije i tenant reset tokeni. | `TenantAuthenticationTest`, `Auth/*`, `TenantAuthenticationBoundaryTest`                                                    |
| Ovlasti i 2FA      | `AdministrativeAccess`, tri policies, `EnsureTwoFactorEnrolled`, `ProtectTwoFactorAuthentication`, `TwoFactorReplayCache`: backend provjera uloge/konteksta, obvezan potvrđeni 2FA ownera/platform admina, throttling i izolirana zaštita ponavljanja koda.                              | `RoleAndTwoFactorTest`, `PlatformTenantTest`, `StudioAdministrationTest`                                                    |
| Cache i queue      | `TenantCache` uključuje tenant u ključ; `UseTenantContext` prima ID, provjerava aktivan studio i čisti kontekst nakon uspjeha/iznimke. Produkcijski booking jobovi još ne postoje.                                                                                                       | `TenantIsolationTest`; `TenantJobIsolationTest` koristi isti database worker kroz više tenant jobova, uključujući neuspjeh. |
| Javni podaci i SSR | `DesignRegistry`, `PublicStudioProfile`, `PublicStudioMetadata`, `HandleInertiaRequests`, `resources/js/app.ts`: dopušteni dizajni, eksplicitan javni DTO, javni auth je null, lazy stranice. Inertia v3 koristi zajednički app entry; nema zasebnog `ssr.ts`.                           | `PublicStudioTest`, `PublicStudioSsrTest`: Lotus → Balance → Lotus, escaping i kontrolirani fallback.                       |

Pregledom navedenih tokova nije identificirana nova greška tenant izolacije.
To nije tvrdnja o potpunom sigurnosnom auditu ni o još nepostojećim M2 tokovima.

## Mapa novih modula i mjesta proširenja

Ovo je plan implementacije prema M2 backlogu, ne popis isporučenih modela.
Zadržati `app/Models`, `app/Actions`, controllers, policies, postojeće Vue
layoute i registar; ne stvarati paralelnu aplikaciju ni novu strukturu modula.

| Zadatak  | Novi podaci / ponašanje                                                                                        | Mjesto povezivanja i zaštita                                                                                                                                                 |
| -------- | -------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| M2-02–03 | `training_types`, `instructor_profiles`, `rooms`, verzionirane poslovne postavke; priprema ostalih M2 entiteta | Tenant FK i složene veze na korisnika/instruktora/prostor. Profil vezan na aktivnog `User`; novi policies za raspored, odvojeno od owner-only profila/osoblja.               |
| M2-04    | `schedule_series`, `class_sessions`, generator ponavljanja                                                     | Lokalna zona za pravilo, UTC trenuci za pojavljivanje; unique pojavljivanje; draft/published/cancelled/completed. Lock protokol treba uvesti već uz prve mutacije rasporeda. |
| M2-05    | `availability_rules`, `availability_exceptions`, računanje slobodnih slotova                                   | Isti resursi i bufferi kao grupni raspored; slot ne stvara booking ni pravo.                                                                                                 |
| M2-06    | `package_products`, `credit_grants`, `credit_entries` i veze na dopuštene vrste                                | Owner-only dodjela/korekcija; snapshot, izvor prava, valjanost i append-only ledger. Bez lažnih payment/prihod zapisa.                                                       |
| M2-07–09 | `bookings`, aktivna jedinstvenost, idempotentne operacije, snapshoti, otkaz/move i kompenzacijski grant        | Jedan servis za admin/customer. Najprije lock postojećeg tenant reda, zatim ciljni retci stabilnim redom; booking/debit/audit/outbox u istoj transakciji.                    |
| M2-10    | Javni raspored i portal za Lotus/Balance                                                                       | Proširiti allow-list `DesignRegistry`, javne DTO-ove i zasebne stranice uz zajedničke poslovne ugovore; rute preko Wayfindera.                                               |
| M2-11    | `attendance_records`                                                                                           | Jedan zapis po bookingu, tenant veze na booking i autora; instruktor samo svoji termini; evidencija ne troši kredit.                                                         |
| M2-12    | `outbox_events`, evidencija isporuke/pokušaja, dispatcher i reminders                                          | `UseTenantContext`, scalar ID-jevi, provjera verzije/statusa i dedup; lokalni mail nakon commita. Ne kopirati slanje pozivnice unutar transakcije u booking tok.             |
| M2-13–15 | Agregati, race testovi, proširen idempotentan demo i CI                                                        | Nastaviti postojeće dashboard controllere/layoute, `LocalDemoSeeder` i `.github/workflows/tests.yml`; race dokazi s neovisnim MySQL konekcijama/procesima.                   |

Važne postojeće granice za nastavak:

- `RequireTenantAuthenticationReady` još je allow-list: novi javni raspored
  mora dobiti eksplicitno dopuštenu putanju uz odgovarajuću autorizaciju.
  Ne uklanjati cijelu zaštitu radi otvaranja nove stranice.
- `studioAccess.manage` sada znači owner upravljanje profilom/osobljem.
  Managerovo M2 pravo na raspored treba zasebnu backend sposobnost i UI
  zastavicu; ne širiti postojeći `UserPolicy` ili `TenantProfilePolicy`.
- `ManageStudioStaff::deactivate` trenutačno opoziva sesije, ali nema budućih
  termina koje bi provjeravao. M2-03 mora povezati deaktivaciju instruktora
  s provjerom rasporeda i zajedničkim lockom.
- `tenants` nema zonu, valutu ni pravila bookinga. Radne default vrijednosti
  M2 ostaju razvojni prijedlozi, a postojeći zapisi zahtijevaju eksplicitan
  backfill; cijene su integer centi EUR, krediti integer.
- Osobni settings ostaju namjerno zatvoreni s 503; 12 scaffold testova za
  settings/dashboard preskočeno je u `tests/Pest.php`. To je postojeći M1
  nedovršeni opseg, ne novi dokaz prolaza.

## Migracije i očuvanje podataka

M2-01 ne mijenja shemu ni razvojne podatke. `migrate:status` potvrđuje svih
šest postojećih migracija kao primijenjene; nije pokrenut razvojni seed,
rollback ili `migrate:fresh`. Testovi koriste isključivo zasebnu bazu
`surya_testing` na portu 3308 prema prisilnoj konfiguraciji `phpunit.xml`.
Njihov postojeći `RefreshDatabase` smije ponovno izgraditi samo tu testnu bazu.

M2-02 treba dodavati forward migracije, bez izmjene već primijenjene
foundation migracije. Ona odbija instalaciju nad starim nepovezanim users
zapisima i rollback kada postoje studiji/platform admini. Pri proširenju
zadržati IDs, domene, auth i audit; nove obvezne vrijednosti dodavati uz
backfill prije NOT NULL/constrainta. Testirati nad M1 fixtureima da migracija
čuva korisnike, profile, pozivnice i sigurnosna stanja, te FK-ove na MySQL-u.
Povijest rezervacija/ledgera mora preživjeti arhiviranje; bez kaskadnog brisanja.

## Rezultati provjera

| Provjera                                                                                                        | Stvarni rezultat 8. listopada 2026.                                                                                                                                                                 |
| --------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer show --direct`, `npm ls … --depth=0`, `php --version`                                                 | Potvrđene gore navedene instalirane verzije.                                                                                                                                                        |
| `docker compose ps`                                                                                             | Razvojni MySQL, zasebni testni MySQL i Mailpit zdravi.                                                                                                                                              |
| `php artisan route:list --except-vendor --no-interaction`                                                       | 35 aplikacijskih ruta; auth rute dodatno pregledane u `routes/auth.php`.                                                                                                                            |
| `php artisan migrate:status --no-interaction`                                                                   | Svih šest migracija primijenjeno; bez upisa/migriranja razvojne baze.                                                                                                                               |
| `composer types:check`                                                                                          | PHPStan prolazi, 0 grešaka.                                                                                                                                                                         |
| `npm run types:check`                                                                                           | Vue/TypeScript prolazi.                                                                                                                                                                             |
| `npm run build`                                                                                                 | Client i SSR prolaze izvan sandboxa; prvi pokušaj blokiran s `spawn EPERM`. Postojeća upozorenja: opcionalni Fontaine i Inertia sourcemape.                                                         |
| `SSR_TEST_URL` postavljen na lokalni Node pa `php artisan test --compact`                                       | 285 ukupno: 272 prolazi, 1 SSR pad, 12 postojećih skipova; 2004 assertiona, 89,6 s. Uključeni MySQL constraintovi, tenant/auth/2FA/policies, cache, isti queue worker, demo seed i lokalni Mailpit. |
| Nakon restarta Nodea: `php artisan test --compact tests/Feature/PublicStudioSsrTest.php` uz isti `SSR_TEST_URL` | Svih 17 prolazi, 263 assertiona, 4,7 s; uključeni stvarni SSR izolacija/escaping testovi.                                                                                                           |
| `npm run check`                                                                                                 | Ne prolazi: postojeći format korisnikova M2 backloga. Dokument nije masovno preformatiran; ovo nije čist ukupni format/lint prolaz.                                                                 |
| `npm run check -- --no-fmt`                                                                                     | Lint svih 83 datoteke prolazi bez upozorenja i grešaka.                                                                                                                                             |

Uzrok SSR pada bio je već pokrenuti Node sa starim lazy importom za
`sites/balance/Home`; novi build uklonio je stari hash datoteke.
`/health` je vraćao OK iako taj render nije mogao uspjeti. Lokalni Node
restartan je nakon builda i zahvaćena zbirka ponovno je prošla. Nije mijenjan
SSR kod ni test da bi se prikrio kvar. Puna zbirka nakon restarta nije
ponovljena; ne prikazivati prvi pokušaj kao 273 prolaza.

Sandbox je blokirao pristup Dockeru/Node spawn; te su provjere dovršene
izvan sandboxa. Pokušaj health provjere bez mrežnog pristupa prekinut je
prije testova. Nisu slani emailovi stvarnim primateljima: mail test koristi
lokalni Mailpit i jedinstvenu `example.test` adresu.

Dokumentacija je dovršena 9. listopada 2026.: README, progress i ovaj
izvještaj formatirani su projektnim formatterom; `git diff --check` prolazi.
Prethodni pokušaj formatiranja bio je blokiran limitom automatske provjere
odobrenja. Testni rezultati iz tablice ostaju rezultati od 8. listopada.

## Preostali prihvat i sljedeći korak

Ručni puni auth/email/QR/recovery i admin demo, svježi cjeloviti setup,
GitHub Actions run i opcionalni Nginx ostaju otvoreni prema M1 matrici.
Ovim pregledom nije ponovljen browser/hydration/mobilni QA niti uvedena
produkcijska integracija. Postojeći SSR fallback bez JavaScripta nema javni
body; to je dokumentirano ograničenje.

Sljedeći razvojni zadatak je M2-02: aditivni modeli, veze, constraintovi i
test očuvanja M1 podataka. Konkretna prava/raspored dolaze u M2-03 nadalje;
konkurentni booking testovi pripadaju M2-07/M2-14. Ručni prihvat M1 ostaje
zasebno otvoren i ne smije se izgubiti prelaskom na M2.
