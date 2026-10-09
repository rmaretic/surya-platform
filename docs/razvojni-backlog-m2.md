# M2 — raspored, paketi, rezervacije i dolasci

Datum: 7. listopada 2026.  
Izvor: specifikacija-platforme-v1.md (v1.6) i razvojni-backlog-m1.md.  
Status (9. listopada 2026.): M2-01 pregledan i dokumentiran; M2-02 implementiran i provjeren na MySQL-u. Potpuni M1 prihvat ostaje otvoren prema njegovoj matrici. M2-03–M2-15 još nisu implementirani.

## Cilj i granica faze

Studio uređuje grupni raspored i privatnu dostupnost, dodjeljuje pakete s evidentiranim izvorom prava, a polaznik rezervira uz važeći kredit. Otkaz, premještanje i ručni dolasci rade na oba postojeća frontend dizajna. Admini prikazuju stvarne operativne podatke.

M2 razrađuje odjeljke 9–15, 18 i 25–28 specifikacije. To nije cijeli opseg A: članarine, stalne grupe, Meet i videoteka ostaju potrebni prije punog lansiranja.

Uključeno: vrste, instruktor profili, prostori, grupni termini/ponavljanje, privatni slotovi, paketi, kreditni ledger, booking, otkaz/move, dolasci, lokalne obavijesti i operativni dashboard.

Izvan M2: Stripe checkout i holdovi, online prodaja, fiskalni dokumenti i refund novca, auto-renew članarine, stalni upisi u grupe, lista čekanja, QR, Meet/Calendar, Bunny i Fathom integracije, SES produkcijsko slanje, marketing, više lokacija, hibridni termini i puni CMS.

**Nema neplaćenog self-service bookinga.** Kredit u M2 potječe iz demo seeda, dokumentirane vanjske kupnje već riješene izvan platforme ili eksplicitne besplatne dodjele ownera. Izvori su odvojeno označeni. Vanjska kupnja zahtijeva referencu računa/evidencije, autora i vrijeme; besplatna dodjela razlog. Ne stvarati lažne payment zapise, račune ni prihod. Admin booking troši isto pravo kao korisnički. Polaznik bez kredita vidi kontakt studija, ne funkcionalno lažni checkout. M2 nije gotov sustav za online prodaju.

## Radne zadane vrijednosti

Ovo su konfigurabilni prijedlozi za razvoj i demo, koje treba potvrditi prije stvarne prodaje.

| Tema | Pravilo |
|---|---|
| Zona | Europe/Zagreb; trenuci UTC, ponavljanje po lokalnom vremenu |
| Grupni trening | Zadano 60 min, jedan instruktor/prostor, podesiv kapacitet |
| Privatni trening | 60 min, buffer 15 min prije/poslije, slot korak 30 min; podesivo |
| Booking prozor | Najmanje 2 sata i najviše 56 dana unaprijed; podesivo |
| Serija | Tjedni dani/vrijeme, početak/opcionalni kraj; generirati horizont 8 tjedana |
| Pravovremeni otkaz | Najkasnije 12 h prije grupnog ili 24 h prije privatnog treninga |
| Kasni otkaz/no-show | Kredit ostaje potrošen; bez dodatne kaznene naplate |
| Demo paketi | 5 grupnih/45 dana, 10 grupnih/90 dana i odvojeni privatni paket |
| Valjanost | Od dodjele; kalendarski dani u zoni studija; početak treninga u [valid_from, expires_at) |
| Potrošnja | Jedan termin = jedan kredit paketa koji pokriva vrstu treninga |
| Odabir paketa | Najraniji valjani istek; ID razrješava izjednačenje |
| Otkaz studija | Povrat kredita s najmanje 7 dana preostale valjanosti samo za vraćeni kredit |

Booking sprema snapshot pravila. Promjena postavki nije retroaktivna. Korisnički pravovremeni otkaz vraća izvorni kredit s izvornim istekom; ako je istekao, nije ponovno raspoloživ. Za studijsku kompenzaciju po potrebi izdati zaseban grant, ne produljivati nepovezane kredite.

## Ovlasti

Owner i manager uređuju raspored, prostore i vrste, rade ručni booking i otkaz treninga. Samo owner uređuje cijene/pravila, dodjeljuje i korigira kredite te odobrava povrat mimo pravila uz razlog. Instruktor vidi minimalne podatke polaznika i vodi dolaske samo svojih termina. Customer vidi i mijenja samo vlastite rezervacije. Platform admin vidi operativne agregate po studijima, bez osobnih popisa i impersonationa.

Instruktor profil vezan je uz aktivnog tenant korisnika. Owner koji drži trening može imati instructor profil bez promjene owner uloge. Profil ne daje dodatne administrativne ovlasti. Sva prava provjerava backend, ne samo UI.

## Isporuke

1. M2-A: M2-01–M2-05 — model i raspored.
2. M2-B: M2-06–M2-09 — kreditna prava i booking.
3. M2-C: M2-10–M2-13 — korisnički tokovi, dolasci i dashboard.
4. M2-D: M2-14–M2-15 — završna provjera i predaja.

Testove pisati uz zadatke. M2-14 nije prvo testiranje.

## M2-01 — pregled M1

**Ovisnost:** M1. **Veza:** specifikacija 5, 25, 29.

**Status (8. listopada 2026.):** dovršeno za opseg pregleda. [Mapa modela, novih modula, migracija i stvarni rezultati provjera](pregled-m1-za-m2.md). Nije pronađena nova greška tenant izolacije. Stari SSR proces restartan je nakon builda; svih 17 SSR testova zatim prolazi. Preostali M1 prihvati i postojeći format backloga izričito su navedeni u izvještaju.

Pregledati AGENTS.md, verzije, tenant resolver, auth/2FA, policies, queue, SSR i testove. Pokrenuti relevantne M1 provjere. Napisati mapu postojećih modela i novih modula. Ne scaffoldati novu aplikaciju niti prepisivati dva dizajna.

**Prihvat:** dokumentirana mapa izmjena i stvarni rezultati provjera. Greške izolacije riješene prije ovisnih tokova. Migracije čuvaju postojeće podatke; bez migrate:fresh nad korisnikovom bazom.

## M2-02 — modeli i constraintovi

**Ovisnost:** M2-01. **Veza:** 12–15, 25.

**Status (9. listopada 2026.): dovršeno.** Dodano 14 modela, factoryji, testni seeder i dvije aditivne migracije: [raspored](../database/migrations/2026_10_09_130203_create_scheduling_foundation.php) i [prava/rezervacije](../database/migrations/2026_10_09_130204_create_credit_and_booking_foundation.php). [MySQL constraint i model testovi](../tests/Feature/BookingFoundationTest.php) i [očuvanje M1 podataka](../tests/Feature/M2MigrationTest.php), zajedno s postojećim tenant regresijama: **160 prolazi, 516 assertiona**. PHPStan, Pint i diff provjera prolaze. Migracije su primijenjene i na lokalnu razvojnu bazu uz nepromijenjen broj M1 zapisa. [Ugovori, naredbe i ograničenja](../README.md#podatkovni-temelj-rasporeda-i-rezervacija--m2-02). Nema novih korisničkih radnji ni produkcijskih integracija; M2-03 slijedi.

Dodati/prilagoditi training_types, instructor_profiles, rooms, schedule_series, class_sessions, availability_rules/exceptions, package_products, credit_grants/entries, bookings, attendance_records, verzije pravila i outbox. Imena prilagoditi postojećem kodu; tenant constraintovi obvezni.

Termini: draft/published/cancelled/completed. Booking: confirmed/cancelled s odvojenim razlogom. Attendance: pending/present/no_show. Pravo nije booking; attendance nije debit; payment status nije booking status. Cijene u centima EUR, krediti integer.

Jedan aktivan booking po polazniku/terminu zaštititi MySQL-kompatibilnim constraintom ili zasebnim aktivnim jedinstvenim zapisom. Sačuvati povijest otkaza i ponovne rezervacije. Ne oslanjati se na PostgreSQL partial index.

**Prihvat:** migracije/testovi na MySQL-u; tuđi instruktor/prostor/grant/korisnik ne mogu biti povezani. Arhiviranje ne briše povijest i ledger.

## M2-03 — katalog i pravila

**Ovisnost:** M2-02. **Veza:** 9, 12, 13.

Admin uređuje vrste, trajanja, kapacitete, prostore i instruktore. Jedna lokacija, više prostorija dopušteno. Owner uređuje cijene i verzionirane rokove. Validirati pozitivne vrijednosti i granice. Deaktiviranje instruktora s budućim terminima traži zamjenu/otkaz.

**Prihvat:** postavke studija odvojene; promjena pravila ne mijenja stare bookinge. Manager ne može mijenjati owner-only polja izravnim HTTP zahtjevom. Javnim DTO-ovima ne izlagati privatne podatke osoblja.

## M2-04 — grupni raspored i serije

**Ovisnost:** M2-03. **Veza:** 12.1, AC06, AC12.

Pojedinačni termin i tjedna serija; generator idempotentan uz unique ključ pojavljivanja. Otkazani termin ne generira se ponovno. Lokalni sat određuje ponavljanje, UTC instanti stvarno trajanje.

Za nepostojeće/dvostruko DST vrijeme tražiti eksplicitni ispravak pojavljivanja, bez tihog odabira offseta. Uređivati jedan ili ovaj i buduće nerazervirane termine. Za rezervirane termine promjena vremena/trajanja/usluge ide kroz otkaz/zamjenu; zamjena instruktora/prostora dopuštena bez konflikta uz obavijest.

**Prihvat:** ponedjeljak 18:00 ostaje lokalno 18:00 preko DST-a. Nema duplih pojavljivanja, konflikta resursa niti smanjenja kapaciteta ispod confirmed broja. Sve mutacije rasporeda koriste protokol zaključavanja iz M2-07, uključujući generator.

## M2-05 — privatna dostupnost

**Ovisnost:** M2-04. **Veza:** 12.2, AC06.

Tjedna dostupnost, iznimke, trajanje, korak, buffer, najava i horizont. Oduzeti zauzeće grupnih i privatnih termina te prostora. Slot je prijedlog, ne potvrda. Autoritativni booking ponavlja provjere i stvara konkretni termin i booking u jednoj transakciji.

Zaštićen interval je [start-buffer_before, end+buffer_after); susjedni jednaki rubovi dopušteni. Retry identificirati stabilnim operation keyjem. Promjena dostupnosti ne briše postojeće termine.

**Prihvat:** prikaz slotova poštuje sve resurse i buffere; konfliktna izmjena dostupnosti odbijena. Race test dva nova privatna upisa dovršiti u M2-07. Vanjska Google zauzetost nije pretpostavljena.

## M2-06 — grantovi i kreditni ledger

**Ovisnost:** M2-03. **Veza:** 15.2, AC07.

Owner dodjeljuje demo/external_purchase/complimentary grant s izvorom i snapshotom broja, valjanosti i vrsta. Promjena proizvoda ne mijenja prethodno pravo. Append-only ledger bilježi dodjelu, debit pri potvrdi, reversal i korekciju s jedinstvenom poslovnom referencom.

Saldo proizlazi iz ledgera; projekciju ako postoji uskladiti. Istek se računa preko valjanosti granta, bez destruktivnog periodičnog brisanja salda. Attendance ne stvara novi debit. Korekcije imaju razlog i ne smiju oduzeti kredite već potrebne potvrđenim bookingima.

**Prihvat:** isti operation key ne ponavlja dodjelu/debit/povrat. Nema negativnog raspoloživog salda. Raspoloživi, rezervirani i istekli krediti razlikuju se. Vanjska kupnja/promo nisu prikazani kao prihod naplaćen platformom.

## M2-07 — atomski booking

**Ovisnosti:** M2-04–M2-06. **Veza:** 13, AC05–AC07.

Jedan servis za customer/admin provjerava tenant, ovlasti/aktivnost, objavu, rok, kapacitet, korisnikove preklopljene rezervacije i grant. Transakcija zapisuje booking, debit, snapshot i outbox. Email/vanjski API nisu u DB transakciji.

Za mali početni volumen koristiti dokumentirani jednostavni protokol: postojeći tenant coordination red zaključati FOR UPDATE za sve mutacije rasporeda/kapaciteta/bookinga/kredita, zatim ciljne retke stabilnim redoslijedom. To serijalizira kratke konfliktne radnje istog studija. Različiti studiji ne dijele lock. Lock samo postojećeg bookinga nije dovoljan za nove intervale. Deadlock retry ograničen i idempotentan; granularnije zaključavanje kasnije uz iste testove.

**Prihvat:** zadnje mjesto/kredit dobiva najviše jedan zahtjev. Dva nova privatna intervala i grupni/privatni upis ne mogu preklopiti resurse. Neuspjeh ne troši kredit. Podmetnut tenant/user/grant odbijen. Duplikat iste operacije vraća isti rezultat.

## M2-08 — otkaz i premještanje

**Ovisnost:** M2-07. **Veza:** 13.4, AC10, AC21.

Prikazati posljedice otkaza prema snapshotu. Pravovremeni vraća izvorni kredit; kasni oslobađa mjesto bez reversala. Nema refund novca. Nakon početka nema standardnog korisničkog otkaza/movea; owner iznimka zasebna je radnja.

Move vrijedi za ekvivalentnu vrstu, isti trošak i grant valjan na novi datum. Osigurati novo mjesto i prenijeti pravo atomski. Nakon besplatnog otkaznog roka ne dopustiti move koji bi zaobišao penal. Razlika cijene/usluge nije podržana u M2.

**Prihvat:** puni novi termin ostavlja stari booking i saldo netaknutima. Dupli otkaz ne vraća dva kredita. Točna granična vremena testirana. Rebooking ima novu poslovnu operaciju i povijest, bez dva aktivna mjesta.

## M2-09 — otkaz studija i iznimke

**Ovisnost:** M2-08. **Veza:** 12.3, 13.4, AC12.

Owner/manager otkazuje cijeli termin uz razlog i pregled pogođenih. Jedna kratka transakcija otkazuje termine/bookinge, vraća prava i sprema audit/outbox. Email naknadno. Za male yoga grupe batch atomski; masovne operacije preko mnogo termina izvan M2.

Samo owner vraća kredit mimo pravila uz razlog. Kompenzacijski grant povezan s izvornim debitom i jedinstvenom operacijom produljuje samo vraćeni kredit. Zamjenski termin je nov; polaznike ne prebacivati prešutno.

**Prihvat:** retry ne duplicira povrat/kompenzaciju. Istodobni booking/otkaz pravilno razriješen. Nepovezani istekli krediti ne oživljavaju.

## M2-10 — raspored i portal na oba dizajna

**Ovisnosti:** M2-07–M2-09. **Veza:** 6, 7, 11.

Javni raspored s filtrima vrste/instruktora/datuma, privatni slotovi, trajanje, kapacitet, kredit i pravila. Zona studija jasno prikazana. Portal s rezervacijama, detaljem, otkazom/moveom, paketima i korisničkom kreditnom poviješću bez privatnih bilješki.

Zajednički backend, sačuvani različiti Lotus/Balance layouti. Nakon logina odabir termina samo je namjera, ponovno provjeriti kapacitet. Uspjeh tek nakon server potvrde. Prazno/loading/error/puni termin imaju jasna stanja.

**Prihvat:** oba dizajna prolaze isti booking tok. Bez kredita nema neplaćenog bookinga. Zastarjeli slot daje smislen konflikt. Mobilni/keyboard prikaz radi; javno nema imena polaznika. Ponovljeni klik ne duplicira.

## M2-11 — ručni dolasci

**Ovisnost:** M2-10. **Veza:** 14.1, AC15.

Instruktor vidi svoje termine i minimalni popis; owner/manager studio. Prisutan bilježi autora/vrijeme. Nakon kraja eksplicitno potvrditi no_show; nezavršena evidencija ostaje pending. Ispravak zahtijeva razlog/audit. Bez QR-a.

**Prihvat:** ponovni klik ne kreira duplikat ni debit. Tuđi tenant/instruktor odbijen. Otkazani booking nema check-in. Zaboravljena evidencija ne uzrokuje automatski no-show.

## M2-12 — obavijesti i podsjetnici

**Ovisnosti:** M2-09, M2-11. **Veza:** 18, AC21.

Outbox/queue šalje potvrdu, otkaz, move i promjenu instruktora/prostora kroz lokalni M1 transport. Reminder 24 h prije, preskočiti zastarjele i redundantne podsjetnike za kasnije nastali booking. Prije slanja ponovno provjeriti status/verziju termina.

Dedup: tenant + događaj + booking/verzija + primatelj + vrsta. Dispatcher oporavlja outbox nakon pada procesa. Log pokušaja bez tajni. Ne obećavati exactly-once vanjsku email isporuku; neizvjestan odgovor ima dokumentiran postupak, bez slijepog beskonačnog retryja.

**Prihvat:** rollback ne šalje potvrdu; pad nakon commita ne gubi događaj. Otkaz/move sprječava stari reminder. Sender/URL/sadržaj pripadaju pravom studiju, vidljivi u lokalnom inboxu. SES nije uvjet M2.

## M2-13 — operativni dashboardi

**Ovisnosti:** M2-11–M2-12. **Veza:** 8–10.

Današnji/budući termini, confirmed/slobodna mjesta, pending/present/no_show. Popunjenost: zbroj confirmed mjesta / zbroj kapaciteta odabranih neotkazanih objavljenih termina. Razdoblje i obuhvat eksplicitni; kasni otkazi zasebno. Povijesni prikaz čuva kontekst kapaciteta/statusa.

Platform admin dobiva agregate po studiju i ukupno, bez osobnih popisa. Instruktor samo svoje termine. Nema izmišljene zarade iz demo paketa ni Fathom podataka.

**Prihvat:** brojke odgovaraju bazi; ukupna stopa iz brojnika/nazivnika, ne prosjek postotaka. Prazan obuhvat i nula pravilno prikazani. Tenant ovlasti provjerene na svakom izvještaju.

## M2-14 — konkurentni testovi i CI

**Ovisnosti:** M2-01–M2-13. **Veza:** AC01–AC07, AC10, AC12, AC21 i relevantni AC27–AC29.

Proširiti postojeći CI. Race testove izvršavati s odvojenim MySQL konekcijama/procesima i kontroliranim istodobnim početkom; sekvencijalni pozivi nisu race test. SQLite ne zamjenjuje MySQL provjeru lockova/constraintova.

Obvezni slučajevi: zadnje mjesto/kredit; novi privatni intervali; grupni/privatni konflikt; preklop polaznika; booking nasuprot otkazu/promjeni rasporeda; duplikat/reversal; istek granta; oba DST prijelaza; granica roka; move na puni termin; kompenzacija; tuđi ID/cache/job; pogrešna uloga; deaktivacija; stari reminder; ponovljeni seed.

**Prihvat:** poslovni i M1 testovi prolaze, frontend build/typecheck uredni. Ručno pregledani oba dizajna i mobilni dolasci. Zapisati izvršene provjere i blokade; ne tvrditi da neizvršeni test prolazi.

## M2-15 — demo i predaja

**Ovisnost:** M2-14.

Idempotentan lokalni seed: dva studija, više vrsta/instruktora, puni/slobodni termini, privatna dostupnost, aktivni/potrošeni/istekli paketi, otkazi i pending dolasci. Umjetni podaci, seed blokiran u produkciji. Kontrola sata samo u testovima, bez javnog endpointa.

README opisuje migracije, scheduler/workere, inbox, pravila i ograničenja. U implementacijskom backlogu označiti završene zadatke i povezati stvarne datoteke/testove.

Demo: owner kreira grupu → dodijeli demo paket → customer rezervira zadnje mjesto → drugi odbijen → prvi pravovremeno otkaže i dobije kredit → privatni slot potvrđen, preklopljeni odbijen → instruktor označi dolazak bez dodatnog debita → studio otkaže drugi termin i vrati prava → Balance ostaje nepromijenjen → platform admin vidi točne agregate.

**Prihvat:** ponovljivo bez stvarne kartice, fiskalnog računa i produkcijskog emaila. Izvor svakog prava vidljiv adminu. M2 nije dovršen uz curenje podataka, overbooking ili negativan saldo.

## Završni rezultat i nastavak

M2 daje operativni razvojni demo s prethodno dodijeljenim pravima. M3 zatim povezuje Stripe Connect/test naplate, narudžbe, holdove, refund novca, fiskalni adapter nakon izbora providera, usklađivanje statusa i SES. Prije stvarne prodaje dovršiti cijeli financijski tok.

Sljedeći inkrementi prije punog lansiranja uključuju članarine/stalne grupe, Meet, Bunny videoteku, Fathom, širi CMS i produkcijsku pripremu. Redoslijed ne smanjuje potvrđeni produktni opseg A.

## Prompt za Codex — prva isporuka M2-A

> Nastavi postojeću yoga platformu nakon M1. Pročitaj AGENTS.md, specifikacija-platforme-v1.md i razvojni-backlog-m2.md na njihovim putanjama u repozitoriju te pregledaj stvarni kod. Implementiraj M2-01 do M2-05 s testovima: proširenje modela, vrste/prostore/instruktore, verzionirana pravila, grupne termine/ponavljanje i privatnu dostupnost. Sačuvaj tenant izolaciju, auth/2FA, admin layoute i oba dizajna. Ne scaffoldaj novu aplikaciju, ne briši postojeće podatke i ne uvodi produkcijske integracije. Odabrani slot nije potvrđena rezervacija: booking servis dolazi u M2-07. Dokumentiraj migracije, provjere i preostale zadatke. Nastavak je M2-06–M2-09, zatim M2-10–M2-15.

Radni defaulti koriste se u razvoju/testovima, a poslovna pravila potvrđuju prije stvarne prodaje. Rutinske odluke rješavati prema postojećem kodu i navedenim kriterijima.
