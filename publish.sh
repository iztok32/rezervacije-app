#!/usr/bin/env bash
#
# publish.sh — zgradi produkcijsko sliko in jo objavi v Docker Hub (zaženi na razvojnem računalniku).
#
#   ./publish.sh              # oznaki <commit> in latest
#   ./publish.sh --no-push    # samo lokalna gradnja
#
# Okolje:
#   DOCKER_REPO   privzeto iztokvozlic/rezervacije-app
#   PLATFORM      privzeto linux/amd64 (strežnik)
#
# Strežnik nato sliko potegne z ./deploy.sh (glej DEPLOYMENT.md).

set -euo pipefail
cd "$(dirname "$0")"

REPO="${DOCKER_REPO:-iztokvozlic/rezervacije-app}"
PLATFORM="${PLATFORM:-linux/amd64}"
PUSH=1
[[ "${1:-}" == "--no-push" ]] && PUSH=0

# Objavi se samo commitana koda — oznaka slike mora ustrezati commitu
if [[ -n "$(git status --porcelain)" ]]; then
  echo "Delovni imenik ima necommitane spremembe. Najprej jih commitajte." >&2
  exit 1
fi

VERSION="$(git rev-parse --short HEAD)"

echo "==> Gradnja ${REPO}:${VERSION} (${PLATFORM}) ..."
docker build --platform "${PLATFORM}" \
  --label "org.opencontainers.image.revision=$(git rev-parse HEAD)" \
  --label "org.opencontainers.image.created=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  -t "${REPO}:${VERSION}" -t "${REPO}:latest" .

if [[ "${PUSH}" == 1 ]]; then
  echo "==> Objava v Docker Hub ..."
  docker push "${REPO}:${VERSION}"
  docker push "${REPO}:latest"
fi

echo "==> Končano: ${REPO}:${VERSION} (+ latest)"
