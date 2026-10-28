#!/usr/bin/env bash
# Contrôles locaux (pas de CI GitHub Actions) : à lancer avant chaque push.
# Usage : ./scripts/check.sh [api|web]   (sans argument : les deux)
# Prérequis : PHP 8.3 (pdo_pgsql), Node 22, PostgreSQL local avec les bases
# `ftth` et `ftth_test` (utilisateur ftth / ftth, voir backend/phpunit.xml).
set -euo pipefail
cd "$(dirname "$0")/.."

api() {
  echo "== API : Pint + tests =="
  (cd backend && vendor/bin/pint --test && php artisan test)
}

web() {
  echo "== Web : lint + typecheck + tests + build =="
  (cd frontend && npm run lint && npm run typecheck && npm run test && npm run build)
}

case "${1:-all}" in
  api) api ;;
  web) web ;;
  all) api; web ;;
  *) echo "Usage : $0 [api|web]"; exit 2 ;;
esac
echo "Tous les contrôles sont verts."
