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

### Vloge

- **admin** — vse: ponudba, uporabniki, nastavitve, rezervacije
- **staff** — rezervacije (potrjevanje, odpovedi, dodelitev vira, vnos za stranko) in koledar
- **customer** — svoje rezervacije

Gost (brez računa) dobi po e-pošti povezavo z žetonom za ogled in odpoved rezervacije.

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

# baza z demo podatki: admin@example.com / password, staff@example.com, stranka@example.com
cp .env.example .env
php artisan key:generate
psql -h 127.0.0.1 -U root -d postgres -c "create database rezervacije_app"
php artisan migrate:fresh --seed
php artisan storage:link

npm run dev          # Vite (ali npm run build)
php artisan test     # testi tečejo na SQLite v pomnilniku, razvojna baza ostane nedotaknjena
```

E-pošta gre v Herdov lovilnik pošte (SMTP 127.0.0.1:2525) — sporočila vidite v Herd → Mail.

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
