# Postavitev na strežnik

Aplikacija teče kot Docker slika **`iztokvozlic/rezervacije-app`** (Docker Hub). Vsaka stranka ima
svojo instanco: aplikacijski vsebnik `<ime>-app` in bazo `<ime>-postgres` (PostgreSQL 17), dosegljivo
na `https://<ime>.epro.one` prek obstoječega Traefika.

```
razvojni računalnik                      strežnik
  ./publish.sh  ──►  Docker Hub  ──►  ./deploy.sh <ime>  ──►  <ime>-app + <ime>-postgres
  (gradnja + push)                     (pull, varnostna kopija, zamenjava vsebnika)
```

## 1. Objava nove različice (razvojni računalnik)

```sh
git commit ...             # objavi se samo commitana koda
./publish.sh               # zgradi linux/amd64 in objavi :<commit> in :latest
```

Potrebna je prijava v Docker Hub (`docker login`, uporabnik `iztokvozlic`).

## 2. Priprava strežnika (enkrat)

Predpogoji (že izpolnjeni na strežniku, kjer teče kk-karlo):

- Docker z vtičnikom Compose
- Traefik z omrežjem `proxy-network`, vstopnima točkama `web` in `websecure` ter certifikatom `*.epro.one`
- DNS `*.epro.one` kaže na strežnik

```sh
cd /srv/project
git clone https://github.com/iztok32/rezervacije-app.git
cd rezervacije-app
chmod +x deploy.sh
```

Strežnik potrebuje iz repozitorija samo `deploy.sh`, `docker-compose.instance.yml` in mapo `instances/`;
koda je v sliki.

## 3. Nova stranka

```sh
./deploy.sh salon-maja hairdresser        # → https://salon-maja.epro.one
./deploy.sh kmetija glamping,riding       # več predlog hkrati
```

Predloge: `riding`, `group_classes`, `hairdresser`, `car_rental`, `glamping`, `hotel`, `court`.

Prva postavitev:

1. ustvari `instances/<ime>.env` (APP_KEY, geslo baze, geslo administratorja; datoteka ni v gitu),
2. zažene aplikacijo in bazo, migracije se izvedejo ob zagonu,
3. naloži administratorja in izbrane predloge (brez vzorčnih virov; za demo `SEED_SAMPLES=true ./deploy.sh …`),
4. izpiše prijavo administratorja.

Nato v `instances/<ime>.env` vpišite podatke za **e-pošto** (`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_FROM_ADDRESS`) in ponovno zaženite `./deploy.sh <ime>`. Brez tega potrditve, opomniki in sporočila ne
odidejo.

> Ime `rezervacije` je že zasedeno (instanca iz projekta kk-karlo). `deploy.sh` zavrne ime, katerega
> vsebnik že obstaja, a ni ustvarjen s to aplikacijo.

## 4. Posodobitev

```sh
cd /srv/project/rezervacije-app
git pull                        # če so se spremenili deploy.sh ali compose
./deploy.sh --all               # vse instance na :latest
./deploy.sh salon-maja          # samo ena instanca
./deploy.sh --list              # pregled instanc in slik
```

Pred zamenjavo vsebnika se baza shrani v `/home/docker/backups/<ime>.predeploy.<čas>.sql.gz`
(hrani se 5 najnovejših). Zamenja se samo aplikacijski vsebnik; baza in naložene datoteke (volumni) ostanejo.

## Povrnitev na prejšnjo različico

```sh
DOCKER_IMAGE=iztokvozlic/rezervacije-app:<commit> ./deploy.sh salon-maja
```

Migracije, ki jih je že izvedla novejša različica, ostanejo. Za popolno povrnitev obnovite bazo iz kopije:

```sh
gunzip -c /home/docker/backups/salon-maja.predeploy.<čas>.sql.gz | docker exec -i salon-maja-postgres psql -U <uporabnik> -d <baza>
```

## V vsebniku

- nginx + PHP-FPM, **queue worker** (e-pošta ročnih sporočil) in **razporejevalnik** (opomniki vsakih
  15 minut) prek supervisord
- `docker logs <ime>-app` — dnevnik aplikacije
- `docker exec -it <ime>-app php artisan …` — ukazi, npr. `preset:add court --samples`
- `docker exec -it <ime>-postgres psql -U <uporabnik> -d <baza>` — baza
