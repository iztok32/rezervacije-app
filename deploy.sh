#!/usr/bin/env bash
#
# deploy.sh — zgradi sliko in postavi/posodobi instanco za stranko.
#
#   ./deploy.sh <ime> [predloge]   npr. ./deploy.sh salon-maja hairdresser
#                                       ./deploy.sh kmetija glamping,riding
#   ./deploy.sh --all              posodobi vse instance v instances/*.env
#
# Prva postavitev nove instance ustvari instances/<ime>.env (APP_KEY, geslo baze),
# zažene migracije in seeder (administrator + izbrane predloge). Posodobitve
# seederja nikoli ne poženejo znova — podatki stranke ostanejo nedotaknjeni.
#
# Okolje:
#   DOMAIN_SUFFIX   privzeto epro.one → <ime>.epro.one
#   SEED_ADMIN_EMAIL, SEED_PRESETS, SEED_SAMPLES — ob prvi postavitvi

set -euo pipefail
cd "$(dirname "$0")"

IMAGE="rezervacije:latest"
COMPOSE="docker-compose.instance.yml"
DOMAIN_SUFFIX="${DOMAIN_SUFFIX:-epro.one}"

usage() {
  echo "Uporaba: ./deploy.sh <ime> [predloge] | --all" >&2
  exit 1
}

build_image() {
  echo "==> Gradnja ${IMAGE} ..."
  docker build -t "${IMAGE}" .
}

random() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "$1"; }

create_env() {
  local name="$1" presets="$2" file="instances/$1.env"
  local db="${name//-/_}"
  echo "==> Nova instanca: ustvarjam ${file}"
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
SEED_SAMPLES=${SEED_SAMPLES:-true}
EOF
  chmod 600 "${file}"
}

deploy() {
  local name="$1" presets="${2:-}" file="instances/$1.env" first=0

  [[ "${name}" =~ ^[a-z0-9][a-z0-9-]*$ ]] || { echo "Neveljavno ime: ${name}" >&2; exit 1; }

  if [[ ! -f "${file}" ]]; then
    create_env "${name}" "${presets}"
    first=1
  fi

  echo "==> Postavljam ${name} ..."
  COMPOSE_PROJECT_NAME="${name}" docker compose -f "${COMPOSE}" --env-file "${file}" up -d

  if [[ "${first}" == 1 ]]; then
    echo "==> Čakam na zagon in polnim začetne podatke ..."
    for _ in $(seq 1 30); do
      docker exec "${name}-app" php artisan migrate:status >/dev/null 2>&1 && break
      sleep 2
    done
    set -a; source "${file}"; set +a
    docker exec \
      -e SEED_ADMIN_EMAIL="${SEED_ADMIN_EMAIL}" \
      -e SEED_ADMIN_PASSWORD="${SEED_ADMIN_PASSWORD}" \
      -e SEED_PRESETS="${SEED_PRESETS}" \
      -e SEED_SAMPLES="${SEED_SAMPLES}" \
      "${name}-app" php artisan db:seed --force
    echo "==> Prijava: ${SEED_ADMIN_EMAIL} / ${SEED_ADMIN_PASSWORD} (shranjeno v ${file})"
  fi

  echo "==> ${name} je postavljen."
}

[[ $# -ge 1 ]] || usage
build_image

if [[ "$1" == "--all" ]]; then
  for f in instances/*.env; do
    n="$(basename "${f}" .env)"
    [[ "${n}" == "example" ]] && continue
    deploy "${n}"
  done
else
  deploy "$1" "${2:-}"
fi
