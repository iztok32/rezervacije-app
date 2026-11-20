# Rezervacije

Splošna aplikacija za spletne rezervacije, ki jo je mogoče prilagoditi za različne dejavnosti:
konjeniški klub, frizerski salon, najem avtomobilov, glamping hiške, hotelske sobe, igrišča …
Ena namestitev (instanca) služi eni stranki; vsebino in pravila nastavi administrator.

Tehnologija: Laravel 13, Inertia 2 + React 18 (TypeScript), Tailwind 3 + shadcn/ui, PostgreSQL (SQLite za teste).

## Pojmi

| Pojem | Pomen | Primeri |
| --- | --- | --- |
| **Tip vira** (`resource_types`) | Ponudba z enim načinom rezervacije in skupnimi pravili | Jahanje, Frizerski salon, Sobe |
| **Vir** (`resources`) | Kar se rezervira | konj, frizerka Maja, VW Golf, Soba 101 |
| **Storitev** (`services`) | Storitev s trajanjem (samo način *service*) | Moško striženje 30 min |
| **Termin** (`timeslots`) | Ponavljajoč termin s kapaciteto (samo način *timeslot*) | Začetniki, tor+čet 17–18, 4 mesta |
| **Delovni čas** (`working_hours`) | Kdaj je vir na voljo (načina *service* in *hourly*); privzet za tip ali lasten za vir | pon 8–16 |
| **Zaprtje** (`closures`) | Ko rezervacije niso mogoče: celo podjetje, tip ali en vir | praznik, dopust, servis |
| **Rezervacija** (`bookings`) | Prijavljen uporabnik ali gost; statusi pending/confirmed/cancelled/rejected/completed/no_show | |

### Načini rezervacije

| Način | Stranka izbere | Cena |
| --- | --- | --- |
| `timeslot` — termini s kapaciteto | termin na dan (+ vir, npr. konja) in število mest | cena termina × mesta |
| `service` — storitve s trajanjem | storitev, izvajalca ali »kdorkoli«, prost začetek | cena storitve |
| `nightly` — dnevi/nočitve | vir in obdobje od–do (po nočeh ali po dnevih) | cena vira × noči/dni |
| `hourly` — urni najem | vir, dan, začetek in konec | cena vira × ure |

Nastavitve tipa: kdo izbere vir (stranka / samodejno / osebje), ali lahko rezervirajo gostje,
ali je potrebna potrditev, okno za rezervacije (najmanj X min, največ Y dni vnaprej), rok za odpoved,
korak začetnih ur, min/max trajanje, čas prihoda/odhoda, min/max noči …

### Vloge, pravice in skupine strank

Stranka nima vloge. Osebje dobi **vlogo** (`roles`), ki jo administrator ureja v aplikaciji (Vloge):

- **pravice** — seznam je v kodi (`App\Enums\Permission`): `bookings.view`, `bookings.manage`,
  `bookings.create`, `offer.manage`, `closures.manage`, `users.manage`, `roles.manage`, `settings.manage`;
  vsaka je tudi Gate (`middleware('can:bookings.view')`, `$user->can(...)`)
- **doseg rezervacij** — vse ali *samo svoji viri* (vir z `user_id` ali vodja termina), npr. frizerka vidi svoje stranke
- **omejitev na ponudbo** — vloga velja samo za izbrane tipe virov (prazno = vsi)

Migracija ustvari sistemske vloge *Administrator* (vse pravice, ni urejljiva), *Recepcija* in *Izvajalec*.
Vlogo uporabniku dodeli samo, kdor ima `roles.manage`; administratorsko vlogo in administratorje ureja
samo administrator. Meni v administraciji se zgradi iz pravic.

**Skupine strank** (`customer_groups`) omejijo, kdo vidi in rezervira tip vira ali posamezen termin
(npr. »samo člani«, »napredni jahači«); brez izbranih skupin je ponudba odprta za vse.

Gost (brez računa) dobi po e-pošti povezavo z žetonom za ogled in odpoved rezervacije.

### Obveščanje strank

- **Samodejna e-pošta** ob oddaji, potrditvi, zavrnitvi in odpovedi rezervacije (`BookingNotifier`).
- **Opomnik** X ur pred začetkom potrjene rezervacije — nastavitev tipa vira (`reminder_hours`, prazno = brez).
  Ukaz `bookings:send-reminders` teče v razporejevalniku vsakih 15 minut.
- **Ročna sporočila** (Obveščanje, pravica `messages.send`): prejemniki so stranke/skupine strank, stranke z
  rezervacijo v obdobju (tudi gostje; po želji tip ali vir) ali izbrani uporabniki. Kanala: e-pošta (v čakalni
  vrsti, stanje po prejemnikih) in nabiralnik v portalu (meni »Sporočila« z oznako neprebranih).
- **Zaprtje z obveščanjem**: obrazec za zaprtje pokaže rezervacije v tem obdobju in jih po izbiri odpove;
  stranke dobijo e-pošto o odpovedi z razlogom zaprtja.

### Vsebina in zgodovina

- **Vsebinske strani** (Vsebina → Strani, pravica `content.manage`): besedilo v Markdownu, na strežniku
  pretvorjeno v varen HTML (brez surovega HTML-ja in `javascript:` povezav); javno na `/p/{slug}`,
  osnutke vidijo samo uredniki.
- **Javni meni** (Vsebina → Meni): postavke v glavi in nogi javne strani — stran, ponudba ali povezava;
  vidno vsem, samo gostom ali samo prijavljenim. Neobjavljene strani in ponudba, do katere obiskovalec nima
  dostopa (skupine strank), se ne prikažejo.
- **Zgodovina rezervacije** (`booking_events`): oddaja, spremembe statusa, dodelitev vira, interne opombe,
  opomnik — s časom in osebo; prikazana v podrobnostih rezervacije.

## Koda

- `app/Services/Booking/` — jedro: `BookingService` (skupna pravila, zaklepanje, statusi),
  `Availability` (delovni čas, zaprtja, zasedenost), `Modes/*Handler` (en razred na način rezervacije)
- `app/Support/Presets.php` — predloge za hitro postavitev (riding, group_classes, hairdresser, car_rental, glamping, hotel, court)
- `resources/js/Components/booking/` — izbirniki za posamezne načine
- `lang/sl.json` — prevodi (ključ je angleško besedilo); `useTranslation()` v `resources/js/lib/i18n.ts`

Vsi časi so v lokalnem času podjetja (`APP_TIMEZONE`, privzeto Europe/Ljubljana).

## Razvoj

Lokalno teče na [Laravel Herd](https://herd.laravel.com) (PHP 8.5) s Herdovo storitvijo PostgreSQL
(port 5432, uporabnik `root`, brez gesla). Mapa `D:\web-project` je parkirana, zato je stran
samodejno dosegljiva na http://rezervacije-app.test.

```sh
composer install
npm install

# baza z demo podatki (geslo password): admin@, staff@ (recepcija), maja@ (izvajalka), stranka@example.com
cp .env.example .env
php artisan key:generate
psql -h 127.0.0.1 -U root -d postgres -c "create database rezervacije_app"
php artisan migrate:fresh --seed
php artisan storage:link

npm run dev          # Vite (ali npm run build)
php artisan test     # testi tečejo na SQLite v pomnilniku, razvojna baza ostane nedotaknjena
```

E-pošta gre v Herdov lovilnik pošte (SMTP 127.0.0.1:2525) — sporočila vidite v Herd → Mail.
Lokalno je `QUEUE_CONNECTION=sync` (ročna sporočila se pošljejo takoj); opomnike preizkusite z
`php artisan bookings:send-reminders` ali `php artisan schedule:work`. V Dockerju tečeta queue worker in
razporejevalnik prek supervisord.

Preizkus produkcijske slike s PostgreSQL: `docker compose up --build`, nato
`docker compose exec app php artisan db:seed --force` → http://localhost:8080.

## Nova stranka (instanca)

```sh
./deploy.sh salon-maja hairdresser           # → https://salon-maja.epro.one
./deploy.sh kmetija glamping,riding          # več tipov hkrati
./deploy.sh --all                            # posodobi vse instance
```

Prva postavitev ustvari `instances/<ime>.env` (skrivnosti, ni v gitu), migrira bazo in naloži
administratorja ter izbrane predloge. Nadaljnje prilagajanje opravi administrator v aplikaciji
(Ponudba, Nastavitve) ali z `php artisan preset:add <predloga> [--samples] [--name=...]`.
