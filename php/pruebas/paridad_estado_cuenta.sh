#!/usr/bin/env bash
# Compara el estado de cuenta de la versión PHP/MySQL contra la lógica ORIGINAL (lib/queries.ts) sobre Postgres.
# Requisitos: base Postgres de referencia con los mismos datos, Node 22 y `npm install` en la raíz del repo.
# Uso:  PG_URL=postgres://usuario:clave@127.0.0.1/lebleu_ref bash pruebas/paridad_estado_cuenta.sh
set -euo pipefail
cd "$(dirname "$0")/.."
TMP="$(mktemp -d)"
DATABASE_URL="${PG_URL:?Falta PG_URL}" node --experimental-strip-types --no-warnings \
  --import ./pruebas/referencia/registro.mjs pruebas/referencia/estado_cuenta.mjs > "$TMP/referencia.json"
php pruebas/estado_cuenta_json.php > "$TMP/php.json"
php pruebas/comparar_json.php "$TMP/referencia.json" "$TMP/php.json"
