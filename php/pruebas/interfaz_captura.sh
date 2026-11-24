#!/usr/bin/env bash
# Corre pruebas/interfaz_captura.mjs contra una COPIA desechable de la base (la real no se toca).
# Requisitos: mysql/mysqldump con acceso a la base `lebleu`, PHP, Node y Playwright; un admin y un capitán de prueba.
# Uso:  PRUEBA_EMAIL=... PRUEBA_CAPITAN=... PRUEBA_CLAVE=... bash pruebas/interfaz_captura.sh
set -euo pipefail
cd "$(dirname "$0")/.."
COPIA=lebleu_ui_prueba
PUERTO=8098
mysql -e "DROP DATABASE IF EXISTS $COPIA; CREATE DATABASE $COPIA CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON $COPIA.* TO 'lebleu'@'localhost';"
mysqldump --single-transaction --routines --triggers lebleu | mysql "$COPIA"
CONFIG="$(mktemp)"
sed "s/'nombre' => '[^']*'/'nombre' => '$COPIA'/" config/config.php > "$CONFIG"
LEBLEU_CONFIG="$CONFIG" php -S "127.0.0.1:$PUERTO" -t public public/index.php >/tmp/php_ui.log 2>&1 &
SERVIDOR=$!
trap 'kill $SERVIDOR 2>/dev/null; mysql -e "DROP DATABASE IF EXISTS $COPIA"; rm -f "$CONFIG"' EXIT
sleep 1
PHP_URL="http://127.0.0.1:$PUERTO" node pruebas/interfaz_captura.mjs
