# Rezervacije — navodila za delo s kodo

Splošna rezervacijska aplikacija (Laravel 13 + Inertia/React + TypeScript). Pregled pojmov in
arhitekture je v `README.md`.

## Okolje

- Lokalno teče na Laravel Herd (PHP 8.5): http://rezervacije-app.test. `php`, `composer`, `psql`
  so Herdovi (v PowerShellu; v Git Bashu niso na PATH). Baza je Herdov PostgreSQL 18
  (127.0.0.1:5432, `root`, brez gesla, baza `rezervacije_app`); testi tečejo na SQLite v pomnilniku.
- Ne poganjaj artisan v Dockerju z bind mountom projekta — `storage:link` v vsebniku pokvari
  `public/storage` (kaže na `/app/...`). Docker je samo za preizkus produkcijske slike (`docker compose up`).
- Pošta gre v Herdov lovilnik (SMTP 2525).
- Node je lokalen: `npx tsc --noEmit`, `npm run build`.
- Po spremembah PHP poženi `vendor/bin/pint` in teste.

## Pravila

- Komentarji v kodi so slovenski, identifikatorji angleški.
- Besedila v vmesniku in sporočila so angleški ključi, preveden v `lang/sl.json`
  (`t()` / `tc()` v TSX, `__()` / `trans_choice()` v PHP). Ob novem besedilu dodaj prevod.
  Množinske oblike v slovenščini imajo 4 oblike: ednina|dvojina|3–4|5+.
- Pravila rezervacij so v `app/Services/Booking/` — kontrolerji jih ne podvajajo. Nov način
  rezervacije = nov `Modes/*Handler` + vnos v `BookingService::handler()` + izbirnik v
  `resources/js/Components/booking/`.
- Kršitve pravil sproži kot `BookingException(polje, sporočilo)`; samodejno postane napaka validacije.
- Ustvarjanje rezervacije in dodelitev vira zakleneta vrstico tipa (`lockForUpdate`) proti dvojnim rezervacijam.
- Časi so lokalni čas podjetja (`APP_TIMEZONE`), datumi v Inertia props so `Y-m-d` / `Y-m-d\TH:i` brez pasu.
- Tailwind barve so CSS spremenljivke prek `color-mix`, zato modifikatorji prosojnosti (`bg-primary/15`) delujejo.
