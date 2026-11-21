#!/usr/bin/env bash
#
# deploy.sh — postavi ali posodobi instanco za stranko na strežniku (zaženi na strežniku).
# Slika se potegne iz Docker Hub (zgradi in objavi jo ./publish.sh na razvojnem računalniku).
#
#   ./deploy.sh <ime> [predloge]    npr. ./deploy.sh salon-maja hairdresser
#                                         ./deploy.sh kmetija glamping,riding
#   ./deploy.sh --all               posodobi vse instance v instances/*.env
#   ./deploy.sh --list              izpiše instance in njihovo stanje
#
# Prva postavitev nove instance ustvari instances/<ime>.env (APP_KEY, geslo baze, geslo
# administratorja) in naloži začetne podatke. Posodobitev naredi varnostno kopijo baze,
# potegne novo sliko in zamenja samo aplikacijski vsebnik; migracije se izvedejo ob zagonu.
#
# Okolje:
#   DOCKER_IMAGE    privzeto iztokvozlic/rezervacije-app:latest (npr. :e1e19e6 za povrnitev)
#   DOMAIN_SUFFIX   privzeto epro.one → <ime>.epro.one
#   BACKUP_DIR      privzeto /home/docker/backups (hrani 5 najnovejših kopij na instanco)
#   SEED_ADMIN_EMAIL, SEED_SAMPLES — ob prvi postavitvi

set -euo pipefail
cd "$(dirname "$0")"

IMAGE="${DOCKER_IMAGE:-iztokvozlic/rezervacije-app:latest}"
COMPOSE="docker-compose.instance.yml"
DOMAIN_SUFFIX="${DOMAIN_SUFFIX:-epro.one}"
BACKUP_DIR="${BACKUP_DIR:-/home/docker/backups}"
export DOCKER_IMAGE="${IMAGE}"

usage() {
  sed -n '3,12p' "$0" | sed 's/^# \{0,1\}//' >&2
  exit 1
}

random() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "$1"; }

preflight() {
  command -v docker >/dev/null || { echo "Docker ni nameščen." >&2; exit 1; }
  docker network inspect proxy-network >/dev/null 2>&1 \
    || { echo "Omrežje proxy-network (Traefik) ne obstaja: docker network create proxy-network" >&2; exit 1; }
  mkdir -p instances
}

pull_image() {
  echo "==> Vlečem ${IMAGE} ..."
  docker pull "${IMAGE}"
}

create_env() {
  local name="$1" presets="$2" file="instances/$1.env"
  local db="${name//-/_}"
  echo "==> Nova instanca: ustvarjam ${file}"
  umask 077
  cat > "${file}" <<EOF
INSTANCE=${name}
APP_NAME=Rezervacije
APP_DOMAIN=${name}.${DOMAIN_SUFFIX}
APP_URL=https://${name}.${DOMAIN_SUFFIX}
APP_KEY=base64:$(openssl rand -base64 32)
DB_DATABASE=${db}
DB_USERNAME=${db}
DB_PASSWORD=$(random 32)
SEED_ADMIN_EMAIL=${SEED_ADMIN_EMAIL:-admin@${name}.${DOMAIN_SUFFIX}}
SEED_ADMIN_PASSWORD=$(random 16)
SEED_PRESETS=${presets}
SEED_SAMPLES=${SEED_SAMPLES:-false}

# E-pošta (izpolnite in ponovno zaženite ./deploy.sh ${name})
MAIL_HOST=
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@${name}.${DOMAIN_SUFFIX}
MAIL_FROM_NAME=Rezervacije
EOF
}

backup_db() {
  local name="$1" file="instances/$1.env"
  docker ps --format '{{.Names}}' | grep -qx "${name}-postgres" || return 0

  mkdir -p "${BACKUP_DIR}"
  local db user target
  db="$(grep -E '^DB_DATABASE=' "${file}" | cut -d= -f2-)"
  user="$(grep -E '^DB_USERNAME=' "${file}" | cut -d= -f2-)"
  target="${BACKUP_DIR}/${name}.predeploy.$(date +%Y%m%d-%H%M%S).sql.gz"

  echo "==> Varnostna kopija baze → ${target}"
  docker exec "${name}-postgres" pg_dump -U "${user}" -d "${db}" | gzip > "${target}"
  # Obdrži 5 najnovejših
  ls -1t "${BACKUP_DIR}/${name}".predeploy.*.sql.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
}

wait_healthy() {
  local name="$1"
  echo "==> Čakam, da se ${name} odzove ..."
  for _ in $(seq 1 60); do
    if docker exec "${name}-app" curl -fsS -o /dev/null http://localhost/up 2>/dev/null; then
      return 0
    fi
    sleep 2
  done
  echo "Aplikacija se ni odzvala. Dnevnik: docker logs ${name}-app" >&2
  return 1
}

deploy() {
  local name="$1" presets="${2:-}" file="instances/$1.env" first=0

  [[ "${name}" =~ ^[a-z0-9][a-z0-9-]*$ ]] || { echo "Neveljavno ime: ${name}" >&2; exit 1; }

  if [[ ! -f "${file}" ]]; then
    # Vsebnik s tem imenom že obstaja, a ni naša instanca (npr. rezervacije iz kk-karlo)
    if docker ps -a --format '{{.Names}}' | grep -qx "${name}-app"; then
      echo "Vsebnik ${name}-app že obstaja in ne pripada tej aplikaciji. Izberite drugo ime." >&2
      exit 1
    fi
    [[ -n "${presets}" ]] || { echo "Nova instanca potrebuje predloge, npr. ./deploy.sh ${name} hairdresser" >&2; exit 1; }
    create_env "${name}" "${presets}"
    first=1
  else
    backup_db "${name}"
  fi

  echo "==> Postavljam ${name} (${IMAGE}) ..."
  COMPOSE_PROJECT_NAME="${name}" docker compose -f "${COMPOSE}" --env-file "${file}" up -d --remove-orphans

  wait_healthy "${name}"

  if [[ "${first}" == 1 ]]; then
    echo "==> Polnim začetne podatke ..."
    set -a; source "${file}"; set +a
    docker exec \
      -e SEED_ADMIN_EMAIL="${SEED_ADMIN_EMAIL}" \
      -e SEED_ADMIN_PASSWORD="${SEED_ADMIN_PASSWORD}" \
      -e SEED_PRESETS="${SEED_PRESETS}" \
      -e SEED_SAMPLES="${SEED_SAMPLES}" \
      "${name}-app" php artisan db:seed --force
    echo "==> Prijava: ${SEED_ADMIN_EMAIL} / ${SEED_ADMIN_PASSWORD} (shranjeno v ${file})"
  fi

  echo "==> ${name} je postavljen: $(grep -E '^APP_URL=' "${file}" | cut -d= -f2-)"
}

list() {
  for f in instances/*.env; do
    [[ -e "${f}" ]] || { echo "Ni instanc."; return; }
    local n; n="$(basename "${f}" .env)"
    [[ "${n}" == "example" ]] && continue
    printf '%-24s %-40s %s\n' "${n}" "$(grep -E '^APP_URL=' "${f}" | cut -d= -f2-)" \
      "$(docker ps --filter "name=^${n}-app$" --format '{{.Status}} · {{.Image}}' || true)"
  done
}

[[ $# -ge 1 ]] || usage
preflight

case "$1" in
  --list) list ;;
  --all)
    pull_image
    for f in instances/*.env; do
      [[ -e "${f}" ]] || { echo "Ni instanc."; exit 0; }
      n="$(basename "${f}" .env)"
      [[ "${n}" == "example" ]] && continue
      deploy "${n}"
    done
    ;;
  -*) usage ;;
  *)
    pull_image
    deploy "$1" "${2:-}"
    ;;
esac
