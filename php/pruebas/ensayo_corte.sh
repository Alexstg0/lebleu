#!/usr/bin/env bash
# ENSAYO DEL CORTE de punta a punta, sin tocar producción: exporta Postgres (como el día real), arma una base MySQL TEMPORAL
# con el esquema, importa el respaldo y valida que los datos y las vistas del estado de cuenta cuadren con el origen.
# Requisitos: PG_URL (Postgres de origen), `mysql` con permiso de administrador (CREATE DATABASE), PHP, Node con `npm install` en la raíz.
# Uso:  PG_URL=postgres://usuario:clave@host/base bash pruebas/ensayo_corte.sh
set -euo pipefail
cd "$(dirname "$0")/.."
RAIZ="$(cd .. && pwd)"
: "${PG_URL:?Falta PG_URL (la base Postgres de origen)}"
TEMP=lebleu_ensayo_corte
TMP="$(mktemp -d)"
CONFIG="$TMP/config.php"
mysql -e "DROP DATABASE IF EXISTS $TEMP; CREATE DATABASE $TEMP CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON $TEMP.* TO 'lebleu'@'localhost';"
trap 'mysql -e "DROP DATABASE IF EXISTS $TEMP"; rm -rf "$TMP"' EXIT
sed "s/'nombre' => '[^']*'/'nombre' => '$TEMP'/" config/config.php > "$CONFIG"
export LEBLEU_CONFIG="$CONFIG"

echo "== 1. Exportar de Postgres"
(cd "$RAIZ" && DATABASE_URL="$PG_URL" node scripts/exportar-respaldo.mjs > "$TMP/respaldo.sql")
echo "   $(grep -c '^INSERT' "$TMP/respaldo.sql") filas en $(grep -c '^-- Tabla' "$TMP/respaldo.sql") tablas ($(du -h "$TMP/respaldo.sql" | cut -f1))"
echo "== 2. Esquema en MySQL"
php scripts/migrar.php | tail -2
echo "== 3. Importar"
SECONDS=0
php -d memory_limit=1G scripts/importar_respaldo.php "$TMP/respaldo.sql" | tail -4
echo "   importado en ${SECONDS}s"
echo "== 4. Cuadre contra Postgres (conteos, fechas, sumas y vistas)"
PG_DSN="pgsql:host=$(echo "$PG_URL" | sed -E 's#.*@([^:/]+).*#\1#');dbname=${PG_URL##*/}" PG_USER="$(echo "$PG_URL" | sed -E 's#.*//([^:]+):.*#\1#')" PG_PASS="$(echo "$PG_URL" | sed -E 's#.*//[^:]+:([^@]+)@.*#\1#')" php pruebas/paridad_postgres.php | tail -4
echo "== 5. Contraseñas y servidor"
CLAVES="$(php scripts/restablecer_claves.php)"
echo "   $(echo "$CLAVES" | grep -c -E '^(admin|capitan|socio)') usuarios con contraseña temporal nueva (no se muestran aquí)"
php scripts/verificar_servidor.php | grep -E "FALLA|Sin fallas|listo" || true
echo
echo "ENSAYO DE CORTE OK: el respaldo se importa completo y cuadra con el origen."
